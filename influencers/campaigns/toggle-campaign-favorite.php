<?php
// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG
// =============================================
$config_file = dirname(dirname(dirname(__FILE__))) . '/includes/config.php';
if (!file_exists($config_file)) {
    die(json_encode(['success' => false, 'message' => 'File di configurazione non trovato']));
}
require_once $config_file;

// =============================================
// VERIFICA AUTENTICAZIONE
// =============================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'influencer') {
    die(json_encode(['success' => false, 'message' => 'Accesso negato']));
}

// =============================================
// VERIFICA PARAMETRI
// =============================================
if (!isset($_POST['campaign_id']) || !isset($_POST['action'])) {
    die(json_encode(['success' => false, 'message' => 'Parametri mancanti']));
}

$campaign_id = intval($_POST['campaign_id']);
$action = $_POST['action'];

// =============================================
// RECUPERO DATI INFLUENCER
// =============================================
try {
    $stmt = $pdo->prepare("SELECT id FROM influencers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        die(json_encode(['success' => false, 'message' => 'Profilo influencer non trovato']));
    }
    
    $influencer_id = $influencer['id'];
    
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Errore nel recupero del profilo']));
}

// =============================================
// VERIFICA ESISTENZA CAMPAGNA
// =============================================
try {
    $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$campaign_id]);
    if (!$stmt->fetch()) {
        die(json_encode(['success' => false, 'message' => 'Campagna non trovata']));
    }
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Errore nella verifica della campagna']));
}

// =============================================
// GESTIONE PREFERITI
// =============================================
try {
    if ($action === 'add') {
        // Aggiungi ai preferiti
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO favorite_campaigns (influencer_id, campaign_id, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$influencer_id, $campaign_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Campagna aggiunta ai preferiti',
            'is_favorite' => true
        ]);
        
    } elseif ($action === 'remove') {
        // Rimuovi dai preferiti
        $stmt = $pdo->prepare("
            DELETE FROM favorite_campaigns 
            WHERE influencer_id = ? AND campaign_id = ?
        ");
        $stmt->execute([$influencer_id, $campaign_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Campagna rimossa dai preferiti',
            'is_favorite' => false
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Azione non valida']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Errore del database: ' . $e->getMessage()]);
}
?>