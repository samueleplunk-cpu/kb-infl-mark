<?php

/**
 * Verifica se l'utente è admin loggato
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Reindirizza admin non loggato
 */
function require_admin_login() {
    if (!is_admin_logged_in()) {
        header("Location: /auth/admin_login.php");
        exit();
    }
}

/**
 * Controlla login admin e reindirizza se non loggato
 */
function checkAdminLogin() {
    if (!is_admin_logged_in()) {
        header("Location: /auth/admin_login.php");
        exit();
    }
}

/**
 * Login admin
 */
function login_admin($admin_id, $username, $is_super_admin = false) {
    $_SESSION['admin_id'] = $admin_id;
    $_SESSION['admin_username'] = $username;
    $_SESSION['is_super_admin'] = $is_super_admin;
    $_SESSION['admin_login_time'] = time();
    
    // Aggiorna ultimo login
    global $pdo;
    $stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$admin_id]);
}

/**
 * Logout admin
 */
function logout_admin() {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['is_super_admin']);
    unset($_SESSION['admin_login_time']);
}

/**
 * Verifica timeout sessione admin (4 ore)
 */
function check_admin_session_timeout() {
    $timeout = 4 * 60 * 60; // 4 ore
    if (isset($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time'] > $timeout)) {
        logout_admin();
        header("Location: /auth/admin_login.php?timeout=1");
        exit();
    }
}

/**
 * Ottiene statistiche piattaforma
 */
function get_admin_platform_stats() {
    global $pdo;
    
    $stats = [];
    
    // Conta utenti totali
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Conta influencer
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'influencer' AND deleted_at IS NULL");
    $stats['total_influencers'] = $stmt->fetchColumn();
    
    // Conta brands
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'brand' AND deleted_at IS NULL");
    $stats['total_brands'] = $stmt->fetchColumn();
    
    // Conta utenti attivi oggi
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE() AND deleted_at IS NULL");
    $stats['new_today'] = $stmt->fetchColumn();
    
    // Conta utenti sospesi
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_suspended = TRUE AND deleted_at IS NULL");
    $stats['suspended_users'] = $stmt->fetchColumn();
    
    return $stats;
}

/**
 * Soft delete utente
 */
function soft_delete_user($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
    return $stmt->execute([$user_id]);
}

/**
 * Ripristina utente
 */
function restore_user($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET deleted_at = NULL WHERE id = ?");
    return $stmt->execute([$user_id]);
}

/**
 * Sospensione temporanea utente
 */
function suspend_user($user_id, $end_date) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET is_suspended = TRUE, suspension_end = ? WHERE id = ?");
    return $stmt->execute([$end_date, $user_id]);
}

/**
 * Rimuovi sospensione utente
 */
function unsuspend_user($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET is_suspended = FALSE, suspension_end = NULL WHERE id = ?");
    return $stmt->execute([$user_id]);
}

/**
 * Blocco permanente utente
 */
function block_user($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET is_blocked = TRUE, is_suspended = FALSE, suspension_end = NULL WHERE id = ?");
    return $stmt->execute([$user_id]);
}

/**
 * Sblocco utente
 */
function unblock_user($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET is_blocked = FALSE WHERE id = ?");
    return $stmt->execute([$user_id]);
}

// =============================================================================
// FUNZIONI PER GESTIONE INFLUENCER E BRANDS
// =============================================================================

/**
 * Costruisce la stringa query per i filtri
 */
function buildQueryString($filters) {
    $params = [];
    foreach ($filters as $key => $value) {
        if (!empty($value)) {
            $params[] = $key . '=' . urlencode($value);
        }
    }
    return $params ? '&' . implode('&', $params) : '';
}

/**
 * Genera una password casuale sicura
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    // Assicura almeno una lettera maiuscola, una minuscola e un numero
    $password .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0, 25)];
    $password .= 'abcdefghijklmnopqrstuvwxyz'[random_int(0, 25)];
    $password .= '0123456789'[random_int(0, 9)];
    
    // Riempi il resto
    for ($i = 3; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    // Mescola i caratteri
    return str_shuffle($password);
}

/**
 * Ottiene gli influencer con paginazione e filtri
 */
function getInfluencers($page = 1, $per_page = 15, $filters = []) {
    global $pdo;
    
    $offset = ($page - 1) * $per_page;
    $where_conditions = ["u.user_type = 'influencer'"];
    $params = [];
    
    // Filtro per stato
    if (!empty($filters['status'])) {
        switch($filters['status']) {
            case 'active':
                $where_conditions[] = "u.is_active = 1 AND u.deleted_at IS NULL AND u.is_blocked = 0 AND (u.is_suspended = 0 OR u.suspension_end < NOW())";
                break;
            case 'suspended':
                $where_conditions[] = "u.is_suspended = 1 AND u.deleted_at IS NULL AND u.suspension_end >= NOW()";
                break;
            case 'blocked':
                $where_conditions[] = "u.is_blocked = 1 AND u.deleted_at IS NULL";
                break;
            case 'deleted':
                $where_conditions[] = "u.deleted_at IS NOT NULL";
                break;
            case 'inactive':
                $where_conditions[] = "u.is_active = 0 AND u.deleted_at IS NULL";
                break;
        }
    } else {
        // Di default mostra solo utenti non eliminati
        $where_conditions[] = "u.deleted_at IS NULL";
    }
    
    // Filtro per data
    if (!empty($filters['date_from'])) {
        $where_conditions[] = "u.created_at >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where_conditions[] = "u.created_at <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    
    // Ricerca per nome/email
    if (!empty($filters['search'])) {
        // Cerca sia in users.name che in influencers.full_name
        $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR i.full_name LIKE ?)";
        $search_term = "%{$filters['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_sql = implode(' AND ', $where_conditions);
    
    // Query per il conteggio totale - CORREZIONE: Aggiungi JOIN anche qui
    $count_sql = "SELECT COUNT(*) as total 
                  FROM users u 
                  LEFT JOIN influencers i ON u.id = i.user_id
                  WHERE $where_sql";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query per i dati con JOIN alla tabella influencers
    $sql = "SELECT u.*, 
                   COALESCE(NULLIF(i.full_name, ''), u.name) as display_name,
                   i.profile_image as influencer_avatar
            FROM users u 
            LEFT JOIN influencers i ON u.id = i.user_id
            WHERE $where_sql 
            ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $influencers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'data' => $influencers,
        'total' => $total_count,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total_count / $per_page)
    ];
}

/**
 * Ottiene un singolo influencer per ID
 */
function getInfluencerById($id) {
    global $pdo;
    
    $sql = "SELECT u.*, 
                   COALESCE(NULLIF(i.full_name, ''), u.name) as display_name,
                   i.full_name as influencer_full_name,
                   i.profile_image as influencer_avatar
            FROM users u 
            LEFT JOIN influencers i ON u.id = i.user_id
            WHERE u.id = ? AND u.user_type = 'influencer'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Valida la complessità della password
 */
function validatePasswordStrength($password) {
    if (strlen($password) < 6) {
        return "La password deve avere almeno 6 caratteri";
    }
    
    // Controlla se contiene almeno una lettera
    if (!preg_match('/[a-zA-Z]/', $password)) {
        return "La password deve contenere almeno una lettera";
    }
    
    // Controlla se contiene almeno un numero
    if (!preg_match('/[0-9]/', $password)) {
        return "La password deve contenere almeno un numero";
    }
    
    return true;
}

/**
 * Crea o aggiorna un influencer
 */
function saveInfluencer($data, $id = null) {
    global $pdo;
    
    if ($id) {
        // Update - con gestione password opzionale
        
        // PRIMA: Gestisci il nome - separa la logica per users e influencers
        $name = !empty($data['name']) ? trim($data['name']) : null;
        $email = !empty($data['email']) ? trim($data['email']) : null;
        
        if (!empty($data['password'])) {
            // Se c'è password, aggiorna tutto
            $sql = "UPDATE users SET name = ?, email = ?, password = ?, is_active = ?, updated_at = NOW() WHERE id = ?";
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$name, $email, $password_hash, $data['is_active'], $id]);
            
            if ($result) {
                // Ora aggiorna la tabella influencers se necessario
                updateInfluencerFullName($id, $name);
            }
            return $result;
        } else {
            // Se non c'è password
            $sql = "UPDATE users SET name = ?, email = ?, is_active = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$name, $email, $data['is_active'], $id]);
            
            if ($result) {
                // Aggiorna la tabella influencers se necessario
                updateInfluencerFullName($id, $name);
            }
            return $result;
        }
    } else {
        // Insert - password obbligatoria per nuovo influencer
        $sql = "INSERT INTO users (name, email, password, user_type, is_active, created_at) VALUES (?, ?, ?, 'influencer', ?, NOW())";
        
        $password = !empty($data['password']) ? $data['password'] : 'password123';
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $name = !empty($data['name']) ? trim($data['name']) : null;
        $email = !empty($data['email']) ? trim($data['email']) : null;
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$name, $email, $password_hash, $data['is_active']]);
        
        // Opzionale: log per debug
        if ($result && empty($data['password'])) {
            error_log("Nuovo influencer creato con password di default: 'password123'");
        }
        
        // Crea record nella tabella influencers con il nome
        if ($result) {
            $user_id = $pdo->lastInsertId();
            createInfluencerRecord($user_id, $name);
        }
        
        return $result;
    }
}

