<?php
// includes/functions.php

/**
 * Sanitizza l'output per prevenire XSS
 */
function sanitize_output($data) {
    if (is_array($data)) {
        return array_map('sanitize_output', $data);
    }
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Reindirizza a una pagina
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Formatta i numeri in formato leggibile (1.5K, 2.3M)
 */
function format_number($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    return $number;
}

/**
 * Ottiene il nome della categoria dal ID
 */
function get_category_name($category_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch();
        return $category ? $category['name'] : 'Sconosciuto';
    } catch (PDOException $e) {
        error_log("Error getting category name: " . $e->getMessage());
        return 'Sconosciuto';
    }
}

/**
 * Genera un slug da una stringa
 */
function generate_slug($string) {
    $slug = preg_replace('/[^a-zA-Z0-9]/', '-', $string);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return strtolower($slug);
}

/**
 * Valida un'email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Hash della password
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifica la password
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Debug function - mostra dati in formato leggibile
 */
function debug($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

/**
 * Ottiene l'URL base dell'applicazione
 */
function base_url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    return $protocol . '://' . $host . $base . $path;
}

/**
 * Mostra messaggi di alert
 */
function display_alert($type = 'info', $message = '') {
    if (!empty($message)) {
        $class = 'alert-' . $type;
        return '<div class="alert ' . $class . '">' . sanitize_output($message) . '</div>';
    }
    return '';
}

/**
 * Crea o recupera una conversazione tra brand e influencer
 */
