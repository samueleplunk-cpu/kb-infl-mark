<?php

/**
 * Ottiene tutti gli amministratori
 */
function getAllAdmins() {
    global $pdo;
    
    try {
        $sql = "SELECT id, username, email, full_name, is_super_admin, is_active, last_login, created_at 
                FROM admins 
                ORDER BY is_super_admin DESC, username ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Errore recupero admin: " . $e->getMessage());
        return [];
    }
}

/**
 * Ottiene un admin per ID
 */
function getAdminById($admin_id) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM admins WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$admin_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Errore recupero admin by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Verifica se username esiste già
 */
function adminUsernameExists($username, $exclude_id = null) {
    global $pdo;
    
    try {
        if ($exclude_id) {
            $sql = "SELECT COUNT(*) FROM admins WHERE username = ? AND id != ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username, $exclude_id]);
        } else {
            $sql = "SELECT COUNT(*) FROM admins WHERE username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$username]);
        }
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Errore verifica username admin: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se email esiste già
 */
function adminEmailExists($email, $exclude_id = null) {
    global $pdo;
    
    try {
        if ($exclude_id) {
            $sql = "SELECT COUNT(*) FROM admins WHERE email = ? AND id != ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email, $exclude_id]);
        } else {
            $sql = "SELECT COUNT(*) FROM admins WHERE email = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
        }
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Errore verifica email admin: " . $e->getMessage());
        return false;
    }
}

/**
 * Aggiunge un nuovo admin
 */
function handleAddAdmin() {
    global $pdo;
    
    if ($_POST['action'] !== 'add') {
        return;
    }
    
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $is_super_admin = isset($_POST['is_super_admin']) ? 1 : 0;
    
    // Validazioni
    if (empty($username) || empty($email) || empty($password)) {
        $_SESSION['error_message'] = "Tutti i campi obbligatori devono essere compilati.";
        return;
    }
    
    if ($password !== $confirm_password) {
        $_SESSION['error_message'] = "Le password non coincidono.";
        return;
    }
    
    if (strlen($password) < 6) {
        $_SESSION['error_message'] = "La password deve essere di almeno 6 caratteri.";
        return;
    }
    
    if (adminUsernameExists($username)) {
        $_SESSION['error_message'] = "Username già esistente.";
        return;
    }
    
    if (adminEmailExists($email)) {
        $_SESSION['error_message'] = "Email già esistente.";
        return;
    }
    
    try {
        $sql = "INSERT INTO admins (username, email, full_name, password, is_super_admin, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $success = $stmt->execute([$username, $email, $full_name, $password_hash, $is_super_admin]);
        
        if ($success) {
            $_SESSION['success_message'] = "Admin creato con successo.";
            header("Location: users.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Errore durante la creazione dell'admin.";
        }
    } catch (PDOException $e) {
        error_log("Errore creazione admin: " . $e->getMessage());
        $_SESSION['error_message'] = "Errore di sistema durante la creazione dell'admin.";
    }
}

/**
 * Modifica un admin esistente
 */
function handleEditAdmin() {
    global $pdo;
    
    if ($_POST['action'] !== 'edit') {
        return;
    }
    
    $admin_id = intval($_POST['admin_id']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name'] ?? '');
    $is_super_admin = isset($_POST['is_super_admin']) ? 1 : 0;
    
    // Validazioni
    if (empty($username) || empty($email)) {
        $_SESSION['error_message'] = "Username ed email sono obbligatori.";
        return;
    }
    
    // Verifica che l'admin esista
    $existing_admin = getAdminById($admin_id);
    if (!$existing_admin) {
        $_SESSION['error_message'] = "Admin non trovato.";
        return;
    }
    
    // Verifica che non si stia modificando se stessi per rimuovere i privilegi di super admin
    if ($admin_id == $_SESSION['admin_id'] && !$is_super_admin) {
        $_SESSION['error_message'] = "Non puoi rimuovere i tuoi privilegi di Super Admin.";
        return;
    }
    
    if (adminUsernameExists($username, $admin_id)) {
        $_SESSION['error_message'] = "Username già esistente.";
        return;
    }
    
    if (adminEmailExists($email, $admin_id)) {
        $_SESSION['error_message'] = "Email già esistente.";
        return;
    }
    
    try {
        $sql = "UPDATE admins SET username = ?, email = ?, full_name = ?, is_super_admin = ?, updated_at = NOW() 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([$username, $email, $full_name, $is_super_admin, $admin_id]);
        
        if ($success) {
            $_SESSION['success_message'] = "Admin modificato con successo.";
            header("Location: users.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Errore durante la modifica dell'admin.";
        }
    } catch (PDOException $e) {
        error_log("Errore modifica admin: " . $e->getMessage());
        $_SESSION['error_message'] = "Errore di sistema durante la modifica dell'admin.";
    }
}

/**
 * Cambia password admin
 */
function handleChangePassword() {
    global $pdo;
    
    if ($_POST['action'] !== 'change_password') {
        return;
    }
    
    $admin_id = intval($_POST['admin_id']);
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];
    
    // Validazioni
    if (empty($new_password)) {
        $_SESSION['error_message'] = "La nuova password è obbligatoria.";
        return;
    }
    
    if ($new_password !== $confirm_new_password) {
        $_SESSION['error_message'] = "Le password non coincidono.";
        return;
    }
    
    if (strlen($new_password) < 6) {
        $_SESSION['error_message'] = "La password deve essere di almeno 6 caratteri.";
        return;
    }
    
    // Verifica che l'admin esista
    $existing_admin = getAdminById($admin_id);
    if (!$existing_admin) {
        $_SESSION['error_message'] = "Admin non trovato.";
        return;
    }
    
    try {
        $sql = "UPDATE admins SET password = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $success = $stmt->execute([$password_hash, $admin_id]);
        
        if ($success) {
            $_SESSION['success_message'] = "Password cambiata con successo.";
            header("Location: users.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Errore durante il cambio password.";
        }
    } catch (PDOException $e) {
        error_log("Errore cambio password admin: " . $e->getMessage());
        $_SESSION['error_message'] = "Errore di sistema durante il cambio password.";
    }
}

/**
 * Elimina un admin (HARD DELETE - ELIMINAZIONE DEFINITIVA)
 */
function handleDeleteAdmin() {
    global $pdo;
    
    if ($_POST['action'] !== 'delete') {
        return;
    }
    
    $admin_id = intval($_POST['admin_id']);
    
    // Verifica che non si stia eliminando se stessi
    if ($admin_id == $_SESSION['admin_id']) {
        $_SESSION['error_message'] = "Non puoi eliminare il tuo account.";
        return;
    }
    
    // Verifica che l'admin esista
    $existing_admin = getAdminById($admin_id);
    if (!$existing_admin) {
        $_SESSION['error_message'] = "Admin non trovato.";
        return;
    }
    
    // CONFERMA ELIMINAZIONE DEFINITIVA
    $username = $existing_admin['username'];
    
    try {
        // HARD DELETE - ELIMINAZIONE DEFINITIVA DAL DATABASE
        $sql = "DELETE FROM admins WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([$admin_id]);
        
        if ($success) {
            $_SESSION['success_message'] = "Admin '$username' eliminato definitivamente dal sistema.";
            header("Location: users.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Errore durante l'eliminazione definitiva dell'admin.";
        }
    } catch (PDOException $e) {
        error_log("Errore eliminazione definitiva admin: " . $e->getMessage());
        $_SESSION['error_message'] = "Errore di sistema durante l'eliminazione definitiva dell'admin.";
    }
}

/**
 * Attiva/Disattiva admin
 */
function handleToggleAdminStatus() {
    global $pdo;
    
    if ($_POST['action'] !== 'toggle_status') {
        return;
    }
    
    $admin_id = intval($_POST['admin_id']);
    $status_action = $_POST['status_action']; // 'activate' or 'deactivate'
    
    // Verifica che non si stia modificando se stessi
    if ($admin_id == $_SESSION['admin_id']) {
        $_SESSION['error_message'] = "Non puoi modificare il tuo account.";
        return;
    }
    
    // Verifica che l'admin esista
    $existing_admin = getAdminById($admin_id);
    if (!$existing_admin) {
        $_SESSION['error_message'] = "Admin non trovato.";
        return;
    }
    
    $new_status = $status_action === 'activate' ? 1 : 0;
    
    try {
        $sql = "UPDATE admins SET is_active = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([$new_status, $admin_id]);
        
        if ($success) {
            $action_text = $status_action === 'activate' ? 'attivato' : 'disattivato';
            $_SESSION['success_message'] = "Admin {$action_text} con successo.";
            header("Location: users.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Errore durante la modifica dello stato dell'admin.";
        }
    } catch (PDOException $e) {
        error_log("Errore modifica stato admin: " . $e->getMessage());
        $_SESSION['error_message'] = "Errore di sistema durante la modifica dello stato dell'admin.";
    }
}

// Gestione azione change_password
if ($_POST['action'] === 'change_password') {
    handleChangePassword();
}
?>