/**
 * Funzione helper per aggiornare full_name nella tabella influencers
 */
function updateInfluencerFullName($user_id, $name) {
    global $pdo;
    
    try {
        // Prima controlla se esiste già un record nella tabella influencers
        $check_sql = "SELECT id FROM influencers WHERE user_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$user_id]);
        $exists = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // Aggiorna il record esistente
            $update_sql = "UPDATE influencers SET full_name = ?, updated_at = NOW() WHERE user_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            return $update_stmt->execute([$name, $user_id]);
        } else {
            // Crea un nuovo record
            $insert_sql = "INSERT INTO influencers (user_id, full_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
            $insert_stmt = $pdo->prepare($insert_sql);
            return $insert_stmt->execute([$user_id, $name]);
        }
    } catch (Exception $e) {
        error_log("Errore nell'aggiornamento di influencers.full_name: " . $e->getMessage());
        return false;
    }
}

/**
 * Funzione helper per creare record nella tabella influencers per nuovi utenti
 */
function createInfluencerRecord($user_id, $full_name) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO influencers (user_id, full_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$user_id, $full_name]);
    } catch (Exception $e) {
        error_log("Errore nella creazione del record influencer: " . $e->getMessage());
        return false;
    }
}

/**
 * Gestisce lo stato di un influencer (sospensione, blocco, etc.)
 */
function updateInfluencerStatus($id, $action, $suspension_end = null) {
    global $pdo;
    
    switch($action) {
        case 'suspend':
            $sql = "UPDATE users SET is_suspended = 1, suspension_end = ?, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$suspension_end, $id]);
            
        case 'unsuspend':
            $sql = "UPDATE users SET is_suspended = 0, suspension_end = NULL, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'block':
            $sql = "UPDATE users SET is_blocked = 1, is_suspended = 0, suspension_end = NULL, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'unblock':
            $sql = "UPDATE users SET is_blocked = 0, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'delete':
            $sql = "UPDATE users SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'restore':
            $sql = "UPDATE users SET deleted_at = NULL, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        default:
            return false;
    }
}

/**
 * Elimina completamente un influencer e tutti i dati correlati
 */
function deleteInfluencerCompletely($user_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 1. Ottieni informazioni sull'influencer prima di eliminare
        $stmt = $pdo->prepare("
            SELECT u.id as user_id, u.avatar, i.id as influencer_id
            FROM users u 
            LEFT JOIN influencers i ON u.id = i.user_id 
            WHERE u.id = ? AND u.user_type = 'influencer'
        ");
        $stmt->execute([$user_id]);
        $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$influencer) {
            throw new Exception("Influencer non trovato");
        }
        
        $avatar = $influencer['avatar'];
        $influencer_id = $influencer['influencer_id'];
        
        // 2. Elimina i file immagine dal filesystem
        if (!empty($avatar)) {
            deleteInfluencerImages($avatar);
        }
        
        // 3. Elimina dalle tabelle correlate (in ordine per vincoli foreign key)
        
        if ($influencer_id) {
            // a) Elimina dalle campagne (se esiste la tabella influencers)
            try {
                $stmt = $pdo->prepare("DELETE FROM campaign_influencers WHERE influencer_id = ?");
                $stmt->execute([$influencer_id]);
            } catch (Exception $e) {
                // Tabella campaign_influencers potrebbe non esistere, ignora l'errore
                error_log("Nota: Tabella campaign_influencers non trovata o errore: " . $e->getMessage());
            }
            
            // b) Elimina matching (se esiste la tabella)
            try {
                $stmt = $pdo->prepare("DELETE FROM matching WHERE influencer_id = ?");
                $stmt->execute([$influencer_id]);
            } catch (Exception $e) {
                // Tabella matching potrebbe non esistere, ignora l'errore
                error_log("Nota: Tabella matching non trovata o errore: " . $e->getMessage());
            }
            
            // c) Elimina l'influencer dalla tabella influencers
            try {
                $stmt = $pdo->prepare("DELETE FROM influencers WHERE id = ?");
                $stmt->execute([$influencer_id]);
            } catch (Exception $e) {
                // Tabella influencers potrebbe non esistere, ignora l'errore
                error_log("Nota: Tabella influencers non trovata o errore: " . $e->getMessage());
            }
        }
        
        // d) Elimina conversazioni e messaggi
        try {
            $stmt = $pdo->prepare("SELECT id FROM conversations WHERE influencer_id = ?");
            $stmt->execute([$user_id]); // Nota: nelle conversazioni influencer_id potrebbe riferirsi a user_id
            $conversations = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($conversations)) {
                $placeholders = str_repeat('?,', count($conversations) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM messages WHERE conversation_id IN ($placeholders)");
                $stmt->execute($conversations);
                
                $stmt = $pdo->prepare("DELETE FROM conversations WHERE influencer_id = ?");
                $stmt->execute([$user_id]);
            }
        } catch (Exception $e) {
            // Tabelle conversazioni potrebbero non esistere, ignora l'errore
            error_log("Nota: Tabelle conversazioni non trovate o errore: " . $e->getMessage());
        }
        
        // e) Elimina l'utente associato (hard delete)
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Errore eliminazione completa influencer $user_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Elimina i file immagine dell'influencer dal filesystem
 */
function deleteInfluencerImages($avatar) {
    if (empty($avatar)) {
        return;
    }
    
    $base_path = $_SERVER['DOCUMENT_ROOT'] . '/';
    
    // Lista dei possibili percorsi dove potrebbe trovarsi l'immagine
    $possible_paths = [
        $base_path . 'uploads/influencers/' . $avatar,
        $base_path . 'uploads/profiles/' . $avatar,
        $base_path . 'influencers/uploads/' . $avatar,
        $base_path . 'uploads/' . $avatar,
        $base_path . $avatar
    ];
    
    foreach ($possible_paths as $file_path) {
        if (file_exists($file_path) && is_file($file_path)) {
            try {
                unlink($file_path);
                error_log("Eliminato file: $file_path");
            } catch (Exception $e) {
                error_log("Errore eliminazione file $file_path: " . $e->getMessage());
            }
        }
    }
    
    // Elimina anche le thumbnail se esistono
    $filename = pathinfo($avatar, PATHINFO_FILENAME);
    $extension = pathinfo($avatar, PATHINFO_EXTENSION);
    
    $thumbnail_patterns = [
        $base_path . 'uploads/influencers/thumb_*' . $filename . '*',
        $base_path . 'uploads/profiles/thumb_*' . $filename . '*',
        $base_path . 'influencers/uploads/thumb_*' . $filename . '*',
        $base_path . 'uploads/thumb_*' . $filename . '*'
    ];
    
    foreach ($thumbnail_patterns as $pattern) {
        foreach (glob($pattern) as $thumbnail) {
            if (file_exists($thumbnail) && is_file($thumbnail)) {
                try {
                    unlink($thumbnail);
                    error_log("Eliminata thumbnail: $thumbnail");
                } catch (Exception $e) {
                    error_log("Errore eliminazione thumbnail $thumbnail: " . $e->getMessage());
                }
            }
        }
    }
}

/**
 * Elimina completamente un brand e tutti i dati correlati
 */
function deleteBrandCompletely($user_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // 1. Ottieni informazioni sul brand prima di eliminare
        $stmt = $pdo->prepare("
            SELECT u.* 
            FROM users u 
            WHERE u.id = ? AND u.user_type = 'brand'
        ");
        $stmt->execute([$user_id]);
        $brand = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$brand) {
            throw new Exception("Brand non trovato");
        }
        
        $avatar = $brand['avatar'];
        
        // 2. Elimina i file immagine dal filesystem
        if (!empty($avatar)) {
            deleteBrandImages($avatar);
        }
        
        // 3. Elimina tutte le campagne del brand (hard delete)
        try {
            $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE brand_id = ?");
            $stmt->execute([$user_id]);
            $campaigns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($campaigns)) {
                // Elimina le viste delle campagne
                $placeholders = str_repeat('?,', count($campaigns) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM campaign_views WHERE campaign_id IN ($placeholders)");
                $stmt->execute($campaigns);
                
                // Elimina gli inviti delle campagne
                $stmt = $pdo->prepare("DELETE FROM campaign_invitations WHERE campaign_id IN ($placeholders)");
                $stmt->execute($campaigns);
                
                // Elimina le richieste di pausa e documenti correlati
                $stmt = $pdo->prepare("SELECT id FROM campaign_pause_requests WHERE campaign_id IN ($placeholders)");
                $stmt->execute($campaigns);
                $pause_requests = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($pause_requests)) {
                    $pr_placeholders = str_repeat('?,', count($pause_requests) - 1) . '?';
                    
                    // Elimina i documenti delle richieste di pausa
                    $stmt = $pdo->prepare("DELETE FROM campaign_pause_documents WHERE pause_request_id IN ($pr_placeholders)");
                    $stmt->execute($pause_requests);
                    
                    // Elimina le richieste di pausa
                    $stmt = $pdo->prepare("DELETE FROM campaign_pause_requests WHERE id IN ($pr_placeholders)");
                    $stmt->execute($pause_requests);
                }
                
                // Elimina le campagne
                $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id IN ($placeholders)");
                $stmt->execute($campaigns);
            }
        } catch (Exception $e) {
            // Log dell'errore ma continua con l'eliminazione
            error_log("Errore nell'eliminazione delle campagne del brand $user_id: " . $e->getMessage());
        }
        
        // 4. MODIFICA: NON eliminare conversazioni e messaggi - PRESERVAZIONE CONVERSAZIONI
        // Le conversazioni rimangono nel database per essere visibili agli influencer
        
        // 5. Elimina eventuali relazioni con influencer (matching)
        try {
            $stmt = $pdo->prepare("DELETE FROM matching WHERE brand_id = ?");
            $stmt->execute([$user_id]);
        } catch (Exception $e) {
            // Log dell'errore ma continua con l'eliminazione
            error_log("Errore nell'eliminazione dei matching del brand $user_id: " . $e->getMessage());
        }
        
        // 6. MODIFICA: Soft delete dell'utente (imposta deleted_at) invece di hard delete
        // Aggiungi anche un flag is_deleted per facilitare le query
        $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW(), is_deleted = 1, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Errore eliminazione completa brand $user_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Elimina i file immagine del brand dal filesystem
 */
function deleteBrandImages($avatar) {
    if (empty($avatar)) {
        return;
    }
    
    $base_path = $_SERVER['DOCUMENT_ROOT'] . '/';
    
    // Lista dei possibili percorsi dove potrebbe trovarsi l'immagine
    $possible_paths = [
        $base_path . 'uploads/brands/' . $avatar,
        $base_path . 'uploads/profiles/' . $avatar,
        $base_path . 'brands/uploads/' . $avatar,
        $base_path . 'uploads/' . $avatar,
        $base_path . $avatar
    ];
    
    foreach ($possible_paths as $file_path) {
        if (file_exists($file_path) && is_file($file_path)) {
            try {
                unlink($file_path);
                error_log("Eliminato file brand: $file_path");
            } catch (Exception $e) {
                error_log("Errore eliminazione file brand $file_path: " . $e->getMessage());
            }
        }
    }
    
    // Elimina anche le thumbnail se esistono
    $filename = pathinfo($avatar, PATHINFO_FILENAME);
    $extension = pathinfo($avatar, PATHINFO_EXTENSION);
    
    $thumbnail_patterns = [
        $base_path . 'uploads/brands/thumb_*' . $filename . '*',
        $base_path . 'uploads/profiles/thumb_*' . $filename . '*',
        $base_path . 'brands/uploads/thumb_*' . $filename . '*',
        $base_path . 'uploads/thumb_*' . $filename . '*'
    ];
    
    foreach ($thumbnail_patterns as $pattern) {
        foreach (glob($pattern) as $thumbnail) {
            if (file_exists($thumbnail) && is_file($thumbnail)) {
                try {
                    unlink($thumbnail);
                    error_log("Eliminata thumbnail brand: $thumbnail");
                } catch (Exception $e) {
                    error_log("Errore eliminazione thumbnail brand $thumbnail: " . $e->getMessage());
                }
            }
        }
    }
}

/**
 * Ottiene i brands con paginazione e filtri
 */
function getBrands($page = 1, $per_page = 15, $filters = []) {
    global $pdo;
    
    $offset = ($page - 1) * $per_page;
    $where_conditions = ["u.user_type = 'brand'"];
    $params = [];
    
    // Filtro per stato
    if (!empty($filters['status'])) {
        switch($filters['status']) {
            case 'active':
                $where_conditions[] = "u.is_active = 1 AND u.deleted_at IS NULL AND u.is_blocked = 0 AND (u.is_suspended = 0 OR u.suspension_end < NOW())";
                break;
            case 'suspended':
                $where_conditions[] = "u.is_suspended = 1 AND u.deleted_at IS NULL AND u.suspension_end >= NOW()";
                break;
            case 'blocked':
                $where_conditions[] = "u.is_blocked = 1 AND u.deleted_at IS NULL";
                break;
            case 'deleted':
                $where_conditions[] = "u.deleted_at IS NOT NULL";
                break;
            case 'inactive':
                $where_conditions[] = "u.is_active = 0 AND u.deleted_at IS NULL";
                break;
        }
    } else {
        // Di default mostra solo utenti non eliminati
        $where_conditions[] = "u.deleted_at IS NULL";
    }
    
    // Ricerca per nome/email/azienda
    // MODIFICATO: Ricerca anche in b.company_name (dalla tabella brands)
    if (!empty($filters['search'])) {
        $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.company_name LIKE ? OR b.company_name LIKE ?)";
        $search_term = "%{$filters['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_sql = implode(' AND ', $where_conditions);
    
    // Query per il conteggio totale
    $count_sql = "SELECT COUNT(*) as total 
                  FROM users u 
                  LEFT JOIN brands b ON u.id = b.user_id 
                  WHERE $where_sql";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Query per i dati
    // MODIFICATO: Aggiunto LEFT JOIN e selezionato b.company_name
    $sql = "SELECT u.*, 
                   COALESCE(NULLIF(b.company_name, ''), 
                           NULLIF(u.company_name, ''), 
                           u.name) as company_display_name
            FROM users u 
            LEFT JOIN brands b ON u.id = b.user_id 
            WHERE $where_sql 
            ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'data' => $brands,
        'total' => $total_count,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total_count / $per_page)
    ];
}

