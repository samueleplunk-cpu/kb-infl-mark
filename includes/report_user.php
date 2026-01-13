<?php
// report_user.php
session_start();

// Percorso assoluto per config
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die(json_encode(['success' => false, 'message' => 'Errore di configurazione']));
}
require_once $config_file;

// Controlla se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato. Devi essere loggato per segnalare utenti.']);
    exit();
}

// Permette sia brand che influencer di segnalare
$allowed_user_types = ['brand', 'influencer'];
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], $allowed_user_types)) {
    echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato. Solo brand e influencer possono segnalare utenti.']);
    exit();
}

// Controlla token CSRF (se implementato)
if (isset($_SESSION['csrf_token']) && isset($_POST['csrf_token'])) {
    if ($_SESSION['csrf_token'] !== $_POST['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Token di sicurezza non valido.']);
        exit();
    }
}

// Verifica dati in POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito.']);
    exit();
}

// Validazione dati
$reported_user_id = filter_input(INPUT_POST, 'reported_user_id', FILTER_VALIDATE_INT);
$reason = trim(filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING));

$errors = [];

if (!$reported_user_id || $reported_user_id <= 0) {
    $errors[] = 'ID utente segnalato non valido.';
}

if (empty($reason) || strlen($reason) < 10) {
    $errors[] = 'La motivazione deve contenere almeno 10 caratteri.';
}

if (strlen($reason) > 1000) {
    $errors[] = 'La motivazione non può superare i 1000 caratteri.';
}

// Verifica che l'utente non stia segnalando se stesso
if ($_SESSION['user_id'] == $reported_user_id) {
    $errors[] = 'Non puoi segnalare te stesso.';
}

// Controlla se ci sono errori
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit();
}

try {
    // Inizia transazione
    $pdo->beginTransaction();
    
    // 1. Verifica che l'utente segnalato esista
    $stmt = $pdo->prepare("SELECT id, email, user_type FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$reported_user_id]);
    $reported_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reported_user) {
        throw new Exception("L'utente segnalato non esiste o è stato eliminato.");
    }
    
    // 2. Verifica che il segnalante abbia il profilo corrispondente nel database
    $reporter_profile_id = null;
    $reporter_profile_type = null;
    
    if ($_SESSION['user_type'] === 'brand') {
        // Segnalante è un brand - deve esistere nella tabella brands
        $stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $brand = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$brand) {
            throw new Exception("Brand non trovato.");
        }
        $reporter_profile_id = $brand['id'];
        $reporter_profile_type = 'brand';
        
        // Brand può segnalare solo influencer
        if ($reported_user['user_type'] !== 'influencer') {
            throw new Exception("I brand possono segnalare solo influencer.");
        }
        
    } elseif ($_SESSION['user_type'] === 'influencer') {
        // Segnalante è un influencer - deve esistere nella tabella influencers
        $stmt = $pdo->prepare("SELECT id FROM influencers WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$influencer) {
            throw new Exception("Influencer non trovato.");
        }
        $reporter_profile_id = $influencer['id'];
        $reporter_profile_type = 'influencer';
        
        // Influencer può segnalare solo brand
        if ($reported_user['user_type'] !== 'brand') {
            throw new Exception("Gli influencer possono segnalare solo brand.");
        }
    }
    
    // 3. Controlla se esiste già una segnalazione recente per lo stesso utente dallo stesso segnalante
    $stmt = $pdo->prepare("
        SELECT id 
        FROM user_reports 
        WHERE reporter_id = ? 
        AND reported_user_id = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id'], $reported_user_id]);
    $existing_report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_report) {
        throw new Exception("Hai già segnalato questo utente nelle ultime 24 ore.");
    }
    
    // 4. Inserisci la segnalazione
    $stmt = $pdo->prepare("
        INSERT INTO user_reports (reporter_id, reporter_type, reported_user_id, reason, status, created_at) 
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_type'], $reported_user_id, $reason]);
    
    $report_id = $pdo->lastInsertId();
    
    // 5. Crea notifica per gli amministratori (se la funzione esiste)
    if (function_exists('create_notification')) {
        $admins = $pdo->query("SELECT id FROM users WHERE user_type = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($admins as $admin) {
            $reporter_type_display = ($_SESSION['user_type'] === 'brand') ? 'un brand' : 'un influencer';
            $reported_type_display = ($reported_user['user_type'] === 'brand') ? 'brand' : 'influencer';
            
            create_notification(
                $pdo,
                $admin['id'],
                'admin',
                'Nuova Segnalazione Utente',
                "{$reporter_type_display} ha segnalato un {$reported_type_display}. Segnalazione ID: #{$report_id}",
                'warning',
                '/admin/reports.php'
            );
        }
    }
    
    // Commit transazione
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Segnalazione inviata con successo. Il nostro supporto la esaminerà al più presto.',
        'report_id' => $report_id
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Errore segnalazione utente: " . $e->getMessage());
    
    // MODIFICATO: Separare il messaggio di errore con un punto invece dei due punti
    echo json_encode([
        'success' => false,
        'message' => 'Errore durante l\'invio della segnalazione. ' . $e->getMessage()
    ]);
}
?>