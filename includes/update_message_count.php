<?php
// /includes/update_message_count.php

// Configurazione errori
error_reporting(0);
ini_set('display_errors', 0);

// Percorso assoluto per config
$config_file = dirname(__DIR__) . '/includes/config.php';

if (file_exists($config_file)) {
    require_once $config_file;
} else {
    echo json_encode(['unread_count' => 0]);
    exit();
}

// Verifica sessione
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode(['unread_count' => 0]);
    exit();
}

// Includi functions.php per usare count_unread_messages
$functions_file = dirname(__DIR__) . '/includes/functions.php';
if (file_exists($functions_file)) {
    require_once $functions_file;
} else {
    echo json_encode(['unread_count' => 0]);
    exit();
}

try {
    $unread_count = count_unread_messages($pdo, $_SESSION['user_id'], $_SESSION['user_type']);
    echo json_encode(['unread_count' => $unread_count]);
} catch (Exception $e) {
    error_log("Errore in update_message_count: " . $e->getMessage());
    echo json_encode(['unread_count' => 0]);
}
?>