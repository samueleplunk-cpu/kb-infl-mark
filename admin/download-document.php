<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';

checkAdminLogin();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID documento non specificato");
}

$document_id = intval($_GET['id']);

try {
    // Recupera informazioni documento
    $sql = "SELECT cpd.*, cpr.campaign_id 
            FROM campaign_pause_documents cpd 
            JOIN campaign_pause_requests cpr ON cpd.pause_request_id = cpr.id 
            WHERE cpd.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$document_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        die("Documento non trovato");
    }
    
    $file_path = $document['file_path'];
    
    if (!file_exists($file_path)) {
        die("File non trovato sul server");
    }
    
    // Imposta headers per il download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $document['file_type']);
    header('Content-Disposition: attachment; filename="' . $document['original_name'] . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    
    // Pulisce l'output buffer e legge il file
    flush();
    readfile($file_path);
    exit;
    
} catch (PDOException $e) {
    die("Errore nel download del documento: " . $e->getMessage());
}
?>