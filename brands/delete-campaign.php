<?php
// =============================================
// CONFIGURAZIONE ERRORI
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG
// =============================================
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

// =============================================
// VERIFICA AUTENTICAZIONE
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    die("Accesso negato: Questa area è riservata ai brand.");
}

// =============================================
// VERIFICA METODO
// =============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: campaigns.php");
    exit();
}

// =============================================
// VALIDA INPUT E CSRF
// =============================================
$campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

if ($campaign_id <= 0) {
    header("Location: campaigns.php?error=invalid_id");
    exit();
}

if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
    header("Location: campaigns.php?error=csrf_invalid");
    exit();
}

// =============================================
// RECUPERA BRAND E VERIFICA PROPRIETÀ
// =============================================
try {
    // Recupera brand
    $stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brand) {
        header("Location: campaigns.php?error=brand_not_found");
        exit();
    }
    
    // Verifica che la campagna appartenga al brand
    $stmt = $pdo->prepare("SELECT id, name FROM campaigns WHERE id = ? AND brand_id = ?");
    $stmt->execute([$campaign_id, $brand['id']]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        header("Location: campaigns.php?error=campaign_not_found_or_not_owner");
        exit();
    }
    
    // =============================================
    // INIZIA TRANSAZIONE PER ELIMINAZIONE A CASCATA
    // =============================================
    $pdo->beginTransaction();
    
    try {
        // 1. Elimina tutte le richieste influencer associate (tabella campaign_influencers)
        $stmt = $pdo->prepare("DELETE FROM campaign_influencers WHERE campaign_id = ?");
        $stmt->execute([$campaign_id]);
        
        // 2. Elimina eventuali comunicazioni o messaggi associati
        // (Se esiste una tabella campaign_messages, elimina anche quelli)
        // NOTA: Questa parte è commentata perché la tabella potrebbe non esistere
        // $stmt = $pdo->prepare("DELETE FROM campaign_messages WHERE campaign_id = ?");
        // $stmt->execute([$campaign_id]);
        
        // 3. Elimina la campagna (HARD DELETE)
        $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = ?");
        $stmt->execute([$campaign_id]);
        
        // =============================================
        // CONFERMA TRANSAZIONE
        // =============================================
        $pdo->commit();
        
        // Rigenera token CSRF per prevenire replay attacks
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // Redirect con messaggio di successo
        header("Location: campaigns.php?success=campaign_deleted&campaign_name=" . urlencode($campaign['name']));
        exit();
        
    } catch (Exception $e) {
        // =============================================
        // ROLLBACK IN CASO DI ERRORE
        // =============================================
        $pdo->rollBack();
        error_log("Errore durante l'eliminazione della campagna ID $campaign_id: " . $e->getMessage());
        header("Location: campaigns.php?error=delete_failed&message=" . urlencode($e->getMessage()));
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Errore database: " . $e->getMessage());
    header("Location: campaigns.php?error=database_error");
    exit();
}
?>