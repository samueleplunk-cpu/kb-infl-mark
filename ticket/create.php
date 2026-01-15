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
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    
    // Validazione
    if (empty($subject)) {
        $error = "Il soggetto è obbligatorio";
    } elseif (strlen($subject) > 255) {
        $error = "Il soggetto è troppo lungo (max 255 caratteri)";
    } elseif (empty($message)) {
        $error = "Il messaggio è obbligatorio";
    } elseif (strlen($message) > 5000) {
        $error = "Il messaggio è troppo lungo (max 5000 caratteri)";
    } else {
        // Crea ticket
        $ticket_id = create_ticket($user_id, $user_type, $subject, $message, $priority);
        
        if ($ticket_id) {
            $success = "Ticket creato con successo! ID: #" . $ticket_id;
            // Reindirizza dopo 2 secondi
            header("refresh:2;url=view.php?id=" . $ticket_id);
        } else {
            $error = "Errore nella creazione del ticket. Riprova.";
        }
    }
}
?>
<?php include dirname(__DIR__) . '/includes/header.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Ticket</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Crea Nuovo</li>
                </ol>
            </nav>
            
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Crea Nuovo Ticket di Supporto</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Reindirizzamento...</span>
                            </div>
                            <p class="mt-2">Reindirizzamento al ticket...</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" id="ticketForm">
                            <div class="mb-3">
                                <label for="subject" class="form-label">Soggetto *</label>
                                <input type="text" class="form-control" id="subject" name="subject" 
                                       value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                                       maxlength="255" required>
                                <div class="char-counter mt-1">
                                    <span id="subjectCounter">0</span>/255 caratteri
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priorità</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low" <?php echo ($_POST['priority'] ?? 'medium') === 'low' ? 'selected' : ''; ?>>Bassa</option>
                                    <option value="medium" <?php echo ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Media</option>
                                    <option value="high" <?php echo ($_POST['priority'] ?? 'medium') === 'high' ? 'selected' : ''; ?>>Alta</option>
                                    <option value="urgent" <?php echo ($_POST['priority'] ?? 'medium') === 'urgent' ? 'selected' : ''; ?>>Urgente</option>
                                </select>
                                <small class="text-muted">
                                    <strong>Bassa:</strong> Richiesta generale<br>
                                    <strong>Media:</strong> Problema non critico<br>
                                    <strong>Alta:</strong> Problema che blocca l'uso<br>
                                    <strong>Urgente:</strong> Sistema non funzionante
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Messaggio *</label>
                                <textarea class="form-control" id="message" name="message" 
                                          rows="8" maxlength="5000" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                <div class="char-counter mt-1">
                                    <span id="messageCounter">0</span>/5000 caratteri
                                </div>
                                <small class="text-muted">
                                    Descrivi il tuo problema o richiesta in modo dettagliato. Includi:
                                    <ul>
                                        <li>Cosa stavi cercando di fare</li>
                                        <li>Cosa è successo invece</li>
                                        <li>Quali passaggi hai già provato</li>
                                        <li>Screenshot se disponibili (puoi allegarli dopo)</li>
                                    </ul>
                                </small>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle"></i> Prima di inviare:</h6>
                                <ul class="mb-0">
                                    <li>Verifica di aver incluso tutte le informazioni necessarie</li>
                                    <li>Controlla la <a href="<?php echo BASE_URL; ?>/faq.php" target="_blank">FAQ</a> per vedere se esiste già una soluzione</li>
                                    <li>Il nostro staff risponderà entro 24-48 ore lavorative</li>
                                </ul>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-outline-secondary me-md-2">Annulla</a>
                                    <button type="submit" class="btn btn-primary">Crea Ticket</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
    // Contatori caratteri
    const subjectInput = document.getElementById('subject');
    const messageInput = document.getElementById('message');
    const subjectCounter = document.getElementById('subjectCounter');
    const messageCounter = document.getElementById('messageCounter');
    
    function updateCounter(input, counter, max) {
        const length = input.value.length;
        counter.textContent = length;
        counter.parentElement.classList.toggle('warning', length > max * 0.9);
    }
    
    subjectInput.addEventListener('input', () => updateCounter(subjectInput, subjectCounter, 255));
    messageInput.addEventListener('input', () => updateCounter(messageInput, messageCounter, 5000));
    
    // Inizializza contatori
    updateCounter(subjectInput, subjectCounter, 255);
    updateCounter(messageInput, messageCounter, 5000);
    
    // Conferma invio
    document.getElementById('ticketForm').addEventListener('submit', function(e) {
        const message = messageInput.value.trim();
        if (message.length < 10) {
            e.preventDefault();
            alert('Il messaggio deve contenere almeno 10 caratteri.');
            messageInput.focus();
        }
    });
</script>

<style>
    .form-container {
        max-width: 800px;
        margin: 0 auto;
    }
    .char-counter {
        font-size: 0.875rem;
        color: #6c757d;
    }
    .char-counter.warning {
        color: #dc3545;
    }
</style>
</body>
</html>