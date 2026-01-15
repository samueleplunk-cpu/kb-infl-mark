<?php

/**
 * Verifica se l'utente può accedere al ticket
 */
function can_access_ticket($ticket_id, $user_id, $user_type) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as can_access 
            FROM tickets 
            WHERE id = ? 
            AND user_id = ? 
            AND user_type = ?
        ");
        $stmt->execute([$ticket_id, $user_id, $user_type]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['can_access'] > 0;
    } catch (PDOException $e) {
        error_log("can_access_ticket error: " . $e->getMessage());
        return false;
    }
}

/**
 * Crea un nuovo ticket
 */
function create_ticket($user_id, $user_type, $subject, $message, $priority = 'medium') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tickets (user_id, user_type, subject, message, priority, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $user_type,
            $subject,
            $message,
            $priority
        ]);
        
        $ticket_id = $pdo->lastInsertId();
        
        // Aggiungi il messaggio iniziale come primo messaggio
        $stmt_msg = $pdo->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, user_type, message, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt_msg->execute([$ticket_id, $user_id, $user_type, $message]);
        
        // Crea notifica per lo staff
        create_ticket_notification($ticket_id, "Nuovo ticket creato: " . $subject, null, null);
        
        return $ticket_id;
    } catch (PDOException $e) {
        error_log("create_ticket error: " . $e->getMessage());
        return false;
    }
}

/**
 * Aggiungi risposta a un ticket
 */