/**
 * Ottiene un singolo brand per ID
 */
function getBrandById($id) {
    global $pdo;
    
    $sql = "SELECT u.*, 
                   COALESCE(NULLIF(b.company_name, ''), 
                           NULLIF(u.company_name, ''), 
                           u.name) as company_display_name
            FROM users u 
            LEFT JOIN brands b ON u.id = b.user_id 
            WHERE u.id = ? AND u.user_type = 'brand'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Crea o aggiorna un brand
 */
function saveBrand($data, $id = null) {
    global $pdo;
    
    if ($id) {
        // Update - con gestione password opzionale
        if (isset($data['password']) && !empty(trim($data['password']))) {
            // Se c'è una nuova password, aggiorna anche quella
            $sql = "UPDATE users SET name = ?, email = ?, company_name = ?, password = ?, is_active = ?, updated_at = NOW() WHERE id = ?";
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare($sql);
            
            // Esegui l'aggiornamento su users
            $user_result = $stmt->execute([
                $data['name'], // Nome contatto (ora separato dal campo azienda)
                $data['email'], 
                $data['company_name'], // Questo ora è users.company_name (backup se brands.company_name è vuoto)
                $password_hash,
                $data['is_active'],
                $id
            ]);
            
            // MODIFICA: Aggiorna anche brands.company_name se esiste il record
            if ($user_result) {
                try {
                    // Controlla se esiste già un record nella tabella brands
                    $check_sql = "SELECT id FROM brands WHERE user_id = ?";
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute([$id]);
                    $brand_exists = $check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($brand_exists) {
                        // Aggiorna il record esistente nella tabella brands
                        $update_sql = "UPDATE brands SET company_name = ?, updated_at = NOW() WHERE user_id = ?";
                        $update_stmt = $pdo->prepare($update_sql);
                        $update_stmt->execute([$data['company_name'], $id]);
                    } else {
                        // Crea un nuovo record nella tabella brands
                        $insert_sql = "INSERT INTO brands (user_id, company_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
                        $insert_stmt = $pdo->prepare($insert_sql);
                        $insert_stmt->execute([$id, $data['company_name']]);
                    }
                } catch (PDOException $e) {
                    error_log("Errore nell'aggiornamento di brands.company_name: " . $e->getMessage());
                    // Non blocchiamo l'operazione se fallisce l'aggiornamento della tabella brands
                }
            }
            
            return $user_result;
        } else {
            // Se non c'è password, aggiorna solo gli altri campi
            $sql = "UPDATE users SET name = ?, email = ?, company_name = ?, is_active = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            // Esegui l'aggiornamento su users
            $user_result = $stmt->execute([
                $data['name'], // Nome contatto (ora separato dal campo azienda)
                $data['email'], 
                $data['company_name'], // Questo ora è users.company_name (backup se brands.company_name è vuoto)
                $data['is_active'],
                $id
            ]);
            
            // MODIFICA: Aggiorna anche brands.company_name se esiste il record
            if ($user_result) {
                try {
                    // Controlla se esiste già un record nella tabella brands
                    $check_sql = "SELECT id FROM brands WHERE user_id = ?";
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute([$id]);
                    $brand_exists = $check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($brand_exists) {
                        // Aggiorna il record esistente nella tabella brands
                        $update_sql = "UPDATE brands SET company_name = ?, updated_at = NOW() WHERE user_id = ?";
                        $update_stmt = $pdo->prepare($update_sql);
                        $update_stmt->execute([$data['company_name'], $id]);
                    } else {
                        // Crea un nuovo record nella tabella brands
                        $insert_sql = "INSERT INTO brands (user_id, company_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
                        $insert_stmt = $pdo->prepare($insert_sql);
                        $insert_stmt->execute([$id, $data['company_name']]);
                    }
                } catch (PDOException $e) {
                    error_log("Errore nell'aggiornamento di brands.company_name: " . $e->getMessage());
                    // Non blocchiamo l'operazione se fallisce l'aggiornamento della tabella brands
                }
            }
            
            return $user_result;
        }
    } else {
        // Insert - genera password casuale se non fornita
        $sql = "INSERT INTO users (name, email, password, user_type, company_name, is_active, created_at) 
                VALUES (?, ?, ?, 'brand', ?, ?, NOW())";
        
        // Usa password generata casualmente invece di 'password123'
        $password = generateRandomPassword(12);
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['name'], 
            $data['email'], 
            $password_hash,
            $data['company_name'],
            $data['is_active']
        ]);
        
        // MODIFICA: Crea anche il record nella tabella brands se l'inserimento su users ha successo
        if ($result) {
            try {
                $user_id = $pdo->lastInsertId();
                $insert_sql = "INSERT INTO brands (user_id, company_name, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute([$user_id, $data['company_name']]);
            } catch (PDOException $e) {
                error_log("Errore nella creazione del record in brands: " . $e->getMessage());
                // Non blocchiamo l'operazione se fallisce la creazione nella tabella brands
            }
            
            error_log("Nuovo brand creato con password generata: $password");
        }
        
        return $result;
    }
}

