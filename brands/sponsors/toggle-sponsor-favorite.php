<?php

// Pulisci output buffer
while (@ob_end_clean());

// Configura errori
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Percorso config
$config_file = __DIR__ . '/../../includes/config.php';

if (!file_exists($config_file)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'System configuration error']);
    exit();
}

// Includi config
ob_start();
require_once $config_file;
ob_end_clean();

// Verifica connessione database
if (!isset($pdo)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

// Avvia sessione
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inizializza risposta
$response = ['success' => false, 'message' => '', 'is_favorite' => false];

try {
    // Verifica metodo
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Verifica parametri
    if (!isset($_POST['sponsor_id']) || empty($_POST['sponsor_id'])) {
        throw new Exception('Missing sponsor ID');
    }
    
    if (!isset($_POST['action']) || !in_array($_POST['action'], ['add', 'remove'])) {
        throw new Exception('Invalid action');
    }
    
    $sponsor_id = (int)$_POST['sponsor_id'];
    $action = $_POST['action'];
    
    if ($sponsor_id <= 0) {
        throw new Exception('Invalid sponsor ID');
    }
    
    // Verifica autenticazione
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }
    
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
        throw new Exception('Access denied');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Ottieni brand_id
    $stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brand) {
        throw new Exception('Brand profile not found');
    }
    
    $brand_id = $brand['id'];
    
    // Verifica sponsor esista e sia attivo
    $stmt = $pdo->prepare("SELECT id FROM sponsors WHERE id = ? AND status = 'active' AND deleted_at IS NULL");
    $stmt->execute([$sponsor_id]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Sponsor not found or not active');
    }
    
    // Gestione preferiti
    if ($action === 'add') {
        // Controlla se già preferito
        $stmt = $pdo->prepare("SELECT id FROM favorite_sponsors WHERE brand_id = ? AND sponsor_id = ?");
        $stmt->execute([$brand_id, $sponsor_id]);
        
        if (!$stmt->fetch()) {
            // Aggiungi ai preferiti solo se non esiste già
            $stmt = $pdo->prepare("INSERT INTO favorite_sponsors (brand_id, sponsor_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$brand_id, $sponsor_id]);
        }
        
        $response = [
            'success' => true,
            'is_favorite' => true,
            'message' => 'Sponsor aggiunto ai preferiti'
        ];
        
    } else { // action = 'remove'
        // Rimuovi dai preferiti
        $stmt = $pdo->prepare("DELETE FROM favorite_sponsors WHERE brand_id = ? AND sponsor_id = ?");
        $stmt->execute([$brand_id, $sponsor_id]);
        
        $response = [
            'success' => true,
            'is_favorite' => false,
            'message' => 'Sponsor rimosso dai preferiti'
        ];
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'is_favorite' => false
    ];
}

// Output finale
while (@ob_end_clean());
header('Content-Type: application/json');
echo json_encode($response);
exit();
?>