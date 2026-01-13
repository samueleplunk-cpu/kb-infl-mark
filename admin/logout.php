<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

logout_admin();
header("Location: /auth/admin_login.php?logout=1");
exit();
?>