/**
 * Gestisce lo stato di un brand (sospensione, blocco, etc.)
 */
function updateBrandStatus($id, $action, $suspension_end = null) {
    global $pdo;
    
    switch($action) {
        case 'suspend':
            $sql = "UPDATE users SET is_suspended = 1, suspension_end = ?, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$suspension_end, $id]);
            
        case 'unsuspend':
            $sql = "UPDATE users SET is_suspended = 0, suspension_end = NULL, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'block':
            $sql = "UPDATE users SET is_blocked = 1, is_suspended = 0, suspension_end = NULL, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'unblock':
            $sql = "UPDATE users SET is_blocked = 0, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'delete':
            $sql = "UPDATE users SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        case 'restore':
            $sql = "UPDATE users SET deleted_at = NULL, updated_at = NOW() WHERE id = ?";
            return $pdo->prepare($sql)->execute([$id]);
            
        default:
            return false;
    }
}

/**
 * Verifica se l'email esiste già (escludendo l'utente corrente per l'edit)
 */
function emailExists($email, $exclude_user_id = null) {
    global $pdo;
    
    if ($exclude_user_id) {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ? AND id != ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email, $exclude_user_id]);
    } else {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
    }
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Ottiene lo stato completo di un utente
 */
function getUserStatus($user) {
    if ($user['deleted_at']) {
        return 'deleted';
    } elseif ($user['is_blocked']) {
        return 'blocked';
    } elseif ($user['is_suspended'] && $user['suspension_end'] && strtotime($user['suspension_end']) > time()) {
        return 'suspended';
    } elseif (!$user['is_active']) {
        return 'inactive';
    } else {
        return 'active';
    }
}

/**
 * Formatta la data per la visualizzazione
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

// =============================================================================
// FUNZIONI PER LA GESTIONE DELLE CAMPAGNE BRAND
// =============================================================================

/**
 * Elimina una campagna (soft delete)
 */
function deleteCampaign($campaign_id) {
    global $pdo;
    
    $sql = "UPDATE campaigns SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$campaign_id]);
}

/**
 * Recupera la lista delle campagne con filtri e paginazione
 */
function getCampaigns($page = 1, $per_page = 15, $filters = []) {
    global $pdo;
    
    // PRIMA: Assicurati che le campagne scadute siano aggiornate quando si usa il filtro expired
    if (isset($filters['status']) && $filters['status'] === 'expired') {
        checkAndUpdateExpiredCampaigns();
    }
    
    $offset = ($page - 1) * $per_page;
    $where_conditions = ["c.deleted_at IS NULL"];
    $params = [];
    
    // Costruzione condizioni WHERE
    if (!empty($filters['search'])) {
        $where_conditions[] = "c.name LIKE :search";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    // MODIFICA: Logica per il filtro "Scadute"
    if (!empty($filters['status'])) {
        if ($filters['status'] === 'expired') {
            // Includi sia campagne con status 'expired' sia campagne con deadline passata
            $where_conditions[] = "(c.status = 'expired' OR (c.deadline_date IS NOT NULL AND c.deadline_date < CURDATE()))";
        } else {
            // Filtro normale per altri stati
            $where_conditions[] = "c.status = :status";
            $params[':status'] = $filters['status'];
        }
    }
    
    if (!empty($filters['brand_id'])) {
        $where_conditions[] = "c.brand_id = :brand_id";
        $params[':brand_id'] = $filters['brand_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $where_conditions[] = "c.start_date >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where_conditions[] = "c.end_date <= :date_to";
        $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
    }
    
    $where_sql = implode(" AND ", $where_conditions);
    
    // Query per il conteggio totale
    $count_sql = "SELECT COUNT(*) as total 
                  FROM campaigns c 
                  WHERE " . $where_sql;
    
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // === SOLUZIONE DEFINITIVA: Usa stessa logica di brands.php ===
    $sql = "SELECT c.*, 
                   -- Stessa logica esatta usata in brands.php
                   COALESCE(
                       NULLIF(b.company_name, ''), 
                       NULLIF(u.company_name, ''), 
                       u.name
                   ) as brand_display_name,
                   (SELECT COUNT(*) FROM campaign_views WHERE campaign_id = c.id) as views_count,
                   (SELECT COUNT(*) FROM campaign_invitations WHERE campaign_id = c.id) as invited_count
            FROM campaigns c 
            -- CORREZIONE: brand_id si riferisce a brands.id, poi users tramite user_id
            LEFT JOIN brands b ON c.brand_id = b.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE " . $where_sql . " 
            ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";
    
    $params[':limit'] = $per_page;
    $params[':offset'] = $offset;
    
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, (int)$value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_pages = ceil($total / $per_page);
    
    return [
        'data' => $data,
        'total' => $total,
        'total_pages' => $total_pages,
        'current_page' => $page
    ];
}

/**
 * Recupera una campagna specifica per ID
 */
function getCampaignById($id) {
    global $pdo;
    
    $sql = "SELECT c.*, 
                   -- Stessa logica esatta usata in brands.php
                   COALESCE(
                       NULLIF(b.company_name, ''), 
                       NULLIF(u.company_name, ''), 
                       u.name
                   ) as brand_display_name,
                   (SELECT COUNT(*) FROM campaign_views WHERE campaign_id = c.id) as views_count,
                   (SELECT COUNT(*) FROM campaign_invitations WHERE campaign_id = c.id) as invited_count
            FROM campaigns c 
            -- CORREZIONE: brand_id si riferisce a brands.id, poi users tramite user_id
            LEFT JOIN brands b ON c.brand_id = b.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE c.id = ? AND c.deleted_at IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Salva/aggiorna una campagna
 */
function saveCampaign($data, $id = null) {
    global $pdo;
    
    try {
        if ($id) {
            // Update
            $sql = "UPDATE campaigns SET 
                    brand_id = ?, name = ?, description = ?, budget = ?, currency = ?, niche = ?, 
                    platforms = ?, target_audience = ?, status = ?, start_date = ?, 
                    end_date = ?, requirements = ?, is_public = ?, allow_applications = ?, 
                    deadline_date = ?, updated_at = NOW() 
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $data['brand_id'], $data['name'], $data['description'], $data['budget'], $data['currency'],
                $data['niche'], $data['platforms'], $data['target_audience'], $data['status'],
                $data['start_date'], $data['end_date'], $data['requirements'], 
                $data['is_public'], $data['allow_applications'], 
                isset($data['deadline_date']) ? $data['deadline_date'] : null,
                $id
            ]);
        } else {
            // Insert
            $sql = "INSERT INTO campaigns 
                    (brand_id, name, description, budget, currency, niche, platforms, 
                     target_audience, status, start_date, end_date, requirements, 
                     is_public, allow_applications, deadline_date, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $data['brand_id'], $data['name'], $data['description'], $data['budget'],
                $data['currency'], $data['niche'], $data['platforms'], $data['target_audience'],
                $data['status'], $data['start_date'], $data['end_date'], $data['requirements'],
                $data['is_public'], $data['allow_applications'],
                isset($data['deadline_date']) ? $data['deadline_date'] : null
            ]);
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Errore salvataggio campagna: " . $e->getMessage());
        return false;
    }
}

/**
 * Aggiorna lo stato di una campagna
 */
function updateCampaignStatus($campaign_id, $status) {
    global $pdo;
    
    $sql = "UPDATE campaigns SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$status, $campaign_id]);
}

/**
 * Conta le campagne per stato
 */
function getCampaignsCount($status = null) {
    global $pdo;
    
    // PRIMA: Assicurati che le campagne scadute siano aggiornate
    if ($status === 'expired') {
        checkAndUpdateExpiredCampaigns();
    }
    
    $sql = "SELECT COUNT(*) FROM campaigns WHERE deleted_at IS NULL";
    
    if ($status) {
        if ($status === 'expired') {
            // MODIFICA: Conta sia campagne con status 'expired' sia campagne con deadline passata
            $sql .= " AND (status = 'expired' OR (deadline_date IS NOT NULL AND deadline_date < CURDATE()))";
        } else {
            $sql .= " AND status = ?";
        }
    }
    
    $stmt = $pdo->prepare($sql);
    
    if ($status && $status !== 'expired') {
        $stmt->execute([$status]);
    } else {
        $stmt->execute();
    }
    
    return $stmt->fetchColumn();
}

/**
 * Controlla e aggiorna automaticamente le campagne in pausa scadute
 */
function checkExpiredPausedCampaigns() {
    global $pdo;
    
    try {
        $query = "
            UPDATE campaigns 
            SET status = 'expired', 
                updated_at = NOW() 
            WHERE status IN ('paused', 'active')
            AND deadline_date IS NOT NULL 
            AND deadline_date < CURDATE()
            AND status != 'expired'
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        $expired_count = $stmt->rowCount();
        
        if ($expired_count > 0) {
            error_log("Auto-expired $expired_count campaigns (paused/active with passed deadline)");
        }
        
        return $expired_count;
        
    } catch (PDOException $e) {
        error_log("Error checking expired campaigns: " . $e->getMessage());
        return 0;
    }
}

/**
 * Controlla e aggiorna IMMEDIATAMENTE le campagne scadute - VERSIONE DEFINITIVA
 */
function checkAndUpdateExpiredCampaigns() {
    global $pdo;
    
    try {
        $current_date = date('Y-m-d H:i:s');
        
        // FORZA l'aggiornamento di TUTTE le campagne con deadline passata
        $query = "
            UPDATE campaigns 
            SET status = 'expired', 
                updated_at = NOW() 
            WHERE deleted_at IS NULL
            AND deadline_date IS NOT NULL 
            AND deadline_date < ?
            AND status != 'expired'
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$current_date]);
        $expired_count = $stmt->rowCount();
        
        if ($expired_count > 0) {
            error_log("SUCCESS: Updated $expired_count campaigns to 'expired' status");
            
            // Log delle campagne aggiornate per debug
            $log_sql = "SELECT id, name, deadline_date 
                       FROM campaigns 
                       WHERE updated_at > DATE_SUB(NOW(), INTERVAL 5 SECOND) 
                       AND status = 'expired'";
            $log_stmt = $pdo->prepare($log_sql);
            $log_stmt->execute();
            $updated = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($updated as $campaign) {
                error_log(" - Campaign {$campaign['id']}: {$campaign['name']} (deadline: {$campaign['deadline_date']})");
            }
        }
        
        return $expired_count;
        
    } catch (PDOException $e) {
        error_log("ERROR in checkAndUpdateExpiredCampaigns: " . $e->getMessage());
        return 0;
    }
}

