<?php
require_once dirname(__DIR__) . '/includes/admin_header.php';

// Includi funzioni ticket
$ticket_functions_file = dirname(__DIR__) . '/ticket/includes/ticket_functions.php';
if (file_exists($ticket_functions_file)) {
    require_once $ticket_functions_file;
} else {
    die("File funzioni ticket non trovato.");
}

$ticket_id = intval($_GET['id'] ?? 0);

if (!$ticket_id) {
    $_SESSION['error_message'] = "Ticket ID non specificato.";
    header("Location: tickets.php");
    exit();
}

// Gestione modifica/eliminazione messaggi (pattern PRG)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_message'])) {
        // Gestione modifica messaggio
        $message_id = intval($_POST['message_id'] ?? 0);
        $edited_message = trim($_POST['edited_message'] ?? '');
        
        if (empty($edited_message)) {
            $_SESSION['error_message'] = "Il messaggio non può essere vuoto";
        } elseif (strlen($edited_message) > 5000) {
            $_SESSION['error_message'] = "Il messaggio è troppo lungo (max 5000 caratteri)";
        } else {
            // Verifica che il messaggio esista e sia di un admin
            try {
                $stmt = $pdo->prepare("
                    SELECT user_type 
                    FROM ticket_messages 
                    WHERE id = ? AND ticket_id = ?
                ");
                $stmt->execute([$message_id, $ticket_id]);
                $message = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$message || $message['user_type'] !== 'admin') {
                    $_SESSION['error_message'] = "Non puoi modificare questo messaggio";
                } else {
                    // Aggiorna il messaggio
                    $stmt = $pdo->prepare("
                        UPDATE ticket_messages 
                        SET message = ?, updated_at = NOW() 
                        WHERE id = ? AND ticket_id = ?
                    ");
                    if ($stmt->execute([$edited_message, $message_id, $ticket_id])) {
                        $_SESSION['success_message'] = "Messaggio modificato con successo";
                    } else {
                        $_SESSION['error_message'] = "Errore nella modifica del messaggio";
                    }
                }
            } catch (PDOException $e) {
                error_log("Errore modifica messaggio: " . $e->getMessage());
                $_SESSION['error_message'] = "Errore nella modifica del messaggio";
            }
        }
        
        // Pattern PRG: redirect dopo POST
        header("Location: view_ticket.php?id=" . $ticket_id);
        exit();
    }
    
    if (isset($_POST['delete_message'])) {
        // Gestione eliminazione messaggio
        $message_id = intval($_POST['message_id'] ?? 0);
        
        // Verifica che il messaggio esista e sia di un admin
        try {
            $stmt = $pdo->prepare("
                SELECT user_type 
                FROM ticket_messages 
                WHERE id = ? AND ticket_id = ?
            ");
            $stmt->execute([$message_id, $ticket_id]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$message || $message['user_type'] !== 'admin') {
                $_SESSION['error_message'] = "Non puoi eliminare questo messaggio";
            } else {
                // Ottieni informazioni sull'allegato se presente
                $stmt = $pdo->prepare("
                    SELECT attachment 
                    FROM ticket_messages 
                    WHERE id = ? AND ticket_id = ?
                ");
                $stmt->execute([$message_id, $ticket_id]);
                $message_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Elimina il messaggio
                $stmt = $pdo->prepare("
                    DELETE FROM ticket_messages 
                    WHERE id = ? AND ticket_id = ?
                ");
                if ($stmt->execute([$message_id, $ticket_id])) {
                    // Se c'è un allegato, elimina anche il file
                    if (!empty($message_data['attachment'])) {
                        $file_path = dirname(__DIR__) . '/' . $message_data['attachment'];
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                    $_SESSION['success_message'] = "Messaggio eliminato con successo";
                } else {
                    $_SESSION['error_message'] = "Errore nell'eliminazione del messaggio";
                }
            }
        } catch (PDOException $e) {
            error_log("Errore eliminazione messaggio: " . $e->getMessage());
            $_SESSION['error_message'] = "Errore nell'eliminazione del messaggio";
        }
        
        // Pattern PRG: redirect dopo POST
        header("Location: view_ticket.php?id=" . $ticket_id);
        exit();
    }
    
    // GESTIONE FORM RISPOSTA CON PATTERN PRG
    if (isset($_POST['submit_reply'])) {
        // Aggiungi risposta
        $new_message = trim($_POST['message'] ?? '');
        $attachment_path = null;
        
        if (empty($new_message)) {
            $_SESSION['error_message'] = "Il messaggio non può essere vuoto";
        } elseif (strlen($new_message) > 5000) {
            $_SESSION['error_message'] = "Il messaggio è troppo lungo (max 5000 caratteri)";
        } else {
            // Gestione allegato
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['attachment'];
                $max_size = 2 * 1024 * 1024; // 2 MB
                
                if ($file['size'] > $max_size) {
                    $_SESSION['error_message'] = "Il file allegato è troppo grande (dimensione massima: 2 MB)";
                } else {
                    $allowed_mime_types = [
                        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
                        'application/pdf',
                        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/plain', 'text/csv'
                    ];
                    
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    
                    if (!in_array($mime_type, $allowed_mime_types)) {
                        $_SESSION['error_message'] = "Tipo di file non supportato.";
                    } else {
                        // Crea directory uploads
                        $upload_dir = BASE_DIR . '/uploads/tickets/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $file_name = uniqid('ticket_', true) . '_' . time() . '.' . $file_extension;
                        $file_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($file['tmp_name'], $file_path)) {
                            $attachment_path = 'uploads/tickets/' . $file_name;
                        } else {
                            $_SESSION['error_message'] = "Errore nel salvataggio del file allegato";
                        }
                    }
                }
            }
            
            // Se non ci sono errori, aggiungi la risposta
            if (empty($_SESSION['error_message'])) {
                // Aggiorna stato del ticket se specificato
                if (isset($_POST['status'])) {
                    update_ticket_status($ticket_id, $_POST['status']);
                }
                
                // Aggiungi la risposta come admin
                $admin_id = $_SESSION['user_id'];
                if (add_ticket_reply($ticket_id, $admin_id, 'admin', $new_message, $attachment_path)) {
                    $_SESSION['success_message'] = "Risposta inviata con successo";
                } else {
                    $_SESSION['error_message'] = "Errore nell'invio della risposta";
                }
            }
        }
        
        // Pattern PRG - redirect dopo l'invio della risposta
        header("Location: view_ticket.php?id=" . $ticket_id);
        exit();
    }
    
    // Gestione aggiornamento stato con pattern PRG
    if (isset($_POST['update_status'])) {
        $new_status = $_POST['status'] ?? '';
        if (in_array($new_status, ['open', 'in_progress', 'resolved', 'closed'])) {
            if (update_ticket_status($ticket_id, $new_status)) {
                $_SESSION['success_message'] = "Stato del ticket aggiornato con successo.";
            } else {
                $_SESSION['error_message'] = "Errore nell'aggiornamento dello stato.";
            }
        }
        header("Location: view_ticket.php?id=" . $ticket_id);
        exit();
    }
}

// Ottieni informazioni ticket (admin può vedere tutti i ticket)
try {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               CASE 
                 WHEN t.user_type = 'brand' THEN b.company_name
                 WHEN t.user_type = 'influencer' THEN i.full_name
                 ELSE 'Utente'
               END as user_name,
               u.email as user_email
        FROM tickets t
        LEFT JOIN brands b ON t.user_id = b.user_id AND t.user_type = 'brand'
        LEFT JOIN influencers i ON t.user_id = i.user_id AND t.user_type = 'influencer'
        LEFT JOIN users u ON (t.user_type = 'brand' AND b.user_id = u.id) OR (t.user_type = 'influencer' AND i.user_id = u.id)
        WHERE t.id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        $_SESSION['error_message'] = "Ticket non trovato.";
        header("Location: tickets.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Errore nel recupero del ticket: " . $e->getMessage());
    $_SESSION['error_message'] = "Errore nel caricamento del ticket.";
    header("Location: tickets.php");
    exit();
}

// Ottieni messaggi del ticket
try {
    $stmt = $pdo->prepare("
        SELECT tm.*,
               CASE 
                 WHEN tm.user_type = 'brand' THEN b.company_name
                 WHEN tm.user_type = 'influencer' THEN i.full_name
                 WHEN tm.user_type = 'admin' THEN 'Supporto Kibbiz'
                 ELSE 'Utente'
               END as user_name
        FROM ticket_messages tm
        LEFT JOIN brands b ON tm.user_id = b.user_id AND tm.user_type = 'brand'
        LEFT JOIN influencers i ON tm.user_id = i.user_id AND tm.user_type = 'influencer'
        WHERE tm.ticket_id = ?
        ORDER BY tm.created_at ASC
    ");
    $stmt->execute([$ticket_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Errore nel recupero dei messaggi: " . $e->getMessage());
    $messages = [];
}

// Funzioni helper per display - time_ago() è già definita in ticket_functions.php
function get_status_badge($status) {
    $status_names = [
        'open' => 'Aperto',
        'in_progress' => 'In lavorazione',
        'resolved' => 'Risolto',
        'closed' => 'Chiuso'
    ];
    
    return $status_names[$status] ?? $status;
}

function get_priority_text($priority) {
    $priority_names = [
        'low' => 'Bassa',
        'medium' => 'Media',
        'high' => 'Alta',
        'urgent' => 'Urgente'
    ];
    return $priority_names[$priority] ?? $priority;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Header senza breadcrumb -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0"><?php echo htmlspecialchars($ticket['subject']); ?></h1>
                </div>
                <div>
                    <a href="/admin/tickets.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Torna alla lista
                    </a>
                </div>
            </div>

            <!-- Messaggi di errore/successo -->
            <?php 
            if (isset($_SESSION['error_message'])): 
                $error = $_SESSION['error_message'];
                unset($_SESSION['error_message']);
            ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php 
            if (isset($_SESSION['success_message'])): 
                $success = $_SESSION['success_message'];
                unset($_SESSION['success_message']);
            ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success) && $success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Colonna principale - Conversazione -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Conversazione</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($messages)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-chat-dots display-1 text-muted"></i>
                                    <p class="mt-2">Nessun messaggio trovato</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $message): ?>
                                    <?php 
                                    // Determinare se il messaggio è dell'admin o dell'utente
                                    $is_admin_message = ($message['user_type'] === 'admin');
                                    ?>
                                    <div class="message-card mb-4 <?php echo $is_admin_message ? 'admin-message' : 'user-message'; ?>" id="message-<?php echo $message['id']; ?>">
                                        <div class="message-header d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($message['user_name']); ?></strong>
                                                <!-- MODIFICATO: rimosso completamente i badge della tipologia utente -->
                                            </div>
                                            <div class="d-flex align-items-center">
    <small class="text-muted me-3">
        <?php echo date('d/m/Y - H:i', strtotime($message['created_at'])); ?>
        <span class="ms-1">(<?php echo time_ago($message['created_at']); ?>)</span>
        <?php if (!empty($message['updated_at']) && $message['updated_at'] != $message['created_at']): ?>
            <br><small class="text-muted">(modificato)</small>
        <?php endif; ?>
    </small>
    <?php if ($is_admin_message): ?>
        <!-- Icone modifica/elimina solo per messaggi admin -->
        <div class="message-actions">
            <!-- Icona modifica -->
            <button type="button" class="btn btn-sm btn-outline-secondary edit-message-btn" 
                    data-message-id="<?php echo $message['id']; ?>"
                    data-message-text="<?php echo htmlspecialchars($message['message']); ?>"
                    title="Modifica messaggio">
                <i class="fas fa-edit"></i>
            </button>
            
            <!-- Icona elimina -->
            <button type="button" class="btn btn-sm btn-outline-danger delete-message-btn ms-1" 
                    data-message-id="<?php echo $message['id']; ?>"
                    title="Elimina messaggio">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    <?php endif; ?>
</div>
                                        </div>
                                        <div class="message-body mt-2">
                                            <div class="message-text" id="message-text-<?php echo $message['id']; ?>">
                                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                            </div>
                                            
                                            <?php if (!empty($message['attachment'])): ?>
                                                <div class="mt-2">
                                                    <?php
                                                    $attachment_path = $message['attachment'];
                                                    $original_filename = basename($attachment_path);
                                                    
                                                    $decoded_filename = $original_filename;
                                                    if (preg_match('/^ticket_[^_]+_[^_]+_(.+)$/', $original_filename, $matches)) {
                                                        $decoded_filename = $matches[1];
                                                    } elseif (preg_match('/^ticket_/', $original_filename)) {
                                                        $decoded_filename = substr($original_filename, 7);
                                                    }
                                                    
                                                    $download_url = 'https://kibbiz.com/' . htmlspecialchars($attachment_path);
                                                    ?>
                                                    <a href="<?php echo $download_url; ?>" 
                                                       class="badge bg-light text-dark border text-decoration-none" 
                                                       download="<?php echo htmlspecialchars($decoded_filename); ?>">
                                                        <i class="bi bi-paperclip"></i> 
                                                        <?php echo htmlspecialchars($decoded_filename); ?>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <!-- Form modifica messaggio (nascosto di default) -->
                            <div id="edit-message-form" class="card mt-4" style="display: none;">
                                <div class="card-header">
                                    <h5 class="mb-0">Modifica Messaggio</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="edit-form">
                                        <input type="hidden" name="message_id" id="edit-message-id">
                                        <input type="hidden" name="edit_message" value="1">
                                        
                                        <div class="mb-3">
                                            <label for="edited_message" class="form-label">Messaggio</label>
                                            <textarea class="form-control" id="edited_message" name="edited_message" 
                                                      rows="4" maxlength="5000" required></textarea>
                                            <small class="text-muted">
                                                Massimo 5000 caratteri.
                                            </small>
                                        </div>
                                        
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-secondary" id="cancel-edit">
                                                Annulla
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                Salva Modifiche
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Form risposta (solo se ticket non chiuso) -->
                            <?php if (!in_array($ticket['status'], ['closed', 'resolved'])): ?>
                                <div class="card mt-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Rispondi al ticket</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label for="message" class="form-label">Il tuo messaggio</label>
                                                <textarea class="form-control" id="message" name="message" 
                                                          rows="4" maxlength="5000" required
                                                          placeholder="Scrivi la tua risposta qui..."></textarea>
                                                <small class="text-muted">
                                                    Massimo 5000 caratteri.
                                                </small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="attachment" class="form-label">Allegato (opzionale)</label>
                                                <input type="file" class="form-control" id="attachment" name="attachment" 
                                                       accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv">
                                                <small class="text-muted">
                                                    Dimensione massima: 2 MB. Tipi supportati: immagini, PDF, documenti Word/Excel, file di testo.
                                                </small>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <label for="status" class="form-label me-2">Aggiorna stato:</label>
                                                    <select name="status" id="status" class="form-select form-select-sm d-inline-block" style="width: auto;">
                                                        <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Aperto</option>
                                                        <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Lavorazione</option>  <!-- MODIFICATO: "In Elaborazione" → "In Lavorazione" -->
                                                        <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Segna come Risolto</option>
                                                        <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Chiudi</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <button type="submit" name="submit_reply" class="btn btn-primary">
                                                        Invia risposta
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mt-4">
                                    <h5><i class="bi bi-info-circle"></i> Ticket Chiuso</h5>
                                    <p class="mb-0">
                                        Questo ticket è stato chiuso. Puoi comunque visualizzare la conversazione.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Colonna laterale - Informazioni e azioni -->
                <div class="col-lg-4">
                    <!-- Informazioni ticket -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Informazioni Ticket</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-5">ID Ticket:</dt>
                                <dd class="col-sm-7">#<?php echo $ticket_id; ?></dd>
                                
                                <dt class="col-sm-5">Creato da:</dt>
                                <dd class="col-sm-7">
                                    <?php echo htmlspecialchars($ticket['user_name']); ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo $ticket['user_type']; ?> 
                                        <?php if (!empty($ticket['user_email'])): ?>
                                            - <?php echo $ticket['user_email']; ?>
                                        <?php endif; ?>
                                    </small>
                                </dd>
                                
                                <dt class="col-sm-5">Stato attuale:</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars(get_status_badge($ticket['status'])); ?></dd>
                                
                                <dt class="col-sm-5">Priorità:</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars(get_priority_text($ticket['priority'])); ?></dd>
                                
                                <dt class="col-sm-5">Messaggi:</dt>
                                <dd class="col-sm-7"><?php echo count($messages); ?></dd>
                                
                                <dt class="col-sm-5">Creato il:</dt>
                                <dd class="col-sm-7">
                                    <?php echo date('d/m/Y - H:i', strtotime($ticket['created_at'])); ?>
                                    <br>
                                    <small class="text-muted">(<?php echo time_ago($ticket['created_at']); ?>)</small>
                                </dd>
                                
                                <dt class="col-sm-5">Aggiornato il:</dt>
                                <dd class="col-sm-7">
                                    <?php echo date('d/m/Y - H:i', strtotime($ticket['updated_at'])); ?>
                                    <br>
                                    <small class="text-muted">(<?php echo time_ago($ticket['updated_at']); ?>)</small>
                                </dd>
                            </dl>
                        </div>
                    </div>
                    
                    <!-- Azioni rapide -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Azioni Rapide</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="mb-3">
                                <div class="mb-3">
                                    <label for="quick_status" class="form-label">Cambia stato:</label>
                                    <select name="status" id="quick_status" class="form-select" onchange="if(confirm('Cambiare stato del ticket?')){this.form.submit();}">
                                        <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Aperto</option>
                                        <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Lavorazione</option>  <!-- MODIFICATO: "In Elaborazione" → "In Lavorazione" -->
                                        <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Segna come Risolto</option>
                                        <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Chiudi</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </div>
                            </form>
                            
                            <div class="d-grid gap-2">
                                <a href="/admin/tickets.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-list me-1"></i> Torna alla lista
                                </a>
                                
                                <?php if ($ticket['status'] === 'closed' || $ticket['status'] === 'resolved'): ?>
                                    <form method="POST" class="d-grid">
                                        <input type="hidden" name="status" value="open">
                                        <input type="hidden" name="update_status" value="1">
                                        <button type="submit" class="btn btn-success" onclick="return confirm('Riaprire il ticket?');">
                                            <i class="fas fa-redo me-1"></i> Riapri Ticket
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal di conferma eliminazione -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Conferma Eliminazione</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Sei sicuro di voler eliminare questo messaggio? Questa azione non può essere annullata.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <form method="POST" id="delete-form">
                    <input type="hidden" name="message_id" id="delete-message-id">
                    <input type="hidden" name="delete_message" value="1">
                    <button type="submit" class="btn btn-danger">Elimina</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-scroll all'ultimo messaggio
document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.message-card');
    if (messages.length > 0) {
        messages[messages.length - 1].scrollIntoView({ behavior: 'smooth' });
    }
    
    // Validazione file client-side
    const fileInput = document.getElementById('attachment');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                const maxSize = 2 * 1024 * 1024; // 2MB
                
                if (file.size > maxSize) {
                    alert('Il file è troppo grande. Dimensione massima: 2 MB.');
                    this.value = '';
                }
                
                // Validazione tipo file
                const allowedExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.pdf', '.doc', '.docx', '.xls', '.xlsx', '.txt', '.csv'];
                const fileName = file.name.toLowerCase();
                const isValidExtension = allowedExtensions.some(ext => fileName.endsWith(ext));
                
                if (!isValidExtension) {
                    alert('Tipo di file non supportato. Tipi consentiti: immagini, PDF, documenti Word/Excel, file di testo.');
                    this.value = '';
                }
            }
        });
    }
    
    // Gestione modifica messaggio
    const editButtons = document.querySelectorAll('.edit-message-btn');
    const editForm = document.getElementById('edit-message-form');
    const editMessageId = document.getElementById('edit-message-id');
    const editedMessage = document.getElementById('edited_message');
    const cancelEdit = document.getElementById('cancel-edit');
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const messageId = this.getAttribute('data-message-id');
            const messageText = this.getAttribute('data-message-text');
            
            // Nascondi il form di risposta se visibile
            const replyForm = document.querySelector('form[action*="view_ticket"]:not(#edit-form):not(#delete-form)');
            if (replyForm && replyForm.closest('.card')) {
                replyForm.closest('.card').style.display = 'none';
            }
            
            // Mostra il form di modifica
            editMessageId.value = messageId;
            editedMessage.value = messageText;
            editForm.style.display = 'block';
            
            // Scroll al form di modifica
            editForm.scrollIntoView({ behavior: 'smooth' });
        });
    });
    
    // Annulla modifica
    cancelEdit.addEventListener('click', function() {
        editForm.style.display = 'none';
        
        // Mostra di nuovo il form di risposta se era nascosto
        const replyForm = document.querySelector('form[action*="view_ticket"]:not(#edit-form):not(#delete-form)');
        if (replyForm && replyForm.closest('.card')) {
            replyForm.closest('.card').style.display = 'block';
        }
    });
    
    // Gestione eliminazione messaggio
    const deleteButtons = document.querySelectorAll('.delete-message-btn');
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    const deleteMessageId = document.getElementById('delete-message-id');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const messageId = this.getAttribute('data-message-id');
            deleteMessageId.value = messageId;
            deleteModal.show();
        });
    });
    
    // Convalida form modifica
    const editFormElement = document.getElementById('edit-form');
    editFormElement.addEventListener('submit', function(e) {
        const message = editedMessage.value.trim();
        if (message.length === 0) {
            e.preventDefault();
            alert('Il messaggio non può essere vuoto');
        } else if (message.length > 5000) {
            e.preventDefault();
            alert('Il messaggio è troppo lungo (max 5000 caratteri)');
        }
    });
});
</script>

