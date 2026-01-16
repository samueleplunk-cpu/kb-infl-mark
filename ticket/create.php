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
    $attachment_path = null;
    
    // Validazione campi base
    if (empty($subject)) {
        $error = "Il soggetto è obbligatorio";
    } elseif (strlen($subject) > 255) {
        $error = "Il soggetto è troppo lungo (max 255 caratteri)";
    } elseif (empty($message)) {
        $error = "Il messaggio è obbligatorio";
    } elseif (strlen($message) > 5000) {
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
        
        // Se non ci sono errori, crea il ticket
        if (empty($error)) {
            // Crea ticket con allegato (se presente)
            $ticket_id = create_ticket($user_id, $user_type, $subject, $message, $priority, $attachment_path);
            
            if ($ticket_id) {
                $success = "Ticket creato con successo! ID: #" . $ticket_id;
                // Reindirizza dopo 2 secondi
                header("refresh:2;url=view.php?id=" . $ticket_id);
            } else {
                $error = "Errore nella creazione del ticket. Riprova.";
            }
        }
    }
}
?>
<?php include dirname(__DIR__) . '/includes/header.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Nuovo ticket di supporto</h4>
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
                        <form method="POST" id="ticketForm" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="subject" class="form-label">Oggetto *</label>
                                <input type="text" class="form-control" id="subject" name="subject" 
                                       value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                                       maxlength="255" required>
                                <div class="char-counter mt-1">
                                    <span id="subjectCounter">0</span>/255 caratteri
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priorità *</label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="low" <?php echo ($_POST['priority'] ?? 'medium') === 'low' ? 'selected' : ''; ?>>Bassa</option>
                                    <option value="medium" <?php echo ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Media</option>
                                    <option value="high" <?php echo ($_POST['priority'] ?? 'medium') === 'high' ? 'selected' : ''; ?>>Alta</option>
                                    <option value="urgent" <?php echo ($_POST['priority'] ?? 'medium') === 'urgent' ? 'selected' : ''; ?>>Urgente</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Messaggio *</label>
                                <textarea class="form-control" id="message" name="message" 
                                          rows="8" maxlength="5000" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                <div class="char-counter mt-1">
                                    <span id="messageCounter">0</span>/5000 caratteri
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="attachment" class="form-label">Allegato</label>
                                <input type="file" class="form-control" id="attachment" name="attachment" 
                                       accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i> Puoi allegare un file (max 2 MB). Tipi supportati: JPG, PNG, PDF.
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle"></i> Prima di inviare:</h6>
                                <ul class="mb-0">
                                    <li>Verifica di aver incluso tutte le informazioni necessarie</li>
                                    <li>Controlla la <a href="<?php echo BASE_URL; ?>/faq.php" target="_blank">sezione FAQ</a> per vedere se esiste già una soluzione</li>
                                    <li>Il nostro supporto risponderà entro 24-48 ore lavorative</li>
                                </ul>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="index.php" class="btn btn-outline-secondary me-md-2">Annulla</a>
                                <button type="submit" class="btn btn-primary">Crea ticket</button>
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
    
    // Validazione dimensione file client-side
    document.getElementById('ticketForm').addEventListener('submit', function(e) {
        const message = messageInput.value.trim();
        if (message.length < 10) {
            e.preventDefault();
            alert('Il messaggio deve contenere almeno 10 caratteri.');
            messageInput.focus();
            return;
        }
        
        // Validazione dimensione file
        const fileInput = document.getElementById('attachment');
        if (fileInput.files.length > 0) {
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