/**
 * Aggiorna la scadenza di una campagna
 */
function updateCampaignDeadline($campaign_id, $deadline) {
    global $pdo;
    
    try {
        $query = "UPDATE campaigns SET deadline_date = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($query);
        return $stmt->execute([$deadline, $campaign_id]);
    } catch (PDOException $e) {
        error_log("Error updating campaign deadline: " . $e->getMessage());
        return false;
    }
}

/**
 * Recupera tutti i brand per i dropdown
 */
function getAllBrands() {
    global $pdo;
    
    $sql = "SELECT id, name, company_name, 
                   COALESCE(NULLIF(company_name, ''), name) as display_name 
            FROM users 
            WHERE user_type = 'brand' AND deleted_at IS NULL 
            ORDER BY company_name, name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Verifica se una campagna esiste
 */
function campaignExists($campaign_id) {
    global $pdo;
    
    $sql = "SELECT COUNT(*) FROM campaigns WHERE id = ? AND deleted_at IS NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$campaign_id]);
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Ottiene statistiche dettagliate per una campagna
 */
function getCampaignStats($campaign_id) {
    global $pdo;
    
    $stats = [
        'views' => 0,
        'invitations' => 0,
        'applications' => 0,
        'accepted_invitations' => 0
    ];
    
    try {
        // Visualizzazioni
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaign_views WHERE campaign_id = ?");
        $stmt->execute([$campaign_id]);
        $stats['views'] = $stmt->fetchColumn();
        
        // Inviti totali
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaign_invitations WHERE campaign_id = ?");
        $stmt->execute([$campaign_id]);
        $stats['invitations'] = $stmt->fetchColumn();
        
        // Inviti accettati
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM campaign_invitations WHERE campaign_id = ? AND status = 'accepted'");
        $stmt->execute([$campaign_id]);
        $stats['accepted_invitations'] = $stmt->fetchColumn();
        
    } catch (PDOException $e) {
        error_log("Errore recupero statistiche campagna: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Elimina definitivamente una campagna (hard delete)
 */
function hardDeleteCampaign($campaign_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Elimina visualizzazioni correlate
        $stmt = $pdo->prepare("DELETE FROM campaign_views WHERE campaign_id = ?");
        $stmt->execute([$campaign_id]);
        
        // Elimina inviti correlati
        $stmt = $pdo->prepare("DELETE FROM campaign_invitations WHERE campaign_id = ?");
        $stmt->execute([$campaign_id]);
        
        // Elimina la campagna
        $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = ?");
        $result = $stmt->execute([$campaign_id]);
        
        $pdo->commit();
        return $result;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Errore eliminazione campagna: " . $e->getMessage());
        return false;
    }
}

/**
 * Duplica una campagna esistente
 */
function duplicateCampaign($campaign_id) {
    global $pdo;
    
    try {
        $campaign = getCampaignById($campaign_id);
        if (!$campaign) {
            return false;
        }
        
        $sql = "INSERT INTO campaigns 
                (brand_id, name, description, budget, currency, niche, platforms, 
                 target_audience, status, start_date, end_date, requirements, 
                 is_public, allow_applications, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $new_name = $campaign['name'] . ' - Copia';
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $campaign['brand_id'],
            $new_name,
            $campaign['description'],
            $campaign['budget'],
            $campaign['currency'],
            $campaign['niche'],
            $campaign['platforms'],
            $campaign['target_audience'],
            $campaign['start_date'],
            $campaign['end_date'],
            $campaign['requirements'],
            $campaign['is_public'],
            $campaign['allow_applications']
        ]);
        
    } catch (PDOException $e) {
        error_log("Errore duplicazione campagna: " . $e->getMessage());
        return false;
    }
}

// =============================================================================
// FUNZIONI PER IL SISTEMA DI PAUSA CAMPAGNE E RICHIESTE DOCUMENTI
// =============================================================================

/**
 * Crea una nuova richiesta di pausa per una campagna
 */
function createPauseRequest($data) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO campaign_pause_requests 
                (campaign_id, admin_id, pause_reason, required_documents, deadline, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['campaign_id'],
            $data['admin_id'],
            $data['pause_reason'],
            $data['required_documents'],
            $data['deadline']
        ]);
        
    } catch (PDOException $e) {
        error_log("Errore creazione richiesta pausa: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottiene tutte le richieste di pausa per una campagna
 */
function getCampaignPauseRequests($campaign_id) {
    global $pdo;
    
    try {
        $sql = "SELECT cpr.*, a.username as admin_name 
                FROM campaign_pause_requests cpr 
                LEFT JOIN admins a ON cpr.admin_id = a.id 
                WHERE cpr.campaign_id = ? 
                ORDER BY cpr.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$campaign_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Errore recupero richieste pausa: " . $e->getMessage());
        return [];
    }
}

/**
 * Ottiene una specifica richiesta di pausa
 */
function getPauseRequest($request_id) {
    global $pdo;
    
    try {
        $sql = "SELECT cpr.*, a.username as admin_name, c.name as campaign_name, 
                       u.company_name as brand_name, u.email as brand_email
                FROM campaign_pause_requests cpr 
                LEFT JOIN admins a ON cpr.admin_id = a.id 
                JOIN campaigns c ON cpr.campaign_id = c.id 
                JOIN users u ON c.brand_id = u.id 
                WHERE cpr.id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$request_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Errore recupero richiesta pausa: " . $e->getMessage());
        return null;
    }
}

/**
 * Completa una richiesta di pausa
 */
function completePauseRequest($request_id) {
    global $pdo;
    
    try {
        $sql = "UPDATE campaign_pause_requests 
                SET status = 'completed', updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$request_id]);
        
    } catch (PDOException $e) {
        error_log("Errore completamento richiesta pausa: " . $e->getMessage());
        return false;
    }
}

/**
 * Cancella una richiesta di pausa
 */
function cancelPauseRequest($request_id) {
    global $pdo;
    
    try {
        $sql = "UPDATE campaign_pause_requests 
                SET status = 'cancelled', updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$request_id]);
        
    } catch (PDOException $e) {
        error_log("Errore cancellazione richiesta pausa: " . $e->getMessage());
        return false;
    }
}

/**
 * Completa tutte le richieste di pausa pendenti per una campagna
 */
