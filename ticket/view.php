<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once __DIR__ . '/includes/ticket_functions.php';

// Verifica login
if (!is_logged_in()) {
    header("Location: /auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$ticket_id = intval($_GET['id'] ?? 0);

// Verifica accesso al ticket
if (!$ticket_id || !can_access_ticket($ticket_id, $user_id, $user_type)) {
    header("Location: index.php?error=access_denied");
    exit();
}

// Ottieni informazioni ticket
$ticket = get_ticket($ticket_id);
if (!$ticket) {
    header("Location: index.php?error=ticket_not_found");
    exit();
}

// Ottieni messaggi
$messages = get_ticket_messages($ticket_id);

// Gestione nuova risposta
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $new_message = trim($_POST['message'] ?? '');
    $attachment_path = null;
    
    if (empty($new_message)) {
        $error = "Il messaggio non può essere vuoto";
    } elseif (strlen($new_message) > 5000) {
        $error = "Il messaggio è troppo lungo (max 5000 caratteri)";
    } else {
        // Gestione allegato se presente
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            
            // Validazione dimensione file (max 2 MB)
            $max_size = 2 * 1024 * 1024; // 2 MB in bytes
            if ($file['size'] > $max_size) {
                $error = "Il file allegato è troppo grande (dimensione massima: 2 MB)";
            } else {
                // Validazione tipo MIME
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
                    $error = "Tipo di file non supportato. Sono consentiti: immagini, PDF, documenti Word/Excel, file di testo";
                } else {
                    // Crea directory uploads se non esiste
                    $upload_dir = BASE_DIR . '/uploads/tickets/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    // Genera nome file unico
                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $file_name = uniqid('ticket_', true) . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    
                    // Sposta il file nella directory di upload
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        $attachment_path = 'uploads/tickets/' . $file_name;
                    } else {
                        $error = "Errore nel salvataggio del file allegato";
                    }
                }
            }
        } elseif (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Gestione errori di upload
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'Il file supera la dimensione massima consentita dal server',
                UPLOAD_ERR_FORM_SIZE => 'Il file supera la dimensione massima specificata nel form',
                UPLOAD_ERR_PARTIAL => 'Il file è stato caricato solo parzialmente',
                UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea mancante',
                UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file su disco',
                UPLOAD_ERR_EXTENSION => 'Upload bloccato da un\'estensione PHP'
            ];
            
            $error_code = $_FILES['attachment']['error'];
            $error = isset($upload_errors[$error_code]) ? $upload_errors[$error_code] : 'Errore sconosciuto durante l\'upload del file';
        }
        
        // Se non ci sono errori, aggiungi la risposta con allegato (se presente)
        if (empty($error)) {
            // Aggiorna stato del ticket se specificato (solo per admin)
            if ($user_type === 'admin' && isset($_POST['status'])) {
                update_ticket_status($ticket_id, $_POST['status']);
            }
            
            // Aggiungi la risposta
            if (add_ticket_reply($ticket_id, $user_id, $user_type, $new_message, $attachment_path)) {
                $success = "Risposta inviata con successo";
                // Ricarica i messaggi
                $messages = get_ticket_messages($ticket_id);
                $ticket = get_ticket($ticket_id);
            } else {
                $error = "Errore nell'invio della risposta";
            }
        }
    }
}

