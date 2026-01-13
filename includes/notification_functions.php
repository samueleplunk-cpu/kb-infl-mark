<?php
// /includes/notification_functions.php

/**
 * Sistema di notifiche di base
 */

/**
 * Crea una nuova notifica
 */
function create_notification($pdo, $user_id, $user_type, $notification_type, $data = []) {
    try {
        // Ottieni il tipo di notifica
        $stmt = $pdo->prepare("SELECT * FROM notification_types WHERE name = ?");
        $stmt->execute([$notification_type]);
        $notification_type_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$notification_type_data) {
            error_log("Tipo di notifica non trovato: " . $notification_type);
            return false;
        }
        
        // Verifica se l'utente ha abilitato questa notifica
        $pref_stmt = $pdo->prepare("
            SELECT enabled 
            FROM notification_preferences 
            WHERE user_id = ? AND user_type = ? AND notification_type_id = ?
        ");
        $pref_stmt->execute([$user_id, $user_type, $notification_type_data['id']]);
        $preference = $pref_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Se non esiste preferenza, usa il default
        $is_enabled = $preference ? (bool)$preference['enabled'] : (bool)$notification_type_data['default_enabled'];
        
        if (!$is_enabled) {
            return false; // Notifica disabilitata per questo utente
        }
        
        // Prepara titolo e messaggio
        $title = process_notification_template($notification_type_data['default_title_template'], $data);
        $message = process_notification_template($notification_type_data['default_message_template'], $data);
        
        // Inserisci la notifica
        $insert_stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, user_type, notification_type_id, title, message)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insert_stmt->execute([
            $user_id,
            $user_type,
            $notification_type_data['id'],
            $title,
            $message
        ]);
        
        return $insert_stmt->rowCount() > 0;
        
    } catch (PDOException $e) {
        error_log("Errore creazione notifica: " . $e->getMessage());
        return false;
    }
}

/**
 * Processa i template delle notifiche sostituendo i placeholder
 */
function process_notification_template($template, $data) {
    $result = $template;
    
    foreach ($data as $key => $value) {
        $placeholder = '{{' . $key . '}}';
        $result = str_replace($placeholder, $value, $result);
    }
    
    return $result;
}

/**
 * Ottiene le notifiche non lette per un utente
 */
function get_unread_notifications($pdo, $user_id, $user_type, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT n.*, nt.name as type_name
            FROM notifications n
            JOIN notification_types nt ON n.notification_type_id = nt.id
            WHERE n.user_id = ? AND n.user_type = ? AND n.is_read = FALSE
            ORDER BY n.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $user_type, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Errore lettura notifiche: " . $e->getMessage());
        return [];
    }
}

/**
 * Ottiene tutte le notifiche per un utente
 */
function get_all_notifications($pdo, $user_id, $user_type, $limit = 20) {
    try {
        $stmt = $pdo->prepare("
            SELECT n.*, nt.name as type_name
            FROM notifications n
            JOIN notification_types nt ON n.notification_type_id = nt.id
            WHERE n.user_id = ? AND n.user_type = ?
            ORDER BY n.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $user_type, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Errore lettura notifiche: " . $e->getMessage());
        return [];
    }
}

/**
 * Conta le notifiche non lette
 */
function count_unread_notifications($pdo, $user_id, $user_type) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM notifications
            WHERE user_id = ? AND user_type = ? AND is_read = FALSE
        ");
        $stmt->execute([$user_id, $user_type]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Errore conteggio notifiche: " . $e->getMessage());
        return 0;
    }
}

/**
 * Segna notifica come letta
 */
function mark_notification_as_read($pdo, $notification_id, $user_id, $user_type) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE id = ? AND user_id = ? AND user_type = ?
        ");
        $stmt->execute([$notification_id, $user_id, $user_type]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Errore aggiornamento notifica: " . $e->getMessage());
        return false;
    }
}

/**
 * Segna tutte le notifiche come lette
 */
function mark_all_notifications_as_read($pdo, $user_id, $user_type) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE 
            WHERE user_id = ? AND user_type = ? AND is_read = FALSE
        ");
        $stmt->execute([$user_id, $user_type]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Errore aggiornamento notifiche: " . $e->getMessage());
        return 0;
    }
}

/**
 * Ottiene le preferenze notifica per un utente
 */
function get_notification_preferences($pdo, $user_id, $user_type) {
    try {
        $stmt = $pdo->prepare("
            SELECT nt.*, 
                   COALESCE(np.enabled, nt.default_enabled) as enabled,
                   COALESCE(np.email_enabled, nt.default_email_enabled) as email_enabled
            FROM notification_types nt
            LEFT JOIN notification_preferences np ON nt.id = np.notification_type_id 
                AND np.user_id = ? AND np.user_type = ?
            ORDER BY nt.name
        ");
        $stmt->execute([$user_id, $user_type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Errore lettura preferenze: " . $e->getMessage());
        return [];
    }
}

/**
 * Aggiorna le preferenze notifica per un utente
 */
function update_notification_preferences($pdo, $user_id, $user_type, $preferences) {
    try {
        $pdo->beginTransaction();
        
        foreach ($preferences as $type_id => $settings) {
            $stmt = $pdo->prepare("
                INSERT INTO notification_preferences (user_id, user_type, notification_type_id, enabled, email_enabled)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    enabled = VALUES(enabled),
                    email_enabled = VALUES(email_enabled),
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $user_id,
                $user_type,
                $type_id,
                $settings['enabled'] ? 1 : 0,
                $settings['email_enabled'] ? 1 : 0
            ]);
        }
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Errore aggiornamento preferenze: " . $e->getMessage());
        return false;
    }
}
?>