function completePendingPauseRequests($campaign_id) {
    global $pdo;
    
    try {
        $sql = "UPDATE campaign_pause_requests 
                SET status = 'completed', updated_at = NOW() 
                WHERE campaign_id = ? AND status = 'pending'";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$campaign_id]);
        
    } catch (PDOException $e) {
        error_log("Errore completamento richieste pausa pendenti: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottiene i documenti caricati per una richiesta di pausa
 */
function getPauseRequestDocuments($pause_request_id) {
    global $pdo;
    
    try {
        $sql = "SELECT cpd.*, u.name as uploaded_by_name 
                FROM campaign_pause_documents cpd 
                LEFT JOIN users u ON cpd.uploaded_by = u.id 
                WHERE cpd.pause_request_id = ? 
                ORDER BY cpd.uploaded_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$pause_request_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Errore recupero documenti pausa: " . $e->getMessage());
        return [];
    }
}

/**
 * Conta le richieste di pausa pendenti per una campagna
 */
function countPendingPauseRequests($campaign_id) {
    global $pdo;
    
    try {
        $sql = "SELECT COUNT(*) FROM campaign_pause_requests 
                WHERE campaign_id = ? AND status = 'pending'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$campaign_id]);
        return $stmt->fetchColumn();
        
    } catch (PDOException $e) {
        error_log("Errore conteggio richieste pausa pendenti: " . $e->getMessage());
        return 0;
    }
}

/**
 * Ottiene statistiche sulle richieste di pausa
 */
function getPauseRequestsStats($timeframe = 'month') {
    global $pdo;
    
    try {
        $time_conditions = [
            'week' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
            'month' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            'year' => "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)"
        ];
        
        $time_condition = $time_conditions[$timeframe] ?? $time_conditions['month'];
        
        $sql = "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_requests,
                SUM(CASE WHEN deadline < CURDATE() AND status = 'pending' THEN 1 ELSE 0 END) as overdue_requests
                FROM campaign_pause_requests 
                WHERE $time_condition";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Errore statistiche richieste pausa: " . $e->getMessage());
        return [
            'total_requests' => 0,
            'pending_requests' => 0,
            'completed_requests' => 0,
            'cancelled_requests' => 0,
            'overdue_requests' => 0
        ];
    }
}

/**
 * Invia notifica al brand per una nuova richiesta di pausa
 */
function sendPauseRequestNotification($pause_request_id) {
    global $pdo;
    
    try {
        $request = getPauseRequest($pause_request_id);
        if (!$request) {
            return false;
        }
        
        // Qui puoi implementare l'invio di email o notifiche push
        error_log("Notifica richiesta pausa: Campagna '{$request['campaign_name']}' - Brand: {$request['brand_email']}");
        
        return true;
        
    } catch (Exception $e) {
        error_log("Errore invio notifica pausa: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se una campagna ha richieste di pausa pendenti
 */
function hasPendingPauseRequests($campaign_id) {
    return countPendingPauseRequests($campaign_id) > 0;
}

/**
 * Ottiene le richieste di pausa in scadenza
 */
function getExpiringPauseRequests($days_before = 3) {
    global $pdo;
    
    try {
        $sql = "SELECT cpr.*, c.name as campaign_name, u.company_name as brand_name,
                       DATEDIFF(cpr.deadline, CURDATE()) as days_remaining
                FROM campaign_pause_requests cpr 
                JOIN campaigns c ON cpr.campaign_id = c.id 
                JOIN users u ON c.brand_id = u.id 
                WHERE cpr.status = 'pending' 
                AND cpr.deadline IS NOT NULL 
                AND cpr.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY cpr.deadline ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$days_before]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Errore recupero richieste in scadenza: " . $e->getMessage());
        return [];
    }
}

/**
 * Ottiene le richieste di pausa scadute
 */
function getOverduePauseRequests() {
    global $pdo;
    
    try {
        $sql = "SELECT cpr.*, c.name as campaign_name, u.company_name as brand_name,
                       DATEDIFF(CURDATE(), cpr.deadline) as days_overdue
                FROM campaign_pause_requests cpr 
                JOIN campaigns c ON cpr.campaign_id = c.id 
                JOIN users u ON c.brand_id = u.id 
                WHERE cpr.status = 'pending' 
                AND cpr.deadline IS NOT NULL 
                AND cpr.deadline < CURDATE()
                ORDER BY cpr.deadline ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Errore recupero richieste scadute: " . $e->getMessage());
        return [];
    }
}

// =============================================================================
// FUNZIONI PER IL SISTEMA DI APPROVAZIONE DOCUMENTI (AGGIORNATE E CORRETTE)
// =============================================================================

/**
 * Recupera l'ID della campagna da una richiesta di pausa
 */
function getCampaignIdFromPauseRequest($pause_request_id) {
    global $pdo;
    
    try {
        $sql = "SELECT campaign_id FROM campaign_pause_requests WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$pause_request_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['campaign_id'] : null;
        
    } catch (PDOException $e) {
        error_log("Errore recupero campaign_id da pause request: " . $e->getMessage());
        return null;
    }
}

/**
 * Aggiorna lo stato di una richiesta di pausa con commento admin
 * FIXED: Gestione corretta stati per mantenere campagna in pausa quando si richiedono modifiche
 */
function updatePauseRequestStatus($request_id, $status, $admin_comment = null, $admin_id = null) {
    global $pdo;
    
    try {
        // FIX: Verifica che la richiesta esista e non sia già completata
        $check_sql = "SELECT campaign_id, status FROM campaign_pause_requests WHERE id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$request_id]);
        $existing_request = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing_request) {
            error_log("Richiesta pausa non trovata: " . $request_id);
            return false;
        }
        
        // FIX: Se è già approved, non fare nulla
        if ($existing_request['status'] === 'approved') {
            error_log("Richiesta pausa già approvata: " . $request_id);
            return true;
        }
        
        $sql = "UPDATE campaign_pause_requests 
                SET status = ?, admin_review_comment = ?, admin_reviewed_by = ?, 
                    admin_reviewed_at = NOW(), updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $status,
            $admin_comment,
            $admin_id,
            $request_id
        ]);
        
        // FIX: Logica di gestione stati campagna migliorata
        $campaign_id = $existing_request['campaign_id'];
        if ($result && $campaign_id) {
            if ($status === 'approved') {
                // Approvazione: riattiva la campagna
                updateCampaignStatus($campaign_id, 'active');
            } elseif ($status === 'changes_requested') {
                // Richiesta modifiche: MANTIENE la campagna in pausa
                updateCampaignStatus($campaign_id, 'paused');
            }
            // Per gli altri stati (under_review, etc.) non cambia lo stato campagna
        }
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("Errore aggiornamento stato richiesta pausa: " . $e->getMessage());
        return false;
    }
}

/**
 * FIX: Nuova funzione per prevenire duplicazioni - Verifica richieste attive
 */
function hasActivePauseRequest($campaign_id) {
    global $pdo;
    
    try {
        $sql = "SELECT COUNT(*) FROM campaign_pause_requests 
                WHERE campaign_id = ? 
                AND status IN ('pending', 'documents_uploaded', 'under_review', 'changes_requested')";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$campaign_id]);
        return $stmt->fetchColumn() > 0;
        
    } catch (PDOException $e) {
        error_log("Errore verifica richieste attive: " . $e->getMessage());
        return false;
    }
}

/**
 * Aggiorna il commento del brand durante l'upload documenti
 */