// Segna notifiche relative a questo ticket come lette
try {
    global $pdo;
    $stmt = $pdo->prepare("
        UPDATE ticket_notifications 
        SET is_read = TRUE 
        WHERE ticket_id = ? 
        AND (user_id = ? AND user_type = ?)
    ");
    $stmt->execute([$ticket_id, $user_id, $user_type]);
} catch (PDOException $e) {
    error_log("Error marking notifications as read: " . $e->getMessage());
}
?>
<?php include dirname(__DIR__) . '/includes/header.php'; ?>

<div class="container py-4">
    <!-- Breadcrumb rimosso come richiesto -->
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0"><?php echo htmlspecialchars($ticket['subject']); ?></h4>
                        <!-- Rimossa la riga con data e creatore -->
                    </div>
                    <!-- Rimosso il badge dello stato dal titolo -->
                </div>
                
                <div class="card-body">
                    <!-- Lista messaggi -->
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-chat-dots display-1 text-muted"></i>
                            <p class="mt-2">Nessun messaggio trovato</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message-card">
                                <div class="message-header d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($message['user_name']); ?></strong>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y - H:i', strtotime($message['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="message-body">
                                    <div class="message-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                    
                                    <?php if (!empty($message['attachment'])): ?>
                                        <div class="mt-3">
                                            <?php
                                            // Estrai il nome file dal percorso
                                            $attachment_path = $message['attachment'];
                                            $original_filename = basename($attachment_path);
                                            
                                            // Decodifica il nome file per rimuovere il prefisso
                                            $decoded_filename = $original_filename;
                                            if (preg_match('/^ticket_[^_]+_[^_]+_(.+)$/', $original_filename, $matches)) {
                                                $decoded_filename = $matches[1];
                                            } elseif (preg_match('/^ticket_/', $original_filename)) {
                                                $decoded_filename = substr($original_filename, 7);
                                            }
                                            
                                            // FIX: URL corretto per gli allegati in produzione
                                            // Usa URL assoluto fisso invece di BASE_URL per evitare il problema /httpdocs/
                                            $download_url = 'https://kibbiz.com/' . htmlspecialchars($attachment_path);
                                            ?>
                                            <a href="<?php echo $download_url; ?>" 
                                               class="badge bg-light text-dark attachment-badge text-decoration-none" 
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
                    
                    <!-- Form risposta (solo se ticket non chiuso) -->
                    <?php if (!in_array($ticket['status'], ['closed', 'resolved'])): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">Aggiungi risposta</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                                <?php endif; ?>
                                
                                <?php if ($success): ?>
                                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                                <?php endif; ?>
                                
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
                                    
                                    <!-- Aggiunto menu selezione allegati -->
                                    <div class="mb-4">
                                        <label for="attachment" class="form-label">Allegato</label>
                                        <input type="file" class="form-control" id="attachment" name="attachment" 
                                               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                        <div class="form-text">
                                            <i class="bi bi-info-circle"></i> Puoi allegare un file (max 2 MB). Tipi supportati: JPG, PNG, PDF.
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <?php if ($user_type === 'admin'): ?>
                                                <select name="status" class="form-select form-select-sm" style="width: auto;">
                                                    <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Apri</option>
                                                    <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Elaborazione</option>
                                                    <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Segna come Risolto</option>
                                                    <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Chiudi</option>
                                                </select>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-send"></i> Invia risposta
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
                                Questo ticket è stato chiuso. Se hai bisogno di ulteriore assistenza, 
                                <a href="create.php">crea un nuovo ticket</a>.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Sezione Informazioni spostata prima di Azioni Ticket -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Informazioni</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Creato da:</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars($ticket['user_name']); ?></dd>
                        
                        <dt class="col-sm-5">Stato:</dt>
                        <dd class="col-sm-7">
                            <?php 
                                $status_names = [
                                    'open' => 'Aperto',
                                    'in_progress' => 'In Elaborazione',
                                    'resolved' => 'Risolto',
                                    'closed' => 'Chiuso'
                                ];
                                echo $status_names[$ticket['status']] ?? $ticket['status'];
                            ?>
                        </dd>
                        
                        <dt class="col-sm-5">ID Ticket:</dt>
                        <dd class="col-sm-7">#<?php echo $ticket_id; ?></dd>
                        
                        <dt class="col-sm-5">Priorità:</dt>
                        <dd class="col-sm-7">
                            <?php 
                                $priority_names = [
                                    'low' => 'Bassa',
                                    'medium' => 'Media',
                                    'high' => 'Alta',
                                    'urgent' => 'Urgente'
                                ];
                                echo $priority_names[$ticket['priority']] ?? $ticket['priority'];
                            ?>
                        </dd>
                        
                        <dt class="col-sm-5">Numero messaggi:</dt>
                        <dd class="col-sm-7"><?php echo count($messages); ?></dd>
                        
                        <dt class="col-sm-5">Creato il:</dt>
                        <dd class="col-sm-7"><?php echo date('d/m/Y - H:i', strtotime($ticket['created_at'])); ?></dd>
                        
                        <dt class="col-sm-5">Aggiornato il:</dt>
                        <dd class="col-sm-7"><?php echo date('d/m/Y - H:i', strtotime($ticket['updated_at'])); ?></dd>
                    </dl>
                </div>
            </div>
            
            <!-- Sezione Azioni Ticket spostata dopo Informazioni -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Azioni Ticket</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Torna alla lista
                        </a>
                        
                        <?php if (!in_array($ticket['status'], ['closed', 'resolved'])): ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#resolveModal">
                                <i class="bi bi-check-circle"></i> Segna come risolto
                            </button>
                            
                            <?php if ($user_type !== 'admin'): ?>
                                <a href="close.php?id=<?php echo $ticket_id; ?>" class="btn btn-outline-danger" 
                                   onclick="return confirm('Sei sicuro di voler chiudere questo ticket?');">
                                    <i class="bi bi-x-circle"></i> Chiudi ticket
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <!-- Rimosso completamente il pulsante "Riapri Ticket" per ticket closed/resolved -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal per segnare come risolto -->
<div class="modal fade" id="resolveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Segna come risolto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Sei sicuro di voler segnare questo ticket come risolto?</p>
                <p>Il ticket sarà chiuso e non sarà più possibile aggiungere nuove risposte.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <a href="close.php?id=<?php echo $ticket_id; ?>&status=resolved" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Segna come risolto
                </a>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
    // Funzione reopenTicket rimossa completamente poiché il pulsante non esiste più
    
    // Auto-scroll all'ultimo messaggio
    window.addEventListener('load', function() {
        const messages = document.querySelectorAll('.message-card');
        if (messages.length > 0) {
            messages[messages.length - 1].scrollIntoView({ behavior: 'smooth' });
        }
    });
    
    // Validazione dimensione file client-side per il form di risposta
    document.addEventListener('DOMContentLoaded', function() {
        const replyForm = document.querySelector('form[enctype="multipart/form-data"]');
        if (replyForm) {
            replyForm.addEventListener('submit', function(e) {
                const messageInput = document.getElementById('message');
                const message = messageInput.value.trim();
                
                // Validazione lunghezza messaggio
                if (message.length < 1) {
                    e.preventDefault();
                    alert('Il messaggio non può essere vuoto.');
                    messageInput.focus();
                    return;
                }
                
                // Validazione dimensione file
                const fileInput = document.getElementById('attachment');
                if (fileInput && fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    const maxSize = 2 * 1024 * 1024; // 2 MB in bytes
                    
                    if (file.size > maxSize) {
                        e.preventDefault();
                        alert('Il file è troppo grande. La dimensione massima consentita è 2 MB.');
                        fileInput.focus();
                        return;
                    }
                    
                    // Validazione tipo file
                    const allowedExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.pdf', '.doc', '.docx', '.xls', '.xlsx', '.txt', '.csv'];
                    const fileName = file.name.toLowerCase();
                    const isValidExtension = allowedExtensions.some(ext => fileName.endsWith(ext));
                    
                    if (!isValidExtension) {
                        e.preventDefault();
                        alert('Tipo di file non supportato. Sono consentiti: immagini, PDF, documenti Word/Excel, file di testo.');
                        fileInput.focus();
                    }
                }
            });
        }
    });
</script>

<style>
    .message-card {
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border: 1px solid #dee2e6;
    }
    .message-header {
        border-bottom: 1px solid #dee2e6;
        padding: 0.75rem 1rem;
        background-color: #f8f9fa;
    }
    .message-body {
        padding: 1rem;
    }
    .user-type-badge {
        font-size: 0.75rem;
        padding: 0.2rem 0.5rem;
        border-radius: 0.25rem;
    }
    .badge-brand { background-color: #007bff; color: white; }
    .badge-influencer { background-color: #28a745; color: white; }
    .badge-admin { background-color: #6c757d; color: white; }
    .ticket-status-badge {
        font-size: 0.9rem;
        padding: 0.35rem 0.7rem;
    }
    .attachment-badge {
        cursor: pointer;
    }
    .attachment-badge:hover {
        background-color: #e9ecef !important;
    }
    .message-text {
        white-space: pre-wrap;
        word-wrap: break-word;
    }
</style>
</body>
</html>