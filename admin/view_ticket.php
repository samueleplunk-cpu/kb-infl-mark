<?php
// /admin/view_ticket.php - Pagina admin per visualizzazione ticket
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
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Errore nel recupero dei messaggi: " . $e->getMessage());
    $messages = [];
}

// Gestione form risposta
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['message'])) {
        // Aggiungi risposta
        $new_message = trim($_POST['message'] ?? '');
        $attachment_path = null;
        
        if (empty($new_message)) {
            $error = "Il messaggio non può essere vuoto";
        } elseif (strlen($new_message) > 5000) {
            $error = "Il messaggio è troppo lungo (max 5000 caratteri)";
        } else {
            // Gestione allegato
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['attachment'];
                $max_size = 2 * 1024 * 1024; // 2 MB
                
                if ($file['size'] > $max_size) {
                    $error = "Il file allegato è troppo grande (dimensione massima: 2 MB)";
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
                        $error = "Tipo di file non supportato.";
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
                            $error = "Errore nel salvataggio del file allegato";
                        }
                    }
                }
            }
            
            // Se non ci sono errori, aggiungi la risposta
            if (empty($error)) {
                // Aggiorna stato del ticket se specificato
                if (isset($_POST['status'])) {
                    update_ticket_status($ticket_id, $_POST['status']);
                    $ticket['status'] = $_POST['status'];
                }
                
                // Aggiungi la risposta come admin
                $admin_id = $_SESSION['user_id'];
                if (add_ticket_reply($ticket_id, $admin_id, 'admin', $new_message, $attachment_path)) {
                    $success = "Risposta inviata con successo";
                    // Ricarica i messaggi
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
                    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $error = "Errore nell'invio della risposta";
                }
            }
        }
    } elseif (isset($_POST['update_status'])) {
        // Aggiorna solo lo stato
        $new_status = $_POST['status'] ?? '';
        if (in_array($new_status, ['open', 'in_progress', 'resolved', 'closed'])) {
            if (update_ticket_status($ticket_id, $new_status)) {
                $success = "Stato del ticket aggiornato con successo.";
                $ticket['status'] = $new_status;
            } else {
                $error = "Errore nell'aggiornamento dello stato.";
            }
        }
    }
}

// Funzioni helper per display - time_ago() è già definita in ticket_functions.php
function get_status_badge($status) {
    $status_names = [
        'open' => 'Aperto',
        'in_progress' => 'In elaborazione',
        'resolved' => 'Risolto',
        'closed' => 'Chiuso'
    ];
    
    $colors = [
        'open' => 'success',
        'in_progress' => 'warning',
        'resolved' => 'info',
        'closed' => 'secondary'
    ];
    
    $status_text = $status_names[$status] ?? $status;
    $color = $colors[$status] ?? 'secondary';
    
    return '<span class="badge bg-' . $color . '">' . $status_text . '</span>';
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
            <!-- Header con breadcrumb -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="/admin/dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="/admin/tickets.php">Ticket</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Ticket #<?php echo $ticket_id; ?></li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0 mt-2"><?php echo htmlspecialchars($ticket['subject']); ?></h1>
                </div>
                <div>
                    <a href="/admin/tickets.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Torna alla lista
                    </a>
                </div>
            </div>

            <!-- Messaggi di errore/successo -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
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
                                    <div class="message-card mb-4">
                                        <div class="message-header d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($message['user_name']); ?></strong>
                                                <small class="text-muted ms-2">
                                                    <?php 
                                                    if ($message['user_type'] === 'brand') {
                                                        echo '<span class="badge bg-primary">Brand</span>';
                                                    } elseif ($message['user_type'] === 'influencer') {
                                                        echo '<span class="badge bg-success">Influencer</span>';
                                                    } elseif ($message['user_type'] === 'admin') {
                                                        echo '<span class="badge bg-secondary">Staff</span>';
                                                    }
                                                    ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y - H:i', strtotime($message['created_at'])); ?>
                                                <span class="ms-1">(<?php echo time_ago($message['created_at']); ?>)</span>
                                            </small>
                                        </div>
                                        <div class="message-body mt-2">
                                            <div class="message-text p-3 bg-light rounded">
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
                                                        <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Elaborazione</option>
                                                        <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Segna come Risolto</option>
                                                        <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Chiudi</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <button type="submit" name="submit_reply" class="btn btn-primary">
                                                        <i class="fas fa-paper-plane me-1"></i> Invia risposta
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
                                <dd class="col-sm-7"><?php echo get_status_badge($ticket['status']); ?></dd>
                                
                                <dt class="col-sm-5">Priorità:</dt>
                                <dd class="col-sm-7">
                                    <span class="badge 
                                        <?php 
                                        switch($ticket['priority']) {
                                            case 'low': echo 'bg-secondary'; break;
                                            case 'medium': echo 'bg-info'; break;
                                            case 'high': echo 'bg-warning'; break;
                                            case 'urgent': echo 'bg-danger'; break;
                                            default: echo 'bg-secondary';
                                        }
                                        ?>">
                                        <?php echo get_priority_text($ticket['priority']); ?>
                                    </span>
                                </dd>
                                
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
                                        <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Elaborazione</option>
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
});
</script>

<style>
.message-card {
    border-radius: 8px;
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
.message-text {
    white-space: pre-wrap;
    word-wrap: break-word;
}
</style>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>