function updatePauseRequestBrandComment($request_id, $brand_comment) {
    global $pdo;
    
    try {
        $sql = "UPDATE campaign_pause_requests 
                SET brand_upload_comment = ?, updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $brand_comment,
            $request_id
        ]);
        
    } catch (PDOException $e) {
        error_log("Errore aggiornamento commento brand: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottiene la cronologia completa delle pause per una campagna
 */
function getCampaignPauseHistory($campaign_id) {
    global $pdo;
    
    try {
        $sql = "SELECT cpr.*, a.username as admin_name, 
                       ar.username as reviewed_by_name,
                       (SELECT COUNT(*) FROM campaign_pause_documents cpd WHERE cpd.pause_request_id = cpr.id) as documents_count
                FROM campaign_pause_requests cpr 
                LEFT JOIN admins a ON cpr.admin_id = a.id 
                LEFT JOIN admins ar ON cpr.admin_reviewed_by = ar.id 
                WHERE cpr.campaign_id = ? 
                ORDER BY cpr.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$campaign_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Errore recupero cronologia pause: " . $e->getMessage());
        return [];
    }
}

/**
 * Ottiene statistiche dettagliate per le richieste di pausa di una campagna
 */
function getCampaignPauseStats($campaign_id) {
    global $pdo;
    
    try {
        $sql = "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                SUM(CASE WHEN status = 'documents_uploaded' THEN 1 ELSE 0 END) as documents_uploaded_requests,
                SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_requests,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                SUM(CASE WHEN status = 'changes_requested' THEN 1 ELSE 0 END) as changes_requested_requests,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_requests
                FROM campaign_pause_requests 
                WHERE campaign_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$campaign_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Errore statistiche pause campagna: " . $e->getMessage());
        return [
            'total_requests' => 0,
            'pending_requests' => 0,
            'documents_uploaded_requests' => 0,
            'under_review_requests' => 0,
            'approved_requests' => 0,
            'changes_requested_requests' => 0,
            'completed_requests' => 0,
            'cancelled_requests' => 0
        ];
    }
}

/**
 * Contrassegna una richiesta di pausa come "in revisione"
 * FIXED: Funzione corretta per stato under_review
 */
function markPauseRequestUnderReview($request_id, $admin_id) {
    global $pdo;
    
    try {
        $sql = "UPDATE campaign_pause_requests 
                SET status = 'under_review', admin_reviewed_by = ?, 
                    admin_reviewed_at = NOW(), updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$admin_id, $request_id]);
        
    } catch (PDOException $e) {
        error_log("Errore marcatura in revisione: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottiene il percorso del file per il download
 */
function getDocumentFilePath($document_id) {
    global $pdo;
    
    try {
        $sql = "SELECT file_path FROM campaign_pause_documents WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$document_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['file_path'] : null;
        
    } catch (PDOException $e) {
        error_log("Errore recupero percorso file: " . $e->getMessage());
        return null;
    }
}

/**
 * Invia notifica al brand per cambio stato richiesta
 */
function sendPauseRequestStatusNotification($pause_request_id, $new_status, $admin_comment = null) {
    global $pdo;
    
    try {
        $request = getPauseRequest($pause_request_id);
        if (!$request) {
            return false;
        }
        
        // Qui puoi implementare l'invio di email o notifiche push
        $status_messages = [
            'approved' => 'I tuoi documenti sono stati approvati',
            'changes_requested' => 'Sono richieste modifiche ai documenti inviati',
            'under_review' => 'I tuoi documenti sono in revisione'
        ];
        
        $message = $status_messages[$new_status] ?? "Stato richiesta aggiornato: {$new_status}";
        if ($admin_comment) {
            $message .= "\nCommento admin: {$admin_comment}";
        }
        
        error_log("Notifica stato richiesta pausa: Campagna '{$request['campaign_name']}' - Brand: {$request['brand_email']} - Messaggio: {$message}");
        
        return true;
        
    } catch (Exception $e) {
        error_log("Errore invio notifica stato: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se una richiesta di pausa può essere approvata
 */
function canApprovePauseRequest($request_id) {
    global $pdo;
    
    try {
        $sql = "SELECT status, campaign_id FROM campaign_pause_requests WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            return false;
        }
        
        // Può essere approvata solo se in stati specifici
        $approvable_states = ['documents_uploaded', 'under_review', 'changes_requested'];
        return in_array($request['status'], $approvable_states);
        
    } catch (PDOException $e) {
        error_log("Errore verifica approvazione richiesta: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se una richiesta di pausa può richiedere modifiche
 */
function canRequestChangesPauseRequest($request_id) {
    global $pdo;
    
    try {
        $sql = "SELECT status FROM campaign_pause_requests WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) {
            return false;
        }
        
        // Può richiedere modifiche solo se in stati specifici
        $changeable_states = ['documents_uploaded', 'under_review'];
        return in_array($request['status'], $changeable_states);
        
    } catch (PDOException $e) {
        error_log("Errore verifica richiesta modifiche: " . $e->getMessage());
        return false;
    }
}

// =============================================================================
// FUNZIONI PER ELIMINAZIONE DOCUMENTI (NUOVE)
// =============================================================================

/**
 * Elimina un documento dalla richiesta di pausa
 */
function deletePauseDocument($document_id) {
    global $pdo;
    
    try {
        // Recupera informazioni sul documento
        $sql = "SELECT cpd.*, cpr.campaign_id 
                FROM campaign_pause_documents cpd 
                JOIN campaign_pause_requests cpr ON cpd.pause_request_id = cpr.id 
                WHERE cpd.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$document_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            return false;
        }

        // Elimina il file fisico
        $file_path = $_SERVER['DOCUMENT_ROOT'] . $document['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        // Elimina il record dal database
        $delete_sql = "DELETE FROM campaign_pause_documents WHERE id = ?";
        $delete_stmt = $pdo->prepare($delete_sql);
        return $delete_stmt->execute([$document_id]);

    } catch (PDOException $e) {
        error_log("Errore eliminazione documento pausa: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifica se un documento può essere eliminato
 */
function canDeletePauseDocument($document_id) {
    global $pdo;
    
    try {
        $sql = "SELECT cpd.*, cpr.status 
                FROM campaign_pause_documents cpd 
                JOIN campaign_pause_requests cpr ON cpd.pause_request_id = cpr.id 
                WHERE cpd.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$document_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return false;
        }

        // FIX: Permetti eliminazione in qualsiasi stato
        return true;

    } catch (PDOException $e) {
        error_log("Errore verifica eliminazione documento: " . $e->getMessage());
        return false;
    }
}

/**
 * Log delle azioni admin
 */
function logAdminAction($admin_id, $action) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO admin_actions (admin_id, action, created_at) VALUES (?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$admin_id, $action]);
    } catch (PDOException $e) {
        error_log("Errore log azione admin: " . $e->getMessage());
        return false;
    }
}

// =============================================================================
// FUNZIONI PER GESTIONE AMMINISTRATORI
// =============================================================================

/**
 * Verifica se l'utente corrente è Super Admin
 */
function is_super_admin() {
    return isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true;
}

/**
 * Richiede i permessi di Super Admin
 */
function require_super_admin() {
    if (!is_super_admin()) {
        $_SESSION['error_message'] = "Accesso negato. Solo i Super Admin possono accedere a questa funzionalità.";
        header("Location: dashboard.php");
        exit();
    }
}

/**
 * Ottiene statistiche per la dashboard admin
 */
function get_admin_dashboard_stats() {
    global $pdo;
    
    $stats = [];
    
    try {
        // Conta admin totali (attivi)
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins WHERE is_active = 1");
        $stats['total_admins'] = $stmt->fetchColumn();
        
        // Conta super admin (attivi)
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins WHERE is_active = 1 AND is_super_admin = 1");
        $stats['super_admins'] = $stmt->fetchColumn();
        
        // Conta admin regolari (attivi)
        $stats['regular_admins'] = $stats['total_admins'] - $stats['super_admins'];
        
        // Conta admin disattivati
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins WHERE is_active = 0");
        $stats['inactive_admins'] = $stmt->fetchColumn();
        
    } catch (PDOException $e) {
        error_log("Errore statistiche admin: " . $e->getMessage());
        // Valori di default in caso di errore
        $stats = [
            'total_admins' => 0,
            'super_admins' => 0,
            'regular_admins' => 0,
            'inactive_admins' => 0
        ];
    }
    
    return $stats;
}

/**
 * Verifica se esiste già un admin con lo stesso username
 */
function admin_username_exists($username, $exclude_id = null) {
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
 * Verifica se esiste già un admin con la stessa email
 */
function admin_email_exists($email, $exclude_id = null) {
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
 * Crea un nuovo admin
 */
function create_admin($username, $email, $password, $full_name = null, $is_super_admin = false) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO admins (username, email, password, full_name, is_super_admin, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        return $stmt->execute([$username, $email, $password_hash, $full_name, $is_super_admin]);
    } catch (PDOException $e) {
        error_log("Errore creazione admin: " . $e->getMessage());
        return false;
    }
}

/**
 * Elimina definitivamente un admin (HARD DELETE)
 */
function hard_delete_admin($admin_id) {
    global $pdo;
    
    try {
        $sql = "DELETE FROM admins WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$admin_id]);
    } catch (PDOException $e) {
        error_log("Errore eliminazione definitiva admin: " . $e->getMessage());
        return false;
    }
}

// =============================================================================
// FUNZIONI PER GESTIONE SPONSOR INFLUENCER
// =============================================================================

/**
 * Ottiene gli sponsor con paginazione e filtri
 */
function getSponsors($page = 1, $per_page = 25, $filters = []) {
    global $pdo;
    
    $offset = ($page - 1) * $per_page;
    $where_conditions = ["s.deleted_at IS NULL"];
    $params = [];
    
    // Costruzione condizioni WHERE
    if (!empty($filters['search'])) {
        $where_conditions[] = "s.title LIKE ?";
        $params[] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['status'])) {
        $where_conditions[] = "s.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['influencer_search'])) {
        $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
        $params[] = '%' . $filters['influencer_search'] . '%';
        $params[] = '%' . $filters['influencer_search'] . '%';
    }
    
    // Normalizzazione categorie per matching corretto
    if (!empty($filters['category'])) {
        $category = trim($filters['category']);
        
        // Mappa di normalizzazione per categorie con nomi diversi
        $category_mapping = [
            'Fitness & Wellness' => 'fitness-wellness',
            'Education' => 'education',
            'Beauty & Makeup' => 'Beauty & Makeup', // Mantieni come è
            'Fashion' => 'Fashion',
            'Lifestyle' => 'Lifestyle', 
            'Food' => 'Food',
            'Travel' => 'Travel',
            'Gaming' => 'Gaming',
            // Aggiungi altre mappature se necessario
        ];
        
        // Se la categoria è nella mappa, usa il valore normalizzato, altrimenti usa l'originale
        $normalized_category = $category_mapping[$category] ?? $category;
        
        $where_conditions[] = "s.category = ?";
        $params[] = $normalized_category;
    }
    
    $where_sql = implode(" AND ", $where_conditions);
    
    // Query per il conteggio totale
    $count_sql = "SELECT COUNT(*) as total 
                  FROM sponsors s 
                  LEFT JOIN influencers i ON s.influencer_id = i.id
                  LEFT JOIN users u ON i.user_id = u.id
                  WHERE " . $where_sql;
    
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Query per i dati
    $sql = "SELECT 
                s.*, 
                u.email as influencer_email, 
                COALESCE(NULLIF(u.name, ''), u.email, 'Influencer Sconosciuto') as influencer_name
            FROM sponsors s 
            LEFT JOIN influencers i ON s.influencer_id = i.id
            LEFT JOIN users u ON i.user_id = u.id
            WHERE " . $where_sql . " 
            ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
    
    // Aggiungi i parametri di paginazione
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_pages = ceil($total / $per_page);
    
    return [
        'data' => $data,
        'total' => $total,
        'total_pages' => $total_pages,
        'current_page' => $page
    ];
}

/**
 * Ottiene un singolo sponsor per ID
 */
function getSponsorById($id) {
    global $pdo;
    
    $sql = "SELECT 
                s.*, 
                u.email as influencer_email, 
                u.name as influencer_name
            FROM sponsors s 
            LEFT JOIN influencers i ON s.influencer_id = i.id
            LEFT JOIN users u ON i.user_id = u.id
            WHERE s.id = ? AND s.deleted_at IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Salva/aggiorna uno sponsor
 */
function saveSponsor($data, $id = null) {
    global $pdo;
    
    try {
        if ($id) {
            // Update
            $sql = "UPDATE sponsors SET 
                    influencer_id = ?, title = ?, description = ?, budget = ?, category = ?, 
                    platforms = ?, target_audience = ?, status = ?, updated_at = NOW() 
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $data['influencer_id'], $data['title'], $data['description'], $data['budget'], 
                $data['category'], $data['platforms'], $data['target_audience'], $data['status'], $id
            ]);
        } else {
            // Insert
            $sql = "INSERT INTO sponsors 
                    (influencer_id, title, description, budget, category, platforms, 
                     target_audience, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $data['influencer_id'], $data['title'], $data['description'], $data['budget'],
                $data['category'], $data['platforms'], $data['target_audience'], $data['status']
            ]);
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Errore salvataggio sponsor: " . $e->getMessage());
        return false;
    }
}

/**
 * Conta gli sponsor per stato
 */
function getSponsorsCount($status = null) {
    global $pdo;
    
    $sql = "SELECT COUNT(*) FROM sponsors WHERE deleted_at IS NULL";
    
    if ($status) {
        $sql .= " AND status = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    
    return $stmt->fetchColumn();
}

/**
 * Ottiene tutti gli influencer per i dropdown
 */
function getAllInfluencers() {
    global $pdo;
    
    $sql = "SELECT id, name, email 
            FROM users 
            WHERE user_type = 'influencer' AND deleted_at IS NULL AND is_active = 1
            ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Ottiene le categorie disponibili per gli sponsor
 */
function getSponsorCategories() {
    return [
        'Fashion',
        'Lifestyle',
        'Beauty & Makeup',
        'Food',
        'Travel',
        'Gaming',
        'Fitness & Wellness',
        'Entertainment',
        'Tech',
        'Finance & Business',
        'Pet',
        'Education'
    ];
}

/**
 * Elimina definitivamente uno sponsor (HARD DELETE)
 */
function hardDeleteSponsor($sponsor_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Recupera informazioni sullo sponsor prima di eliminare
        $stmt = $pdo->prepare("SELECT * FROM sponsors WHERE id = ?");
        $stmt->execute([$sponsor_id]);
        $sponsor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sponsor) {
            throw new Exception("Sponsor non trovato");
        }
        
        // Elimina l'immagine fisica se esiste
        if (!empty($sponsor['image_url'])) {
            deleteSponsorImage($sponsor['image_url']);
        }
        
        // Elimina lo sponsor dal database
        $stmt = $pdo->prepare("DELETE FROM sponsors WHERE id = ?");
        $result = $stmt->execute([$sponsor_id]);
        
        $pdo->commit();
        return $result;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Errore eliminazione sponsor $sponsor_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Elimina l'immagine dello sponsor dal filesystem
 */
function deleteSponsorImage($image_url) {
    if (empty($image_url)) {
        return;
    }
    
    $base_path = dirname(__DIR__) . '/';
    
    // Estrae il percorso del file dall'URL
    $parsed_url = parse_url($image_url);
    $file_path = $parsed_url['path'] ?? '';
    
    if (empty($file_path)) {
        return;
    }
    
    // Rimuove il leading slash se presente
    if (strpos($file_path, '/') === 0) {
        $file_path = substr($file_path, 1);
    }
    
    $full_path = $base_path . $file_path;
    
    if (file_exists($full_path) && is_file($full_path)) {
        try {
            unlink($full_path);
            error_log("Eliminata immagine sponsor: $full_path");
        } catch (Exception $e) {
            error_log("Errore eliminazione immagine sponsor $full_path: " . $e->getMessage());
        }
    }
    
    // Elimina anche le thumbnail se esistono
    $filename = pathinfo($file_path, PATHINFO_FILENAME);
    $extension = pathinfo($file_path, PATHINFO_EXTENSION);
    $directory = pathinfo($file_path, PATHINFO_DIRNAME);
    
    $thumbnail_patterns = [
        $base_path . $directory . '/thumb_*' . $filename . '*',
        $base_path . $directory . '/small_*' . $filename . '*',
        $base_path . $directory . '/medium_*' . $filename . '*'
    ];
    
    foreach ($thumbnail_patterns as $pattern) {
        foreach (glob($pattern) as $thumbnail) {
            if (file_exists($thumbnail) && is_file($thumbnail)) {
                try {
                    unlink($thumbnail);
                    error_log("Eliminata thumbnail sponsor: $thumbnail");
                } catch (Exception $e) {
                    error_log("Errore eliminazione thumbnail sponsor $thumbnail: " . $e->getMessage());
                }
            }
        }
    }
}

/**
 * Conta gli sponsor totali di un influencer (attivi, bozze, eliminati)
 */
function countInfluencerTotalSponsors($influencer_id) {
    global $pdo;
    
    try {
        // Usa la tabella influencers per ottenere l'ID influencer
        // L'ID passato è user_id, quindi dobbiamo ottenere l'ID dalla tabella influencers
        $sql = "SELECT 
                (SELECT COUNT(*) FROM sponsors WHERE influencer_id = ?) as total_sponsors";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$influencer_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['total_sponsors'] : 0;
    } catch (PDOException $e) {
        error_log("Errore conteggio sponsor totali: " . $e->getMessage());
        return 0;
    }
}

/**
 * Conta gli sponsor attivi di un influencer
 */
function countInfluencerActiveSponsors($influencer_id) {
    global $pdo;
    
    try {
        // Conta solo sponsor con status = 'active' e non eliminati
        $sql = "SELECT COUNT(*) 
                FROM sponsors 
                WHERE influencer_id = ? 
                AND status = 'active' 
                AND deleted_at IS NULL";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$influencer_id]);
        
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Errore conteggio sponsor attivi: " . $e->getMessage());
        return 0;
    }
}

// =============================================================================
// FUNZIONI PER RICHIESTE INFORMAZIONI SPONSOR
// =============================================================================

/**
 * Crea una richiesta di informazioni per uno sponsor
 */
function createSponsorInfoRequest($sponsor_id, $admin_id, $influencer_id, $message) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO sponsor_info_requests 
                (sponsor_id, admin_id, influencer_id, message, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$sponsor_id, $admin_id, $influencer_id, $message]);
        
    } catch (PDOException $e) {
        error_log("Errore creazione richiesta informazioni sponsor: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottiene le richieste di informazioni per uno sponsor
 */
function getSponsorInfoRequests($sponsor_id, $status = null) {
    global $pdo;
    
    try {
        $sql = "SELECT sir.*, a.username as admin_name
                FROM sponsor_info_requests sir
                LEFT JOIN admins a ON sir.admin_id = a.id
                WHERE sir.sponsor_id = ?";
        
        $params = [$sponsor_id];
        
        if ($status) {
            $sql .= " AND sir.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY sir.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Errore recupero richieste informazioni sponsor: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica se ci sono richieste pendenti per uno sponsor
 */
function hasPendingInfoRequests($sponsor_id) {
    global $pdo;
    
    try {
        $sql = "SELECT COUNT(*) as count 
                FROM sponsor_info_requests 
                WHERE sponsor_id = ? AND status = 'pending'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sponsor_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['count'] > 0 : false;
        
    } catch (PDOException $e) {
        error_log("Errore verifica richieste pendenti sponsor: " . $e->getMessage());
        return false;
    }
}

/**
 * Aggiorna la risposta a una richiesta di informazioni
 */
function updateSponsorInfoRequest($request_id, $response) {
    global $pdo;
    
    try {
        $sql = "UPDATE sponsor_info_requests 
                SET response = ?, status = 'replied', 
                    replied_at = NOW(), updated_at = NOW() 
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$response, $request_id]);
        
    } catch (PDOException $e) {
        error_log("Errore aggiornamento risposta sponsor: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottiene una richiesta di informazioni specifica
 */
function getSponsorInfoRequest($request_id) {
    global $pdo;
    
    try {
        $sql = "SELECT sir.*, a.username as admin_name, s.title as sponsor_title,
                       u.name as influencer_name, u.email as influencer_email
                FROM sponsor_info_requests sir
                LEFT JOIN admins a ON sir.admin_id = a.id
                JOIN sponsors s ON sir.sponsor_id = s.id
                JOIN influencers i ON sir.influencer_id = i.id
                JOIN users u ON i.user_id = u.id
                WHERE sir.id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$request_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Errore recupero richiesta informazioni: " . $e->getMessage());
        return null;
    }
}

/**
 * Ottiene l'ID dell'influencer da uno sponsor
 */
function getInfluencerIdFromSponsor($sponsor_id) {
    global $pdo;
    
    try {
        $sql = "SELECT influencer_id FROM sponsors WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sponsor_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['influencer_id'] : null;
        
    } catch (PDOException $e) {
        error_log("Errore recupero influencer_id da sponsor: " . $e->getMessage());
        return null;
    }
}
?>