<?php
/**
 * Funzioni per la gestione delle comunicazioni admin
 * 
 * @package CommunicationFunctions
 */

/**
 * Recupera tutte le comunicazioni per un tipo di utente
 * 
 * @param PDO $pdo Connessione al database
 * @param string $user_type Tipo di utente ('influencer' o 'brand')
 * @param bool $include_inactive Include anche le comunicazioni disattivate
 * @return array Array di comunicazioni
 */
function get_admin_communications($pdo, $user_type, $include_inactive = false) {
    $sql = "SELECT * FROM admin_communications 
            WHERE user_type = :user_type 
            AND deleted_at IS NULL";
    
    if (!$include_inactive) {
        $sql .= " AND is_active = 1";
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_type' => $user_type]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Aggiunge una nuova comunicazione
 * 
 * @param PDO $pdo Connessione al database
 * @param string $user_type Tipo di utente ('influencer' o 'brand')
 * @param string $message Messaggio della comunicazione
 * @param string|null $link Link opzionale
 * @return bool True se successo, false altrimenti
 */
function add_admin_communication($pdo, $user_type, $message, $link = null) {
    $sql = "INSERT INTO admin_communications (user_type, message, link, created_at, updated_at) 
            VALUES (:user_type, :message, :link, NOW(), NOW())";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':user_type' => $user_type,
        ':message' => trim($message),
        ':link' => !empty($link) ? trim($link) : null
    ]);
}

/**
 * Elimina (soft delete) una comunicazione
 * 
 * @param PDO $pdo Connessione al database
 * @param int $id ID della comunicazione
 * @return bool True se successo, false altrimenti
 */
function delete_admin_communication($pdo, $id) {
    $sql = "UPDATE admin_communications 
            SET deleted_at = NOW() 
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':id' => $id]);
}

/**
 * Attiva/disattiva una comunicazione
 * 
 * @param PDO $pdo Connessione al database
 * @param int $id ID della comunicazione
 * @param bool $is_active Nuovo stato (true = attiva, false = disattiva)
 * @return bool True se successo, false altrimenti
 */
function toggle_admin_communication($pdo, $id, $is_active) {
    $sql = "UPDATE admin_communications 
            SET is_active = :is_active, updated_at = NOW() 
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':id' => $id,
        ':is_active' => $is_active ? 1 : 0
    ]);
}

/**
 * Recupera una comunicazione specifica
 * 
 * @param PDO $pdo Connessione al database
 * @param int $id ID della comunicazione
 * @return array|null Array con i dati della comunicazione o null se non trovata
 */
function get_admin_communication($pdo, $id) {
    $sql = "SELECT * FROM admin_communications 
            WHERE id = :id AND deleted_at IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Conta le comunicazioni attive per un tipo di utente
 * 
 * @param PDO $pdo Connessione al database
 * @param string $user_type Tipo di utente ('influencer' o 'brand')
 * @return int Numero di comunicazioni attive
 */
function count_active_admin_communications($pdo, $user_type) {
    $sql = "SELECT COUNT(*) as count 
            FROM admin_communications 
            WHERE user_type = :user_type 
            AND is_active = 1 
            AND deleted_at IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_type' => $user_type]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($result['count'] ?? 0);
}

/**
 * Modifica una comunicazione esistente
 * 
 * @param PDO $pdo Connessione al database
 * @param int $id ID della comunicazione
 * @param string $message Nuovo messaggio
 * @param string|null $link Nuovo link
 * @return bool True se successo, false altrimenti
 */
function update_admin_communication($pdo, $id, $message, $link = null) {
    $sql = "UPDATE admin_communications 
            SET message = :message, 
                link = :link, 
                updated_at = NOW() 
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':id' => $id,
        ':message' => trim($message),
        ':link' => !empty($link) ? trim($link) : null
    ]);
}

/**
 * Recupera le comunicazioni recenti (ultime 7 giorni)
 * 
 * @param PDO $pdo Connessione al database
 * @param string $user_type Tipo di utente ('influencer' o 'brand')
 * @param int $days Giorni da considerare (default 7)
 * @return array Array di comunicazioni recenti
 */
