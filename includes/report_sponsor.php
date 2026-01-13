<?php
require_once 'config.php';

// Verifica se functions.php è incluso, altrimenti includilo
if (!function_exists('verify_csrf_token')) {
    require_once 'functions.php';
}

// Verifica se è una richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metodo non consentito']);
    exit();
}

// Verifica autenticazione
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'brand') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Accesso negato']);
    exit();
}

// Validazione CSRF token - CORREGGI QUESTA LINEA
if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF non valido']);
    exit();
}

// Validazione input
$sponsor_id = filter_input(INPUT_POST, 'sponsor_id', FILTER_VALIDATE_INT);
$reporter_brand_id = filter_input(INPUT_POST, 'reporter_brand_id', FILTER_VALIDATE_INT);

// SOSTITUISCI FILTER_SANITIZE_STRING (deprecato)
// $reason = trim(filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING));
$reason = trim($_POST['reason'] ?? '');
// Sanitizza manualmente
$reason = htmlspecialchars($reason, ENT_QUOTES | ENT_HTML5, 'UTF-8');

if (!$sponsor_id || !$reporter_brand_id || empty($reason)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dati mancanti o non validi']);
    exit();
}

// Limita lunghezza motivo
if (strlen($reason) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Il motivo non può superare i 1000 caratteri']);
    exit();
}

try {
    // Verifica che lo sponsor esista e sia attivo
    $stmt = $pdo->prepare("
        SELECT s.id, s.influencer_id, i.full_name as influencer_name, s.title
        FROM sponsors s
        JOIN influencers i ON s.influencer_id = i.id
        WHERE s.id = ? AND s.status = 'active' AND s.deleted_at IS NULL
    ");
    $stmt->execute([$sponsor_id]);
    $sponsor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sponsor) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sponsor non trovato o non attivo']);
        exit();
    }
    
    // Verifica che il brand esista
    $stmt = $pdo->prepare("SELECT id FROM brands WHERE id = ? AND user_id = ?");
    $stmt->execute([$reporter_brand_id, $_SESSION['user_id']]);
    $brand = $stmt->fetch();
    
    if (!$brand) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Brand non valido']);
        exit();
    }
    
    // Verifica se esiste già una segnalazione pending per questo sponsor da parte di questo brand
    $stmt = $pdo->prepare("
        SELECT id FROM sponsor_reports 
        WHERE sponsor_id = ? AND reporter_brand_id = ? AND status = 'pending'
    ");
    $stmt->execute([$sponsor_id, $reporter_brand_id]);
    $existing_report = $stmt->fetch();
    
    if ($existing_report) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Hai già una segnalazione in attesa per questo sponsor']);
        exit();
    }
    
    // Inserisci la segnalazione
    $stmt = $pdo->prepare("
        INSERT INTO sponsor_reports (sponsor_id, reporter_user_id, reporter_brand_id, reason, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$sponsor_id, $_SESSION['user_id'], $reporter_brand_id, $reason]);
    
    // Ottieni l'ID della segnalazione appena creata
    $report_id = $pdo->lastInsertId();
    
    // REGISTRA ATTIVITÀ DI LOG (solo se la tabella esiste)
    // COMMENTA O MODIFICA QUESTA SEZIONE se admin_logs non esiste
    try {
        // Verifica se la tabella esiste
        $check_table = $pdo->query("SHOW TABLES LIKE 'admin_logs'");
        if ($check_table->rowCount() > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO admin_logs (user_id, action_type, target_type, target_id, details, created_at)
                VALUES (?, 'report', 'sponsor', ?, ?, NOW())
            ");
            $details = "Segnalazione sponsor #" . $sponsor_id . " - " . $sponsor['title'];
            $stmt->execute([$_SESSION['user_id'], $sponsor_id, $details]);
        }
        // Se la tabella non esiste, salta silenziosamente
    } catch (PDOException $e) {
        // Logga l'errore ma non bloccare il flusso principale
        error_log("Errore inserimento admin_logs (tabella potrebbe non esistere): " . $e->getMessage());
    }
    
    // Potresti anche voler inviare una notifica agli admin qui
    
    echo json_encode([
        'success' => true, 
        'message' => 'Segnalazione inviata con successo. Il nostro supporto la esaminerà al più presto.',
        'report_id' => $report_id
    ]);
    
} catch (PDOException $e) {
    error_log("Errore segnalazione sponsor: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore del server. Riprova più tardi.']);
}