function startConversation($pdo, $brand_id, $influencer_id, $campaign_id = null, $initial_message = null) {
    try {
        // Verifica se esiste già una conversazione
        if ($campaign_id) {
            $stmt = $pdo->prepare("
                SELECT id FROM conversations 
                WHERE brand_id = ? AND influencer_id = ? AND campaign_id = ?
            ");
            $stmt->execute([$brand_id, $influencer_id, $campaign_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id FROM conversations 
                WHERE brand_id = ? AND influencer_id = ? AND campaign_id IS NULL
            ");
            $stmt->execute([$brand_id, $influencer_id]);
        }
        
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Verifica se l'influencer ha rifiutato questa campagna
            if ($campaign_id) {
                $reject_stmt = $pdo->prepare("
                    SELECT status 
                    FROM campaign_influencers 
                    WHERE campaign_id = ? AND influencer_id = ? AND status = 'rejected'
                ");
                $reject_stmt->execute([$campaign_id, $influencer_id]);
                
                if ($reject_stmt->rowCount() > 0) {
                    // L'influencer ha rifiutato questa campagna
                    return false;
                }
            }
            return $existing['id']; // Restituisce ID conversazione esistente
        }
        
        // Verifica se l'influencer ha già rifiutato questa campagna (per nuove conversazioni)
        if ($campaign_id) {
            $reject_stmt = $pdo->prepare("
                SELECT status 
                FROM campaign_influencers 
                WHERE campaign_id = ? AND influencer_id = ? AND status = 'rejected'
            ");
            $reject_stmt->execute([$campaign_id, $influencer_id]);
            
            if ($reject_stmt->rowCount() > 0) {
                // L'influencer ha già rifiutato questa campagna, non creare nuova conversazione
                return false;
            }
        }
        
        // Crea nuova conversazione
        $stmt = $pdo->prepare("
            INSERT INTO conversations (brand_id, influencer_id, campaign_id, created_at, updated_at) 
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$brand_id, $influencer_id, $campaign_id]);
        $conversation_id = $pdo->lastInsertId();
        
        // Aggiungi messaggio iniziale se fornito
        if ($initial_message && $conversation_id) {
            $stmt = $pdo->prepare("
                INSERT INTO messages (conversation_id, sender_id, sender_type, message, sent_at) 
                VALUES (?, ?, 'brand', ?, NOW())
            ");
            $stmt->execute([$conversation_id, $brand_id, $initial_message]);
        }
        
        return $conversation_id;
        
    } catch (PDOException $e) {
        error_log("Errore creazione conversazione: " . $e->getMessage());
        return false;
    }
}

/**
 * Segna i messaggi come letti quando un utente visualizza una conversazione
 */
function mark_messages_as_read($pdo, $conversation_id, $user_type, $user_id) {
    try {
        if ($user_type === 'brand') {
            // Per i brand: segna come letti i messaggi degli influencer
            $stmt = $pdo->prepare("
                UPDATE messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN brands b ON c.brand_id = b.id
                SET m.is_read = TRUE
                WHERE c.id = ? AND b.user_id = ? AND m.sender_type = 'influencer' AND m.is_read = FALSE
            ");
            $stmt->execute([$conversation_id, $user_id]);
        } else if ($user_type === 'influencer') {
            // Per gli influencer: segna come letti i messaggi dei brand
            $stmt = $pdo->prepare("
                UPDATE messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN influencers i ON c.influencer_id = i.id
                SET m.is_read = TRUE
                WHERE c.id = ? AND i.user_id = ? AND m.sender_type = 'brand' AND m.is_read = FALSE
            ");
            $stmt->execute([$conversation_id, $user_id]);
        }
        return true;
    } catch (Exception $e) {
        error_log("Errore nel segnare i messaggi come letti: " . $e->getMessage());
        return false;
    }
}

/**
 * Conta i messaggi non letti per l'utente corrente
 */
function count_unread_messages($pdo, $user_id, $user_type) {
    try {
        if ($user_type === 'brand') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread 
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN brands b ON c.brand_id = b.id
                WHERE b.user_id = ? AND m.sender_type = 'influencer' AND m.is_read = FALSE
            ");
            $stmt->execute([$user_id]);
        } else if ($user_type === 'influencer') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread 
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN influencers i ON c.influencer_id = i.id
                WHERE i.user_id = ? AND m.sender_type = 'brand' AND m.is_read = FALSE
            ");
            $stmt->execute([$user_id]);
        } else {
            return 0;
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['unread'] ?? 0;
    } catch (Exception $e) {
        error_log("Errore nel conteggio messaggi non letti: " . $e->getMessage());
        return 0;
    }
}

/**
 * Ottiene il numero di messaggi non letti per una conversazione specifica
 */
function count_unread_messages_in_conversation($pdo, $conversation_id, $user_type, $user_id) {
    try {
        if ($user_type === 'brand') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread 
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN brands b ON c.brand_id = b.id
                WHERE c.id = ? AND b.user_id = ? AND m.sender_type = 'influencer' AND m.is_read = FALSE
            ");
            $stmt->execute([$conversation_id, $user_id]);
        } else if ($user_type === 'influencer') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as unread 
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                JOIN influencers i ON c.influencer_id = i.id
                WHERE c.id = ? AND i.user_id = ? AND m.sender_type = 'brand' AND m.is_read = FALSE
            ");
            $stmt->execute([$conversation_id, $user_id]);
        } else {
            return 0;
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['unread'] ?? 0;
    } catch (Exception $e) {
        error_log("Errore nel conteggio messaggi non letti per conversazione: " . $e->getMessage());
        return 0;
    }
}

/**
 * Verifica se l'utente ha accesso alla conversazione
 */
function can_access_conversation($pdo, $conversation_id, $user_type, $user_id) {
    try {
        if ($user_type === 'brand') {
            $stmt = $pdo->prepare("
                SELECT c.id, c.campaign_id, c.influencer_id
                FROM conversations c
                JOIN brands b ON c.brand_id = b.id
                WHERE c.id = ? AND b.user_id = ?
            ");
        } else if ($user_type === 'influencer') {
            $stmt = $pdo->prepare("
                SELECT c.id, c.campaign_id, c.influencer_id
                FROM conversations c
                JOIN influencers i ON c.influencer_id = i.id
                WHERE c.id = ? AND i.user_id = ?
            ");
        } else {
            return false;
        }
        
        $stmt->execute([$conversation_id, $user_id]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conversation) {
            return false;
        }
        
        // Per influencer: verifica se ha rifiutato la campagna
        if ($user_type === 'influencer' && $conversation['campaign_id']) {
            $reject_stmt = $pdo->prepare("
                SELECT status 
                FROM campaign_influencers 
                WHERE campaign_id = ? AND influencer_id = ? AND status = 'rejected'
            ");
            $reject_stmt->execute([$conversation['campaign_id'], $conversation['influencer_id']]);
            
            if ($reject_stmt->rowCount() > 0) {
                // L'influencer ha rifiutato, ma può ancora visualizzare la conversazione in sola lettura
                return true;
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Errore nella verifica accesso conversazione: " . $e->getMessage());
        return false;
    }
}

/**
 * Invia una notifica email (funzione base - da implementare con servizio email)
 */
function send_notification_email($to, $subject, $message) {
    $headers = "From: noreply@influencer-marketplace.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Formatta la data in formato italiano
 */
function format_italian_date($date_string) {
    $timestamp = strtotime($date_string);
    $months = [
        'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
        'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'
    ];
    
    $day = date('j', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);
    
    return "$day $month $year alle $time";
}

/**
 * Valida i dati di input
 */
function validate_input($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';
        $rules_array = explode('|', $rule);
        
        foreach ($rules_array as $single_rule) {
            if ($single_rule === 'required' && empty(trim($value))) {
                $errors[$field] = "Il campo $field è obbligatorio";
                break;
            }
            
            if ($single_rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "Il campo $field deve contenere un'email valida";
                break;
            }
            
            if (strpos($single_rule, 'min:') === 0) {
                $min_length = (int) str_replace('min:', '', $single_rule);
                if (strlen(trim($value)) < $min_length) {
                    $errors[$field] = "Il campo $field deve essere lungo almeno $min_length caratteri";
                    break;
                }
            }
            
            if (strpos($single_rule, 'max:') === 0) {
                $max_length = (int) str_replace('max:', '', $single_rule);
                if (strlen(trim($value)) > $max_length) {
                    $errors[$field] = "Il campo $field non può superare $max_length caratteri";
                    break;
                }
            }
        }
    }
    
    return $errors;
}

/**
 * Carica un file con controlli di sicurezza
 */
function upload_file($file, $allowed_types, $max_size, $upload_path) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Errore nel caricamento del file: " . $file['error'];
        return ['success' => false, 'errors' => $errors];
    }
    
    if ($file['size'] > $max_size) {
        $errors[] = "Il file è troppo grande. Dimensione massima: " . ($max_size / 1024 / 1024) . "MB";
        return ['success' => false, 'errors' => $errors];
    }
    
    $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_type, $allowed_types)) {
        $errors[] = "Tipo file non consentito. Tipi consentiti: " . implode(', ', $allowed_types);
        return ['success' => false, 'errors' => $errors];
    }
    
    $filename = uniqid() . '_' . time() . '.' . $file_type;
    $filepath = $upload_path . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath
        ];
    } else {
        $errors[] = "Errore nel salvataggio del file";
        return ['success' => false, 'errors' => $errors];
    }
}

/**
 * Genera un token CSRF
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica un token CSRF
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Pulizia input per prevenire SQL injection
 */
function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    return trim(stripslashes(htmlspecialchars($data ?? '')));
}

/**
 * Restituisce il percorso del placeholder in base al tipo
 */
function get_placeholder_path($type) {
    $base_url = '/assets/img/';
    
    switch ($type) {
        case 'brand':
            return $base_url . 'brand-placeholder.png';
        case 'influencer':
            return $base_url . 'influencer-placeholder.png';
        default:
            return $base_url . 'user-placeholder.png';
    }
}

/**
 * Ottiene il percorso corretto per un'immagine
 */
function get_image_path($filename, $default_type = 'user') {
    if (empty($filename)) {
        return get_placeholder_path($default_type);
    }
    
    $base_path = dirname(__DIR__) . '/';
    
    if (strpos($filename, 'uploads/') === 0) {
        $possible_paths[] = $base_path . $filename;
    }
    
    if ($default_type === 'brand') {
        $possible_paths[] = $base_path . 'uploads/brands/' . $filename;
        $possible_paths[] = $base_path . 'brands/uploads/' . $filename;
    } elseif ($default_type === 'influencer') {
        $possible_paths[] = $base_path . 'uploads/influencers/' . $filename;
        $possible_paths[] = $base_path . 'uploads/profiles/' . $filename;
        $possible_paths[] = $base_path . 'influencers/uploads/' . $filename;
    }
    
    $possible_paths[] = $base_path . 'uploads/' . $filename;
    $possible_paths[] = $base_path . $filename;
    
    foreach ($possible_paths as $full_path) {
        if (file_exists($full_path) && is_file($full_path)) {
            $web_path = str_replace($base_path, '/', $full_path);
            return $web_path;
        }
    }
    
    return get_placeholder_path($default_type);
}

/**
 * Verifica se un file immagine esiste
 */
function image_exists($filename) {
    if (empty($filename)) {
        return false;
    }
    
    $base_path = dirname(__DIR__) . '/';
    
    $possible_paths = [];
    
    if (strpos($filename, 'uploads/') === 0) {
        $possible_paths[] = $base_path . $filename;
    }
    
    $possible_paths[] = $base_path . 'uploads/brands/' . $filename;
    $possible_paths[] = $base_path . 'brands/uploads/' . $filename;
    
    $possible_paths[] = $base_path . 'uploads/influencers/' . $filename;
    $possible_paths[] = $base_path . 'uploads/profiles/' . $filename;
    
    $possible_paths[] = $base_path . 'uploads/' . $filename;
    $possible_paths[] = $base_path . $filename;
    
    foreach ($possible_paths as $full_path) {
        if (file_exists($full_path) && is_file($full_path)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Ottiene l'ID del brand associato all'utente corrente
 */
function get_brand_id($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $brand = $stmt->fetch(PDO::FETCH_ASSOC);
        return $brand ? $brand['id'] : null;
    } catch (Exception $e) {
        error_log("Errore nel recupero brand ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Ottiene l'ID dell'influencer associato all'utente corrente
 */
function get_influencer_id($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM influencers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
        return $influencer ? $influencer['id'] : null;
    } catch (Exception $e) {
        error_log("Errore nel recupero influencer ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Verifica se l'utente corrente è un amministratore
 * Controlla sia la sessione che il ruolo nella tabella users
 */
function is_admin() {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        return false;
    }
    
    if ($_SESSION['user_type'] !== 'admin') {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user && isset($user['role']) && $user['role'] === 'admin';
        
    } catch (PDOException $e) {
        error_log("Errore verifica ruolo admin per user_id {$_SESSION['user_id']}: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottiene le impostazioni del sito
 */
function get_site_settings($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return $settings;
    } catch (PDOException $e) {
        error_log("Errore recupero impostazioni sito: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica se il sito è in manutenzione
 */
function is_site_in_maintenance($pdo) {
    $settings = get_site_settings($pdo);
    return isset($settings['maintenance_mode']) && $settings['maintenance_mode'] === '1';
}

/**
 * Ottiene statistiche della piattaforma per la dashboard admin
 */
function get_platform_stats($pdo) {
    try {
        $stats = [];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL");
        $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM influencers i JOIN users u ON i.user_id = u.id WHERE u.deleted_at IS NULL");
        $stats['total_influencers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM brands b JOIN users u ON b.user_id = u.id WHERE u.deleted_at IS NULL");
        $stats['total_brands'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = CURDATE() AND deleted_at IS NULL");
        $stats['new_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE suspended = 1 AND deleted_at IS NULL");
        $stats['suspended_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Errore recupero statistiche piattaforma: " . $e->getMessage());
        return [
            'total_users' => 0,
            'total_influencers' => 0,
            'total_brands' => 0,
            'new_today' => 0,
            'suspended_users' => 0
        ];
    }
}

/**
 * Esegue pulizia automatica degli utenti soft deleted
 */
function cleanup_soft_deleted_users() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            DELETE FROM users 
            WHERE deleted_at IS NOT NULL 
            AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $deleted_count = $stmt->rowCount();
        
        if ($deleted_count > 0) {
            error_log("Pulizia automatica: eliminati $deleted_count utenti soft deleted");
        }
        
        return $deleted_count;
    } catch (PDOException $e) {
        error_log("Errore pulizia utenti soft deleted: " . $e->getMessage());
        return 0;
    }
}

/**
 * Verifica se l'utente ha i permessi per accedere alla risorsa
 */
function has_permission($required_type, $user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    if (!isset($_SESSION['user_type']) || $_SESSION['user_id'] !== $user_id) {
        return false;
    }
    
    if ($_SESSION['user_type'] === 'admin') {
        return true;
    }
    
    return $_SESSION['user_type'] === $required_type;
}

/**
 * Logga le attività importanti
 */
function log_activity($pdo, $user_id, $action, $details = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->execute([
            $user_id,
            $action,
            $details ? json_encode($details) : null,
            $ip_address,
            $user_agent
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Errore logging attività: " . $e->getMessage());
        return false;
    }
}

/**
 * Formatta i byte in formato leggibile
 */
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Genera un codice casuale
 */
function generate_random_code($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $code;
}

/**
 * Valida un URL
 */
function validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Estrae il dominio da un URL
 */
function extract_domain($url) {
    $parsed = parse_url($url);
    return $parsed['host'] ?? null;
}

/**
 * Verifica se l'email è disposable/temporanea
 */
function is_disposable_email($email) {
    $disposable_domains = [
        'tempmail.com', 'guerrillamail.com', 'mailinator.com', '10minutemail.com',
        'yopmail.com', 'throwawaymail.com', 'fakeinbox.com', 'temp-mail.org'
    ];
    
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    return in_array($domain, $disposable_domains);
}

/**
 * Ottiene l'età da una data di nascita
 */
function get_age_from_birthdate($birthdate) {
    $birthday = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birthday);
    return $age->y;
}

/**
 * Calcola la percentuale
 */
function calculate_percentage($part, $total) {
    if ($total == 0) {
        return 0;
    }
    return round(($part / $total) * 100, 2);
}

/**
 * Invia notifica push (placeholder per implementazione futura)
 */
function send_push_notification($user_id, $title, $message, $data = []) {
    error_log("Push notification for user $user_id: $title - $message");
    return true;
}

/**
 * Verifica se esiste già una conversazione tra brand e influencer
 * Restituisce l'ID della conversazione se esiste, altrimenti false
 */
function conversationExists($pdo, $brand_id, $influencer_id, $campaign_id = null) {
    try {
        if ($campaign_id) {
            $stmt = $pdo->prepare("
                SELECT id FROM conversations 
                WHERE brand_id = ? AND influencer_id = ? AND campaign_id = ?
            ");
            $stmt->execute([$brand_id, $influencer_id, $campaign_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id FROM conversations 
                WHERE brand_id = ? AND influencer_id = ? AND campaign_id IS NULL
            ");
            $stmt->execute([$brand_id, $influencer_id]);
        }
        
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conversation && $campaign_id) {
            // Verifica se l'influencer ha rifiutato questa campagna
            $reject_stmt = $pdo->prepare("
                SELECT status 
                FROM campaign_influencers 
                WHERE campaign_id = ? AND influencer_id = ? AND status = 'rejected'
            ");
            $reject_stmt->execute([$campaign_id, $influencer_id]);
            
            if ($reject_stmt->rowCount() > 0) {
                // L'influencer ha rifiutato questa campagna
                return false;
            }
        }
        
        return $conversation ? $conversation['id'] : false;
        
    } catch (PDOException $e) {
        error_log("Errore verifica conversazione esistente: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottiene tutte le conversazioni esistenti per un brand con una lista di influencer
 */
function getExistingConversationsForBrand($pdo, $brand_id, $influencer_ids) {
    try {
        if (empty($influencer_ids)) {
            return [];
        }
        
        // Crea i placeholder per la query
        $placeholders = implode(',', array_fill(0, count($influencer_ids), '?'));
        
        $stmt = $pdo->prepare("
            SELECT influencer_id, id as conversation_id 
            FROM conversations 
            WHERE brand_id = ? 
            AND influencer_id IN ($placeholders)
            AND campaign_id IS NULL
        ");
        
        // Parametri: brand_id + tutti gli influencer_ids
        $params = array_merge([$brand_id], $influencer_ids);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Trasforma in array associativo influencer_id => conversation_id
        $conversations = [];
        foreach ($results as $row) {
            $conversations[$row['influencer_id']] = $row['conversation_id'];
        }
        
        return $conversations;
        
    } catch (PDOException $e) {
        error_log("Errore recupero conversazioni esistenti: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica se un influencer ha rifiutato una campagna specifica
 */
function hasInfluencerRejectedCampaign($pdo, $campaign_id, $influencer_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT status 
            FROM campaign_influencers 
            WHERE campaign_id = ? AND influencer_id = ? AND status = 'rejected'
        ");
        $stmt->execute([$campaign_id, $influencer_id]);
        
        return $stmt->rowCount() > 0;
        
    } catch (PDOException $e) {
        error_log("Errore verifica rifiuto campagna: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se un influencer può essere invitato a una campagna
 */
function canInviteInfluencerToCampaign($pdo, $campaign_id, $influencer_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT status 
            FROM campaign_influencers 
            WHERE campaign_id = ? AND influencer_id = ?
        ");
        $stmt->execute([$campaign_id, $influencer_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se non esiste record, può essere invitato
        if (!$record) {
            return true;
        }
        
        // Se esiste ma è 'rejected', non può essere reinvitato
        if ($record['status'] === 'rejected') {
            return false;
        }
        
        // Per altri stati, verifica la logica specifica
        $blocked_states = ['rejected'];
        return !in_array($record['status'], $blocked_states);
        
    } catch (PDOException $e) {
        error_log("Errore verifica possibilità invito: " . $e->getMessage());
        return false;
    }
}