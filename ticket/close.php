<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once __DIR__ . '/includes/ticket_functions.php';

// Verifica login
if (!is_logged_in()) {
    header("Location: /auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$ticket_id = intval($_GET['id'] ?? 0);
$new_status = $_GET['status'] ?? 'closed';

// Verifica accesso al ticket
if (!$ticket_id || !can_access_ticket($ticket_id, $user_id, $user_type)) {
    header("Location: index.php?error=access_denied");
    exit();
}

// Verifica che lo stato sia valido
$allowed_statuses = ['closed', 'resolved', 'open'];
if (!in_array($new_status, $allowed_statuses)) {
    $new_status = 'closed';
}

// Aggiorna lo stato del ticket
if (update_ticket_status($ticket_id, $new_status)) {
    // Reindirizza alla view del ticket
    header("Location: view.php?id=" . $ticket_id . "&success=status_updated");
} else {
    header("Location: view.php?id=" . $ticket_id . "&error=update_failed");
}
exit();
?>