<?php
/**
 * Gestisce la nascosta temporanea delle comunicazioni admin
 */
session_start();

// Configurazione di base
require_once dirname(__DIR__) . '/includes/config.php';

// Verifica che sia una richiesta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

// Verifica che l'utente sia autenticato
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

// Verifica che ci sia un ID comunicazione
$comm_id = $_POST['comm_id'] ?? 0;
if (!$comm_id || !is_numeric($comm_id)) {
    http_response_code(400);
    exit();
}

// Inizializza l'array delle comunicazioni nascoste se non esiste
if (!isset($_SESSION['hidden_admin_comms'])) {
    $_SESSION['hidden_admin_comms'] = [];
}

// Aggiungi l'ID alla lista delle comunicazioni nascoste
if (!in_array($comm_id, $_SESSION['hidden_admin_comms'])) {
    $_SESSION['hidden_admin_comms'][] = $comm_id;
}

// Limita a 100 comunicazioni nascoste (per evitare sessioni troppo grandi)
if (count($_SESSION['hidden_admin_comms']) > 100) {
    $_SESSION['hidden_admin_comms'] = array_slice($_SESSION['hidden_admin_comms'], -100);
}

// Risposta di successo
http_response_code(200);
echo json_encode(['success' => true]);
?>