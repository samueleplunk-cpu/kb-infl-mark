<?php
// toggle-favorite.php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json');

// Verifica autenticazione
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Accesso non autorizzato. Per favore, accedi come brand.']);
    exit;
}

// Verifica parametri
if (!isset($_POST['influencer_id']) || !isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parametri mancanti.']);
    exit;
}

$influencer_id = intval($_POST['influencer_id']);
$action = $_POST['action'];

if ($influencer_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID influencer non valido.']);
    exit;
}

if (!in_array($action, ['add', 'remove'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Azione non valida.']);
    exit;
}

// Verifica che l'influencer esista
try {
    $stmt = $pdo->prepare("SELECT id FROM influencers WHERE id = ?");
    $stmt->execute([$influencer_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Influencer non trovato.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore del server durante la verifica dell\'influencer.']);
    exit;
}

// Recupera brand_id
try {
    $stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $brand_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$brand_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Profilo brand non trovato.']);
        exit;
    }

    $brand_id = $brand_data['id'];
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore del server durante il recupero del brand.']);
    exit;
}

try {
    if ($action === 'add') {
        // Verifica se già nei preferiti
        $check_stmt = $pdo->prepare("SELECT id FROM favorite_influencers WHERE brand_id = ? AND influencer_id = ?");
        $check_stmt->execute([$brand_id, $influencer_id]);
        
        if ($check_stmt->fetch()) {
            echo json_encode([
                'success' => true,
                'is_favorite' => true,
                'message' => 'Influencer già nei preferiti.'
            ]);
        } else {
            // Aggiungi ai preferiti
            $stmt = $pdo->prepare("INSERT INTO favorite_influencers (brand_id, influencer_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$brand_id, $influencer_id]);
            
            echo json_encode([
                'success' => true,
                'is_favorite' => true,
                'message' => 'Influencer aggiunto ai preferiti.'
            ]);
        }
        
    } elseif ($action === 'remove') {
        // Rimuovi dai preferiti
        $stmt = $pdo->prepare("DELETE FROM favorite_influencers WHERE brand_id = ? AND influencer_id = ?");
        $stmt->execute([$brand_id, $influencer_id]);
        
        $rows_affected = $stmt->rowCount();
        
        echo json_encode([
            'success' => true,
            'is_favorite' => false,
            'message' => $rows_affected > 0 ? 'Influencer rimosso dai preferiti.' : 'Influencer non era nei preferiti.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Errore toggle favorite: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Errore del server durante l\'operazione.']);
}
?>