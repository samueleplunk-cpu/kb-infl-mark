<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once __DIR__ . '/ticket_functions.php';

// Solo per richieste AJAX/API
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

switch ($action) {
    case 'get_unread':
        $notifications = get_unread_notifications($user_id, $user_type);
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;
        
    case 'mark_as_read':
        $notification_id = intval($_POST['notification_id'] ?? 0);
        if ($notification_id) {
            $result = mark_notifications_as_read([$notification_id]);
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
        }
        break;
        
    case 'mark_all_read':
        $result = mark_notifications_as_read([], $user_id, $user_type);
        echo json_encode(['success' => $result]);
        break;
        
    case 'count_unread':
        $notifications = get_unread_notifications($user_id, $user_type);
        echo json_encode(['success' => true, 'count' => count($notifications)]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>