<style>
.message-card {
    border-radius: 8px;
    border: 1px solid #dee2e6;
    max-width: 80%;
    margin-right: auto;
    margin-left: 0;
}
.admin-message {
    margin-left: auto;
    margin-right: 0;
    background-color: #f0f7ff;
    border-color: #cce5ff;
}
.user-message {
    background-color: #f8f9fa;
}
.message-header {
    border-bottom: 1px solid #dee2e6;
    padding: 0.75rem 1rem;
    background-color: rgba(0,0,0,0.02);
}
.admin-message .message-header {
    background-color: rgba(0,123,255,0.05);
    border-bottom-color: #cce5ff;
}
.message-body {
    padding: 1rem;
}
.message-text {
    white-space: pre-wrap;
    word-wrap: break-word;
    text-align: left !important;
    padding: 1rem !important;
    margin: 0 !important;
    width: 100% !important;
    background-color: transparent !important;
}
.admin-message .message-text {
    background-color: transparent !important;
}
.message-actions {
    opacity: 1;
    transition: opacity 0.2s ease;
}

/* Rimuove qualsiasi centratura del testo */
.message-text * {
    text-align: left !important;
}

/* Responsive: su schermi piccoli, usa tutta la larghezza */
@media (max-width: 768px) {
    .message-card {
        max-width: 95%;
    }
}
</style>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>