<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';

// Verifica permessi admin
checkAdminLogin();

// Verifica che sia una richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metodo non consentito');
}

// Verifica CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit('Token di sicurezza non valido');
}

// Verifica che l'ID documento sia stato fornito
if (!isset($_POST['document_id']) || empty($_POST['document_id'])) {
    http_response_code(400);
    exit('ID documento non valido');
}

$document_id = intval($_POST['document_id']);

try {
    // Recupera informazioni sul documento
    global $pdo;
    $sql = "SELECT cpd.*, cpr.campaign_id 
            FROM campaign_pause_documents cpd 
            JOIN campaign_pause_requests cpr ON cpd.pause_request_id = cpr.id 
            WHERE cpd.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$document_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        http_response_code(404);
        exit('Documento non trovato');
    }

    // FIX: Eliminazione file fisico
    $base_upload_path = $_SERVER['DOCUMENT_ROOT'] . '/uploads/pause_documents/';
    $file_name = basename($document['file_path']);
    $file_path = $base_upload_path . $file_name;
    
    // Verifica se il file esiste e eliminalo
    if (file_exists($file_path)) {
        if (unlink($file_path)) {
            error_log("File eliminato con successo: " . $file_path);
        } else {
            error_log("Errore nell'eliminazione del file: " . $file_path);
        }
    } else {
        // Prova percorso alternativo
        $alt_path = $_SERVER['DOCUMENT_ROOT'] . $document['file_path'];
        if (file_exists($alt_path)) {
            if (unlink($alt_path)) {
                error_log("File eliminato con successo (percorso alternativo): " . $alt_path);
            } else {
                error_log("Errore nell'eliminazione del file (percorso alternativo): " . $alt_path);
            }
        } else {
            error_log("File non trovato, procedo con eliminazione record DB. Percorsi provati: " . $file_path . " e " . $alt_path);
        }
    }

    // Elimina il record dal database
    $delete_sql = "DELETE FROM campaign_pause_documents WHERE id = ?";
    $delete_stmt = $pdo->prepare($delete_sql);
    $delete_success = $delete_stmt->execute([$document_id]);

    if ($delete_success) {
        // Log dell'azione
        logAdminAction($_SESSION['admin_id'], 
            "Eliminato documento: {$document['original_name']} (ID: $document_id) dalla richiesta pausa campagna ID: {$document['campaign_id']}");

        // Reindirizza alla pagina della campagna
        if (isset($_POST['redirect_to'])) {
            header('Location: ' . $_POST['redirect_to']);
        } else {
            header('Location: brand-campaigns.php?action=edit&id=' . $document['campaign_id']);
        }
        exit;
    } else {
        http_response_code(500);
        exit('Errore durante l\'eliminazione del documento dal database');
    }

} catch (PDOException $e) {
    error_log("Errore eliminazione documento: " . $e->getMessage());
    http_response_code(500);
    exit('Errore del server durante l\'eliminazione');
}
?>