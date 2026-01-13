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
    
    if (empty($new_message)) {
        $error = "Il messaggio non può essere vuoto";
    } elseif (strlen($new_message) > 5000) {
        $error = "Il messaggio è troppo lungo (max 5000 caratteri)";
    } else {
        if (add_ticket_reply($ticket_id, $user_id, $user_type, $new_message)) {
            $success = "Risposta inviata con successo";
            // Ricarica i messaggi
            $messages = get_ticket_messages($ticket_id);
            $ticket = get_ticket($ticket_id);
        } else {
            $error = "Errore nell'invio della risposta";
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
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo $ticket_id; ?> - Influencer Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
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
        .message-text {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__) . '/includes/header.php'; ?>
    
    <div class="container py-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Ticket</a></li>
                <li class="breadcrumb-item active" aria-current="page">Ticket #<?php echo $ticket_id; ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0"><?php echo htmlspecialchars($ticket['subject']); ?></h4>
                            <small class="text-muted">
                                Creato il <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?>
                                da <?php echo htmlspecialchars($ticket['user_name'] ?? 'Utente'); ?>
                            </small>
                        </div>
                        <div>
                            <span class="badge bg-secondary me-2">
                                <?php 
                                    $priority_names = [
                                        'low' => 'Bassa',
                                        'medium' => 'Media',
                                        'high' => 'Alta',
                                        'urgent' => 'Urgente'
                                    ];
                                    echo $priority_names[$ticket['priority']] ?? $ticket['priority'];
                                ?>
                            </span>
                            <span class="badge 
                                <?php 
                                    switch($ticket['status']) {
                                        case 'open': echo 'bg-info'; break;
                                        case 'in_progress': echo 'bg-warning'; break;
                                        case 'resolved': echo 'bg-success'; break;
                                        case 'closed': echo 'bg-secondary'; break;
                                        default: echo 'bg-light text-dark';
                                    }
                                ?> ticket-status-badge">
                                <?php 
                                    $status_names = [
                                        'open' => 'Aperto',
                                        'in_progress' => 'In Elaborazione',
                                        'resolved' => 'Risolto',
                                        'closed' => 'Chiuso'
                                    ];
                                    echo $status_names[$ticket['status']] ?? $ticket['status'];
                                ?>
                            </span>
                        </div>
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
                                            <span class="user-type-badge ms-2 badge-<?php echo $message['user_type']; ?>">
                                                <?php 
                                                    $user_type_names = [
                                                        'brand' => 'Brand',
                                                        'influencer' => 'Influencer',
                                                        'admin' => 'Staff'
                                                    ];
                                                    echo $user_type_names[$message['user_type']] ?? $message['user_type'];
                                                ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="message-body">
                                        <div class="message-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                        
                                        <?php if (!empty($message['attachment'])): ?>
                                            <div class="mt-3">
                                                <span class="badge bg-light text-dark attachment-badge">
                                                    <i class="bi bi-paperclip"></i> Allegato
                                                </span>
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
                                    <h5 class="mb-0">Aggiungi Risposta</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($error): ?>
                                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if ($success): ?>
                                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                                    <?php endif; ?>
                                    
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label for="message" class="form-label">Il tuo messaggio</label>
                                            <textarea class="form-control" id="message" name="message" 
                                                      rows="4" maxlength="5000" required
                                                      placeholder="Scrivi la tua risposta qui..."></textarea>
                                            <small class="text-muted">
                                                Massimo 5000 caratteri. Il nostro staff risponderà il prima possibile.
                                            </small>
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
                                                    <i class="bi bi-send"></i> Invia Risposta
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
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Azioni Ticket</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Torna alla Lista
                            </a>
                            
                            <?php if (!in_array($ticket['status'], ['closed', 'resolved'])): ?>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#resolveModal">
                                    <i class="bi bi-check-circle"></i> Segna come Risolto
                                </button>
                                
                                <?php if ($user_type !== 'admin'): ?>
                                    <a href="close.php?id=<?php echo $ticket_id; ?>" class="btn btn-outline-danger" 
                                       onclick="return confirm('Sei sicuro di voler chiudere questo ticket?');">
                                        <i class="bi bi-x-circle"></i> Chiudi Ticket
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-warning" onclick="reopenTicket()">
                                    <i class="bi bi-arrow-clockwise"></i> Riapri Ticket
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Informazioni</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">ID Ticket:</dt>
                            <dd class="col-sm-7">#<?php echo $ticket_id; ?></dd>
                            
                            <dt class="col-sm-5">Creato:</dt>
                            <dd class="col-sm-7"><?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></dd>
                            
                            <dt class="col-sm-5">Ultimo aggiornamento:</dt>
                            <dd class="col-sm-7"><?php echo date('d/m/Y H:i', strtotime($ticket['updated_at'])); ?></dd>
                            
                            <dt class="col-sm-5">Creato da:</dt>
                            <dd class="col-sm-7"><?php echo htmlspecialchars($ticket['user_name']); ?></dd>
                            
                            <dt class="col-sm-5">Tipo utente:</dt>
                            <dd class="col-sm-7">
                                <span class="badge 
                                    <?php echo $ticket['user_type'] === 'brand' ? 'bg-primary' : 'bg-success'; ?>">
                                    <?php echo $ticket['user_type'] === 'brand' ? 'Brand' : 'Influencer'; ?>
                                </span>
                            </dd>
                            
                            <dt class="col-sm-5">Numero messaggi:</dt>
                            <dd class="col-sm-7"><?php echo count($messages); ?></dd>
                        </dl>
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
                    <h5 class="modal-title">Segna come Risolto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Sei sicuro di voler segnare questo ticket come risolto?</p>
                    <p>Il ticket sarà chiuso e non sarà più possibile aggiungere nuove risposte.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <a href="close.php?id=<?php echo $ticket_id; ?>&status=resolved" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Segna come Risolto
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include dirname(__DIR__) . '/includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function reopenTicket() {
            if (confirm('Vuoi riaprire questo ticket?')) {
                window.location.href = 'close.php?id=<?php echo $ticket_id; ?>&status=open';
            }
        }
        
        // Auto-scroll all'ultimo messaggio
        window.addEventListener('load', function() {
            const messages = document.querySelectorAll('.message-card');
            if (messages.length > 0) {
                messages[messages.length - 1].scrollIntoView({ behavior: 'smooth' });
            }
        });
    </script>
</body>
</html>