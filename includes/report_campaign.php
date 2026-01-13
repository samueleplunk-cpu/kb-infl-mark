<?php
// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG CON PERCORSO ASSOLUTO
// =============================================
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die(json_encode(['success' => false, 'message' => 'Config file not found']));
}
require_once $config_file;

// =============================================
// VERIFICA SESSIONE E PERMESSI
// =============================================
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato']);
    exit;
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'influencer') {
    echo json_encode(['success' => false, 'message' => 'Accesso negato: Solo gli influencer possono segnalare campagne']);
    exit;
}

// =============================================
// VERIFICA CSRF TOKEN
// =============================================
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF non valido']);
    exit;
}

// =============================================
// VALIDAZIONE INPUT
// =============================================
$required_fields = ['campaign_id', 'reporter_influencer_id', 'reason'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        echo json_encode(['success' => false, 'message' => 'Campo mancante: ' . $field]);
        exit;
    }
}

$campaign_id = intval($_POST['campaign_id']);
$reporter_influencer_id = intval($_POST['reporter_influencer_id']);
$reason = trim($_POST['reason']);

if (strlen($reason) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Il motivo della segnalazione non può superare i 1000 caratteri']);
    exit;
}

// =============================================
// VERIFICA CHE L'INFLUENCER ESISTA E SIA CHI DICE DI ESSERE
// =============================================
try {
    // Verifica che l'influencer esista e corrisponda all'utente loggato
    $stmt = $pdo->prepare("
        SELECT i.id, i.user_id 
        FROM influencers i 
        WHERE i.id = ? AND i.user_id = ?
    ");
    $stmt->execute([$reporter_influencer_id, $_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        echo json_encode(['success' => false, 'message' => 'Profilo influencer non trovato o non autorizzato']);
        exit;
    }
    
    // Verifica che la campagna esista
    $stmt = $pdo->prepare("
        SELECT id, name, brand_id 
        FROM campaigns 
        WHERE id = ? AND is_public = TRUE
    ");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        echo json_encode(['success' => false, 'message' => 'Campagna non trovata']);
        exit;
    }
    
    // Verifica se l'influencer ha già segnalato questa campagna
    $stmt = $pdo->prepare("
        SELECT id 
        FROM campaign_reports 
        WHERE campaign_id = ? AND reporter_influencer_id = ? AND status IN ('pending', 'reviewed')
    ");
    $stmt->execute([$campaign_id, $reporter_influencer_id]);
    $existing_report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_report) {
        echo json_encode(['success' => false, 'message' => 'Hai già segnalato questa campagna. La segnalazione è in attesa di revisione.']);
        exit;
    }
    
    // Inserisce la segnalazione
    $stmt = $pdo->prepare("
        INSERT INTO campaign_reports (campaign_id, reporter_user_id, reporter_influencer_id, reason, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
        $campaign_id,
        $_SESSION['user_id'],
        $reporter_influencer_id,
        $reason
    ]);
    
    $report_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => '',
        'report_id' => $report_id
    ]);
    
} catch (PDOException $e) {
    error_log("Errore segnalazione campagna: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Errore durante l\'invio della segnalazione. Riprova più tardi.'
    ]);
}
?>