function get_recent_admin_communications($pdo, $user_type, $days = 7) {
    $sql = "SELECT * FROM admin_communications 
            WHERE user_type = :user_type 
            AND is_active = 1 
            AND deleted_at IS NULL 
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_type' => $user_type,
        ':days' => $days
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Verifica se esiste già una comunicazione simile (per evitare duplicati)
 * 
 * @param PDO $pdo Connessione al database
 * @param string $user_type Tipo di utente
 * @param string $message Messaggio da verificare
 * @param int $hours_range Ore entro cui considerare un messaggio simile (default 24)
 * @return bool True se esiste già una comunicazione simile, false altrimenti
 */
function similar_communication_exists($pdo, $user_type, $message, $hours_range = 24) {
    $sql = "SELECT COUNT(*) as count 
            FROM admin_communications 
            WHERE user_type = :user_type 
            AND message LIKE :message 
            AND deleted_at IS NULL 
            AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_type' => $user_type,
        ':message' => '%' . $message . '%',
        ':hours' => $hours_range
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($result['count'] ?? 0) > 0;
}

/**
 * Pulisce le comunicazioni nascoste dalla sessione utente
 * Rimuove le comunicazioni che non esistono più nel database
 * 
 * @param PDO $pdo Connessione al database
 * @param array $hidden_comms Array degli ID delle comunicazioni nascoste
 * @return array Array pulito degli ID delle comunicazioni nascoste
 */
function clean_hidden_communications($pdo, $hidden_comms) {
    if (empty($hidden_comms)) {
        return [];
    }
    
    // Crea una stringa di placeholder per la query
    $placeholders = implode(',', array_fill(0, count($hidden_comms), '?'));
    
    $sql = "SELECT id FROM admin_communications 
            WHERE id IN ($placeholders) 
            AND deleted_at IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($hidden_comms);
    $valid_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Restituisci solo gli ID validi (intersezione)
    return array_intersect($hidden_comms, $valid_ids);
}

/**
 * Formatta una data per la visualizzazione
 * 
 * @param string $date Data in formato MySQL
 * @param bool $show_time Se mostrare anche l'ora
 * @return string Data formattata
 */
function format_communication_date($date, $show_time = true) {
    if (empty($date)) {
        return 'N/A';
    }
    
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    // Se meno di 24 ore fa, mostra "X ore fa" o "X minuti fa"
    if ($diff < 86400) {
        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes <= 1 ? 'poco fa' : $minutes . ' minuti fa';
        }
        $hours = floor($diff / 3600);
        return $hours == 1 ? '1 ora fa' : $hours . ' ore fa';
    }
    
    // Se meno di 7 giorni fa, mostra "X giorni fa"
    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days == 1 ? 'ieri' : $days . ' giorni fa';
    }
    
    // Altrimenti mostra la data formattata
    $format = $show_time ? 'd/m/Y H:i' : 'd/m/Y';
    return date($format, $timestamp);
}

/**
 * Valida un link URL
 * 
 * @param string $url URL da validare
 * @return bool True se l'URL è valido, false altrimenti
 */
function validate_communication_url($url) {
    if (empty($url)) {
        return true; // Il link è opzionale
    }
    
    // Verifica il formato dell'URL
    $pattern = '/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/i';
    if (!preg_match($pattern, $url)) {
        return false;
    }
    
    return true;
}

/**
 * Sanitizza il messaggio per la sicurezza
 * 
 * @param string $message Messaggio da sanitizzare
 * @return string Messaggio sanitizzato
 */
function sanitize_communication_message($message) {
    // Rimuovi tag HTML pericolosi ma mantieni i break line
    $message = strip_tags($message, '<br><strong><em><u><a><code>');
    
    // Limita la lunghezza a 500 caratteri
    if (strlen($message) > 500) {
        $message = substr($message, 0, 497) . '...';
    }
    
    return trim($message);
}

/**
 * Recupera le statistiche delle comunicazioni
 * 
 * @param PDO $pdo Connessione al database
 * @return array Array con le statistiche
 */
function get_communications_stats($pdo) {
    $sql = "SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive,
                COUNT(CASE WHEN user_type = 'influencer' THEN 1 END) as influencer_count,
                COUNT(CASE WHEN user_type = 'brand' THEN 1 END) as brand_count,
                MIN(created_at) as oldest,
                MAX(created_at) as newest
            FROM admin_communications 
            WHERE deleted_at IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Ripristina una comunicazione eliminata (soft delete)
 * 
 * @param PDO $pdo Connessione al database
 * @param int $id ID della comunicazione
 * @return bool True se successo, false altrimenti
 */
function restore_admin_communication($pdo, $id) {
    $sql = "UPDATE admin_communications 
            SET deleted_at = NULL 
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':id' => $id]);
}
?>