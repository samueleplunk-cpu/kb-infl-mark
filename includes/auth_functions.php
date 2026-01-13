<?php

/**
 * Verifica se l'utente è loggato
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && !is_session_expired();
}

/**
 * Verifica se l'utente è un influencer
 */
function is_influencer() {
    return is_logged_in() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'influencer';
}

/**
 * Verifica se l'utente è un brand
 */
function is_brand() {
    return is_logged_in() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'brand';
}

/**
 * Reindirizza utente non loggato alla login
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: /auth/login.php");
        exit();
    }
}

/**
 * Reindirizza utente non influencer
 */
function require_influencer() {
    require_login();
    if (!is_influencer()) {
        header("Location: /auth/access-denied.php");
        exit();
    }
}

/**
 * Reindirizza utente non brand
 */
function require_brand() {
    require_login();
    if (!is_brand()) {
        header("Location: /auth/access-denied.php");
        exit();
    }
}

/**
 * Login utente con sessione di 14 giorni
 */
function login_user($user_id, $user_type, $username = '') {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_type'] = $user_type;
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();
}

/**
 * Logout utente
 */
function logout_user() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Verifica se la sessione è scaduta (14 giorni)
 */
function is_session_expired() {
    if (!isset($_SESSION['login_time'])) {
        return true;
    }
    
    $current_time = time();
    $login_time = $_SESSION['login_time'];
    
    // Durata fissa della sessione: 14 giorni (1209600 secondi)
    $session_duration = 1209600;
    
    return ($current_time - $login_time) > $session_duration;
}

/**
 * Controlla e gestisce la scadenza della sessione
 */
function check_session_expiry() {
    if (isset($_SESSION['user_id']) && is_session_expired()) {
        // Sessione scaduta, effettua il logout
        logout_user();
        header("Location: /auth/login.php?timeout=1");
        exit();
    }
}

/**
 * Verifica timeout sessione (8 ore) - mantenuto per compatibilità
 * @deprecated Usare check_session_expiry() invece
 */
function check_session_timeout() {
    // Delegato alla nuova funzione per mantenere compatibilità
    check_session_expiry();
}
?>