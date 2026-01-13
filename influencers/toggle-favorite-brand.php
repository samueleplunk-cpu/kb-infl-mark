<?php
// toggle-favorite-brand.php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json');

// Verifica autenticazione
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'influencer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato. Per favore, accedi come influencer.']);
    exit;
}

// Verifica parametri
if (!isset($_POST['brand_id']) || !isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parametri mancanti.']);
    exit;
}

$brand_id = intval($_POST['brand_id']);
$action = $_POST['action'];

if ($brand_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID brand non valido.']);
    exit;
}

if (!in_array($action, ['add', 'remove'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Azione non valida.']);
    exit;
}

// Verifica che il brand esista
try {
    $stmt = $pdo->prepare("SELECT id FROM brands WHERE id = ?");
    $stmt->execute([$brand_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Brand non trovato.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore del server durante la verifica del brand.']);
    exit;
}

// Recupera influencer_id
try {
    $stmt = $pdo->prepare("SELECT id FROM influencers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $influencer_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$influencer_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Profilo influencer non trovato.']);
        exit;
    }

    $influencer_id = $influencer_data['id'];
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore del server durante il recupero dell\'influencer.']);
    exit;
}

try {
    if ($action === 'add') {
        // Verifica se già nei preferiti
        $check_stmt = $pdo->prepare("SELECT id FROM favorite_brands WHERE influencer_id = ? AND brand_id = ?");
        $check_stmt->execute([$influencer_id, $brand_id]);
        
        if ($check_stmt->fetch()) {
            echo json_encode([
                'success' => true,
                'is_favorite' => true,
                'message' => 'Brand già nei preferiti.'
            ]);
        } else {
            // Aggiungi ai preferiti
            $stmt = $pdo->prepare("INSERT INTO favorite_brands (influencer_id, brand_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$influencer_id, $brand_id]);
            
            echo json_encode([
                'success' => true,
                'is_favorite' => true,
                'message' => 'Brand aggiunto ai preferiti.'
            ]);
        }
        
    } elseif ($action === 'remove') {
        // Rimuovi dai preferiti
        $stmt = $pdo->prepare("DELETE FROM favorite_brands WHERE influencer_id = ? AND brand_id = ?");
        $stmt->execute([$influencer_id, $brand_id]);
        
        $rows_affected = $stmt->rowCount();
        
        echo json_encode([
            'success' => true,
            'is_favorite' => false,
            'message' => $rows_affected > 0 ? 'Brand rimosso dai preferiti.' : 'Brand non era nei preferiti.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Errore toggle favorite brand: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore del server durante l\'operazione.']);
}
?>