function add_ticket_reply($ticket_id, $user_id, $user_type, $message, $attachment = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, user_type, message, attachment, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$ticket_id, $user_id, $user_type, $message, $attachment]);
        
        // Aggiorna timestamp del ticket
        $stmt_update = $pdo->prepare("
            UPDATE tickets SET updated_at = NOW() WHERE id = ?
        ");
        $stmt_update->execute([$ticket_id]);
        
        // Crea notifica
        $user_info = get_user_info($user_id, $user_type);
        $user_name = $user_info['name'] ?? 'Utente';
        create_ticket_notification($ticket_id, "Nuova risposta da " . $user_name, $user_id, $user_type);
        
        return true;
    } catch (PDOException $e) {
        error_log("add_ticket_reply error: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottieni informazioni ticket
 */
function get_ticket($ticket_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   CASE 
                     WHEN t.user_type = 'brand' THEN b.company_name
                     WHEN t.user_type = 'influencer' THEN i.full_name
                     ELSE 'Utente'
                   END as user_name
            FROM tickets t
            LEFT JOIN brands b ON t.user_id = b.user_id AND t.user_type = 'brand'
            LEFT JOIN influencers i ON t.user_id = i.user_id AND t.user_type = 'influencer'
            WHERE t.id = ?
        ");
        
        $stmt->execute([$ticket_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_ticket error: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottieni messaggi di un ticket
 */
function get_ticket_messages($ticket_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT tm.*,
                   CASE 
                     WHEN tm.user_type = 'brand' THEN b.company_name
                     WHEN tm.user_type = 'influencer' THEN i.full_name
                     WHEN tm.user_type = 'admin' THEN 'Staff Supporto'
                     ELSE 'Utente'
                   END as user_name
            FROM ticket_messages tm
            LEFT JOIN brands b ON tm.user_id = b.user_id AND tm.user_type = 'brand'
            LEFT JOIN influencers i ON tm.user_id = i.user_id AND tm.user_type = 'influencer'
            WHERE tm.ticket_id = ?
            ORDER BY tm.created_at ASC
        ");
        
        $stmt->execute([$ticket_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_ticket_messages error: " . $e->getMessage());
        return [];
    }
}

/**
 * Ottieni tutti i ticket di un utente
 */
function get_user_tickets($user_id, $user_type, $status = null, $limit = 50) {
    global $pdo;
    
    try {
        $sql = "
            SELECT t.*,
                   COUNT(tm.id) as message_count,
                   MAX(tm.created_at) as last_message_date
            FROM tickets t
            LEFT JOIN ticket_messages tm ON t.id = tm.ticket_id
            WHERE t.user_id = ? AND t.user_type = ?
        ";
        
        $params = [$user_id, $user_type];
        
        // LOGICA MOLTO CHIARA:
        // Solo se $status è specificato E non è 'all'
        if ($status && $status !== 'all') {
            if ($status === 'open') {
                $sql .= " AND (t.status = 'open' OR t.status = 'in_progress')";
            } elseif ($status === 'closed') {
                $sql .= " AND (t.status = 'closed' OR t.status = 'resolved')";
            } else {
                $sql .= " AND t.status = ?";
                $params[] = $status;
            }
        }
        
        $sql .= " GROUP BY t.id ORDER BY t.updated_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_user_tickets error: " . $e->getMessage());
        return [];
    }
}

/**
 * Aggiorna stato del ticket
 */
function update_ticket_status($ticket_id, $status) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE tickets 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([$status, $ticket_id]);
        
        // Crea notifica per cambio stato
        $status_names = [
            'open' => 'aperto',
            'in_progress' => 'in elaborazione',
            'closed' => 'chiuso',
            'resolved' => 'risolto'
        ];
        
        create_ticket_notification(
            $ticket_id, 
            "Stato del ticket cambiato a: " . ($status_names[$status] ?? $status),
            null, 
            null
        );
        
        return true;
    } catch (PDOException $e) {
        error_log("update_ticket_status error: " . $e->getMessage());
        return false;
    }
}

/**
 * Crea una notifica per il ticket
 */
function create_ticket_notification($ticket_id, $message, $user_id = null, $user_type = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ticket_notifications (ticket_id, user_id, user_type, message, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$ticket_id, $user_id, $user_type, $message]);
        return true;
    } catch (PDOException $e) {
        error_log("create_ticket_notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottieni notifiche ticket non lette per l'utente
 * Rinominata da get_unread_notifications() per evitare conflitto con sistema notifiche principale
 */
function get_unread_ticket_notifications($user_id = null, $user_type = null) {
    global $pdo;
    
    try {
        if ($user_id && $user_type) {
            // Notifiche specifiche per l'utente
            $stmt = $pdo->prepare("
                SELECT n.*, t.subject 
                FROM ticket_notifications n
                JOIN tickets t ON n.ticket_id = t.id
                WHERE (n.user_id = ? AND n.user_type = ?) 
                   OR (n.user_id IS NULL AND n.user_type IS NULL)
                AND n.is_read = FALSE
                ORDER BY n.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$user_id, $user_type]);
        } else {
            // Notifiche globali (per admin/staff)
            $stmt = $pdo->prepare("
                SELECT n.*, t.subject 
                FROM ticket_notifications n
                JOIN tickets t ON n.ticket_id = t.id
                WHERE n.is_read = FALSE
                ORDER BY n.created_at DESC
                LIMIT 20
            ");
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("get_unread_ticket_notifications error: " . $e->getMessage());
        return [];
    }
}

/**
 * Segna notifiche come lette
 */
function mark_notifications_as_read($notification_ids = [], $user_id = null, $user_type = null) {
    global $pdo;
    
    try {
        if (!empty($notification_ids)) {
            $placeholders = implode(',', array_fill(0, count($notification_ids), '?'));
            $stmt = $pdo->prepare("
                UPDATE ticket_notifications 
                SET is_read = TRUE 
                WHERE id IN ($placeholders)
            ");
            $stmt->execute($notification_ids);
        } else if ($user_id && $user_type) {
            $stmt = $pdo->prepare("
                UPDATE ticket_notifications 
                SET is_read = TRUE 
                WHERE user_id = ? AND user_type = ?
            ");
            $stmt->execute([$user_id, $user_type]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("mark_notifications_as_read error: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottieni informazioni utente
 */
function get_user_info($user_id, $user_type) {
    global $pdo;
    
    try {
        if ($user_type === 'brand') {
            $stmt = $pdo->prepare("
                SELECT company_name as name, email 
                FROM brands b 
                JOIN users u ON b.user_id = u.id 
                WHERE b.user_id = ?
            ");
        } else if ($user_type === 'influencer') {
            $stmt = $pdo->prepare("
                SELECT full_name as name, email 
                FROM influencers i 
                JOIN users u ON i.user_id = u.id 
                WHERE i.user_id = ?
            ");
        } else {
            return ['name' => 'Staff', 'email' => ''];
        }
        
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['name' => 'Utente', 'email' => ''];
    }
}

/**
 * Conta ticket aperti
 */
function count_open_tickets($user_id = null, $user_type = null) {
    global $pdo;
    
    try {
        if ($user_id && $user_type) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM tickets 
                WHERE user_id = ? 
                AND user_type = ? 
                AND status IN ('open', 'in_progress')
            ");
            $stmt->execute([$user_id, $user_type]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM tickets 
                WHERE status IN ('open', 'in_progress')
            ");
            $stmt->execute();
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Formatta il tempo trascorso in modo leggibile
 */
function time_ago($timestamp) {
    if (empty($timestamp)) {
        return 'mai';
    }
    
    $now = new DateTime();
    $past = new DateTime($timestamp);
    $diff = $now->diff($past);
    
    if ($diff->y > 0) {
        return $diff->y == 1 ? '1 anno fa' : $diff->y . ' anni fa';
    } elseif ($diff->m > 0) {
        return $diff->m == 1 ? '1 mese fa' : $diff->m . ' mesi fa';
    } elseif ($diff->d > 0) {
        return $diff->d == 1 ? '1 giorno fa' : $diff->d . ' giorni fa';
    } elseif ($diff->h > 0) {
        return $diff->h == 1 ? '1 ora fa' : $diff->h . ' ore fa';
    } elseif ($diff->i > 0) {
        return $diff->i == 1 ? '1 minuto fa' : $diff->i . ' minuti fa';
    } else {
        return 'pochi secondi fa';
    }
}
?>