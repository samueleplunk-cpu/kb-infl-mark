<?php
// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG E FUNZIONI
// =============================================
$config_file = dirname(dirname(__DIR__)) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

$functions_file = dirname(dirname(__DIR__)) . '/includes/functions.php';
if (!file_exists($functions_file)) {
    die("Errore: File funzioni non trovato in: " . $functions_file);
}
require_once $functions_file;

// =============================================
// VERIFICA AUTENTICAZIONE UTENTE
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

if (!is_influencer()) {
    die("Accesso negato: Questa area è riservata agli influencer.");
}

// =============================================
// VERIFICA PARAMETRI
// =============================================
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID conversazione non valido");
}

$conversation_id = intval($_GET['id']);

// =============================================
// RECUPERO INFLUENCER_ID E INFLUENCER_IMAGE
// =============================================
$influencer_id = null;
$influencer_image = null;
$stmt = $pdo->prepare("SELECT id, profile_image FROM influencers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$influencer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$influencer) {
    die("Profilo influencer non trovato. Completa prima il profilo influencer.");
}
$influencer_id = $influencer['id'];
$influencer_image = $influencer['profile_image'];

// =============================================
// RECUPERA CONVERSAZIONE
// =============================================
$stmt = $pdo->prepare("
    SELECT c.*, 
           b.company_name as brand_name,
           b.logo as brand_image,
           b.description as brand_description,
           camp.name as campaign_title,
           camp.id as campaign_id,
           camp.budget as campaign_budget
    FROM conversations c
    LEFT JOIN brands b ON c.brand_id = b.id
    LEFT JOIN campaigns camp ON c.campaign_id = camp.id
    WHERE c.id = ? AND c.influencer_id = ?
");
$stmt->execute([$conversation_id, $influencer_id]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    die("Conversazione non trovata o accesso negato");
}

// =============================================
// VERIFICA SE IL BRAND HA ELIMINATO L'ACCOUNT
// =============================================
$brand_deleted = false;
$brand_deleted_at = null;
if (!empty($conversation['brand_id'])) {
    $stmt = $pdo->prepare("
        SELECT u.is_deleted, u.deleted_at 
        FROM users u 
        INNER JOIN brands b ON b.user_id = u.id 
        WHERE b.id = ? AND u.user_type = 'brand'
    ");
    $stmt->execute([$conversation['brand_id']]);
    $brand_status = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($brand_status) {
        $brand_deleted = ($brand_status['is_deleted'] == 1 || !empty($brand_status['deleted_at']));
        $brand_deleted_at = $brand_status['deleted_at'] ?? null;
    }
}

// =============================================
// VERIFICA SE LA CAMPAGNA ESISTE
// =============================================
$campaign_exists = false;
$campaign_details = null;

if (!empty($conversation['campaign_id'])) {
    $stmt = $pdo->prepare("
        SELECT id, name, budget, status 
        FROM campaigns 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$conversation['campaign_id']]);
    $campaign_details = $stmt->fetch(PDO::FETCH_ASSOC);
    $campaign_exists = ($campaign_details !== false);
}

// =============================================
// FUNZIONE PER VERIFICARE SE UN MESSAGGIO È INVITO
// =============================================
function isInviteMessage($message) {
    // Controlla se il messaggio contiene frasi tipiche di un invito
    $invitePatterns = [
        'vorrei invitarti',
        'ti invito a collaborare',
        'invito alla campagna',
        'collaborazione alla campagna',
        'partecipare alla campagna'
    ];
    
    $messageLower = strtolower($message);
    foreach ($invitePatterns as $pattern) {
        if (strpos($messageLower, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

// =============================================
// VERIFICA SE L'INVITO È STATO RIFIUTATO
// =============================================
$invite_rejected = false;
if (!empty($conversation['campaign_id'])) {
    $reject_stmt = $pdo->prepare("
        SELECT status 
        FROM campaign_influencers 
        WHERE campaign_id = ? AND influencer_id = ? AND status = 'rejected'
    ");
    $reject_stmt->execute([$conversation['campaign_id'], $influencer_id]);
    $invite_rejected = ($reject_stmt->rowCount() > 0);
}

// =============================================
// RECUPERA MESSAGGI
// =============================================
$messages_stmt = $pdo->prepare("
    SELECT m.*, 
           CASE 
               WHEN m.sender_type = 'brand' THEN b.company_name
               WHEN m.sender_type = 'influencer' THEN inf.full_name
           END as sender_name
    FROM messages m
    LEFT JOIN brands b ON m.sender_type = 'brand' AND m.sender_id = b.id
    LEFT JOIN influencers inf ON m.sender_type = 'influencer' AND m.sender_id = inf.id
    WHERE m.conversation_id = ?
    ORDER BY m.sent_at ASC
");
$messages_stmt->execute([$conversation_id]);
$messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// SEGNA I MESSAGGI COME LETTI
// =============================================
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    mark_messages_as_read($pdo, $conversation_id, $_SESSION['user_type'], $_SESSION['user_id']);
}

// =============================================
// GESTIONE INVIO NUOVO MESSAGGIO
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !empty(trim($_POST['message']))) {
    // Controlla se l'invito è stato rifiutato
    if ($invite_rejected) {
        $_SESSION['error_message'] = "Non puoi inviare messaggi dopo aver rifiutato l'invito.";
        header("Location: conversation.php?id=" . $conversation_id);
        exit();
    }
    
    $new_message = trim($_POST['message']);
    
    try {
        $insert_stmt = $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, sender_type, message, sent_at) 
            VALUES (?, ?, 'influencer', ?, NOW())
        ");
        $insert_stmt->execute([$conversation_id, $influencer_id, $new_message]);
        
        // Aggiorna data ultimo aggiornamento conversazione
        $update_stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
        $update_stmt->execute([$conversation_id]);
        
        // Messaggio di successo
        $_SESSION['success_message'] = "Messaggio inviato con successo!";
        
        // Ricarica la pagina per mostrare il nuovo messaggio
        header("Location: conversation.php?id=" . $conversation_id);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Errore nell'invio del messaggio: " . $e->getMessage();
        header("Location: conversation.php?id=" . $conversation_id);
        exit();
    }
}

// =============================================
// GESTIONE RIFIUTO INVITO
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_invite'])) {
    try {
        // Verifica che ci sia una campagna associata
        if (empty($conversation['campaign_id'])) {
            $_SESSION['error_message'] = "Nessuna campagna associata a questa conversazione.";
            header("Location: conversation.php?id=" . $conversation_id);
            exit();
        }
        
        // Verifica che l'influencer possa rifiutare (non abbia già rifiutato)
        $check_stmt = $pdo->prepare("
            SELECT status 
            FROM campaign_influencers 
            WHERE campaign_id = ? AND influencer_id = ?
        ");
        $check_stmt->execute([$conversation['campaign_id'], $influencer_id]);
        $campaign_status = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$campaign_status) {
            $_SESSION['error_message'] = "Nessun invito trovato per questa campagna.";
            header("Location: conversation.php?id=" . $conversation_id);
            exit();
        }
        
        if ($campaign_status['status'] === 'rejected') {
            $_SESSION['error_message'] = "Invito già rifiutato.";
            header("Location: conversation.php?id=" . $conversation_id);
            exit();
        }
        
        // Aggiorna lo stato a 'rejected'
        $update_stmt = $pdo->prepare("
            UPDATE campaign_influencers 
            SET status = 'rejected', updated_at = NOW() 
            WHERE campaign_id = ? AND influencer_id = ?
        ");
        $update_stmt->execute([$conversation['campaign_id'], $influencer_id]);
        
        // Aggiungi un messaggio di sistema per informare il brand
        $system_message = "L'influencer ha rifiutato l'invito alla campagna.";
        
        $message_stmt = $pdo->prepare("
    INSERT INTO messages (conversation_id, sender_id, sender_type, message, sent_at, is_system) 
    VALUES (?, ?, 'system', ?, NOW(), 1)
");
        $message_stmt->execute([$conversation_id, 0, $system_message]);
        
        // Aggiorna timestamp conversazione
        $update_conv_stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
        $update_conv_stmt->execute([$conversation_id]);
        
        header("Location: conversation.php?id=" . $conversation_id);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Errore durante il rifiuto dell'invito: " . $e->getMessage();
        header("Location: conversation.php?id=" . $conversation_id);
        exit();
    }
}

// =============================================
// INCLUSIONE HEADER
// =============================================
$header_file = dirname(dirname(__DIR__)) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Conversazione con <?php echo htmlspecialchars_decode(htmlspecialchars($conversation['brand_name']), ENT_QUOTES); ?></h2>
                <?php if (!empty($conversation['campaign_title'])): ?>
                    <small class="text-muted">Campagna: <?php echo htmlspecialchars($conversation['campaign_title']); ?></small>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <?php if (!empty($conversation['campaign_id']) && $campaign_exists): ?>
                    <a href="/influencers/campaigns/view.php?id=<?php echo $conversation['campaign_id']; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-bullhorn me-1"></i> Dettagli Campagna
                    </a>
                <?php endif; ?>
                <a href="conversation-list.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i> Torna ai Messaggi
                </a>
            </div>
        </div>

        <!-- INFO CONVERSAZIONE -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="card-title">
                            <i class="fas fa-building me-2"></i>Brand
                        </h5>
                        <div class="d-flex align-items-center">
                            <?php 
                            $brand_image = $conversation['brand_image'] ?? '';
                            if (!empty($brand_image)) {
                                $brand_image_path = get_image_path($brand_image, 'brand');
                            } else {
                                $brand_image_path = '/uploads/placeholder/brand_admin_edit.png';
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($brand_image_path); ?>" 
                                 class="rounded-circle me-3" width="60" height="60" alt="Brand Logo" 
                                 style="object-fit: cover;"
                                 onerror="this.onerror=null; this.src='<?php echo get_placeholder_path('brand'); ?>';">
                            <div>
                                <strong class="h6">
                                    <?php echo htmlspecialchars_decode(htmlspecialchars($conversation['brand_name']), ENT_QUOTES); ?>
                                    <?php if ($brand_deleted): ?>
                                        <span class="text-black">(Account eliminato)</span>
                                    <?php endif; ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($conversation['campaign_title'])): ?>
                    <div class="col-md-6">
                        <h5 class="card-title">
                            <i class="fas fa-bullhorn me-2"></i>Campagna
                        </h5>
                        <div>
                            <p class="mb-1"><strong><?php echo htmlspecialchars($conversation['campaign_title']); ?></strong></p>
                            <?php if (!empty($conversation['campaign_budget'])): ?>
                                <p class="mb-1">
                                    <small class="text-muted">
                                        <i class="fas fa-euro-sign me-1"></i>
                                        Budget: <?php echo number_format($conversation['campaign_budget'], 2, ',', '.'); ?>€
                                    </small>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($conversation['campaign_id'])): ?>
                                <small>
                                    <?php if ($campaign_exists): ?>
                                        <a href="/influencers/campaigns/view.php?id=<?php echo $conversation['campaign_id']; ?>" class="text-decoration-none">
                                            <i class="fas fa-external-link-alt me-1"></i>Vedi dettagli completi
                                        </a>
                                    <?php else: ?>
                                        <a href="/influencers/campaigns/list.php?campaign_deleted=1" class="text-decoration-none text-warning">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Campagna non più disponibile
                                        </a>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                            
                            <?php if ($invite_rejected): ?>
                                <div class="alert alert-danger mt-2 p-2" style="font-size: 0.9rem;">
                                    <i class="fas fa-ban me-1"></i>
                                    <strong>Invito rifiutato</strong> - La conversazione è in sola lettura
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- MESSAGGI -->
        <div class="card mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-comments me-2"></i>Messaggi
                    <span class="badge bg-primary ms-2"><?php echo count($messages); ?></span>
                </h5>
                <div class="d-flex gap-2">
                    <?php if (count($messages) > 5): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="scrollToTop()">
                            <i class="fas fa-arrow-up me-1"></i> Inizio
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="scrollToBottom()">
                        <i class="fas fa-arrow-down me-1"></i> Ultimo
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="messages-container" style="max-height: 500px; overflow-y: auto; padding: 1.25rem;">
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">Nessun messaggio ancora</h4>
                            <p class="text-muted">Inizia la conversazione inviando il primo messaggio!</p>
                            <?php if (!empty($conversation['campaign_title'])): ?>
                                <div class="mt-3 p-3 bg-light rounded">
                                    <small class="text-muted">
                                        <i class="fas fa-lightbulb me-1"></i>
                                        <strong>Suggerimento:</strong> Presentati e chiedi informazioni sulla campagna "<?php echo htmlspecialchars($conversation['campaign_title']); ?>"
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <?php 
                            $is_own_message = $message['sender_type'] === 'influencer';
                            $message_class = $is_own_message ? 'text-end' : 'text-start';
                            $bubble_class = $is_own_message ? 'bg-primary text-white' : 'bg-light';
                            $time_class = $is_own_message ? 'text-white-50' : 'text-muted';
                            
                            // Verifica se questo è un messaggio di sistema
                            $is_system_message = isset($message['is_system']) && $message['is_system'] == 1;
                            
                            // Verifica se questo è un messaggio di invito del brand
                            $is_brand_invite = (!$is_own_message && 
                                               $message['sender_type'] === 'brand' && 
                                               !empty($conversation['campaign_id']) && 
                                               isInviteMessage($message['message']) &&
                                               !$is_system_message);
                            
                            // Verifica se l'invito è già stato rifiutato
                            $show_reject_button = ($is_brand_invite && !$invite_rejected && !$is_system_message);
                            ?>
                            
                            <div class="message mb-4 <?php echo $message_class; ?>" id="message-<?php echo $message['id']; ?>">
                                <div class="d-flex <?php echo $is_own_message ? 'justify-content-end' : 'justify-content-start'; ?>">
                                    <div class="message-bubble <?php echo $is_system_message ? 'bg-light border' : $bubble_class; ?> rounded-3 p-3 position-relative" 
                                         style="max-width: 70%; <?php echo $is_system_message ? 'border-left: 4px solid #6c757d; font-style: italic;' : ''; ?>">
                                        <!-- Nome mittente per i messaggi del brand (non per i messaggi di sistema) -->
                                        <?php if (!$is_own_message && !$is_system_message): ?>
                                            <div class="sender-name mb-1">
                                                <strong><?php 
                                                    if ($message['sender_type'] === 'brand') {
                                                        echo htmlspecialchars_decode(htmlspecialchars($message['sender_name']), ENT_QUOTES);
                                                        // Mostra "(Account eliminato)" anche nei messaggi se il brand è eliminato
                                                        if ($brand_deleted): ?>
                                                            <span class="text-black">(Account eliminato)</span>
                                                        <?php endif;
                                                    } else {
                                                        echo htmlspecialchars($message['sender_name']);
                                                    }
                                                ?></strong>
                                            </div>
                                        <?php elseif ($is_system_message): ?>
                                            <div class="sender-name mb-1">
                                                <strong><i class="fas fa-info-circle me-1"></i> Sistema</strong>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Contenuto messaggio -->
                                        <div class="message-text">
                                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                        </div>
                                        
                                        <!-- Pulsante Rifiuta per messaggi di invito -->
                                        <?php if ($show_reject_button): ?>
                                            <div class="mt-3 pt-2 border-top">
                                                <form method="POST" action="" class="reject-form" onsubmit="return confirmRejectInvite();">
                                                    <input type="hidden" name="reject_invite" value="1">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        <i class="fas fa-times me-1"></i> Rifiuta Invito
                                                    </button>
                                                    <small class="text-muted ms-2">
                                                        Rifiutando, la conversazione verrà chiusa
                                                    </small>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Data e ora -->
                                        <div class="message-time mt-2">
                                            <small class="<?php echo $time_class; ?>">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('d/m/Y H:i', strtotime($message['sent_at'])); ?>
                                                
                                                <?php if ($is_own_message && $message['is_read']): ?>
                                                    <i class="fas fa-check-double ms-2 text-success" title="Messaggio letto"></i>
                                                <?php elseif ($is_own_message): ?>
                                                    <i class="fas fa-check ms-2" title="Messaggio inviato"></i>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- FORM INVIO MESSAGGIO -->
        <div class="card">
            <div class="card-body">
                <?php if ($invite_rejected): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-ban me-2"></i>
                        <strong>Invito rifiutato</strong> - Non puoi più inviare messaggi in questa conversazione.
                    </div>
                <?php elseif ($brand_deleted): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-user-slash me-2"></i>
                        <strong>Account brand eliminato</strong> - Non puoi inviare messaggi.
                    </div>
                <?php else: ?>
                    <form method="POST" action="" id="message-form">
                        <div class="input-group">
                            <textarea name="message" class="form-control" placeholder="Scrivi il tuo messaggio..." 
                                      rows="3" required id="message-input" 
                                      placeholder="Scrivi il tuo messaggio...<?php echo empty($messages) ? ' Presentati e chiedi informazioni sulla collaborazione!' : ''; ?>"></textarea>
                            <button type="submit" class="btn btn-primary" id="send-button">
                                <i class="fas fa-paper-plane me-1"></i> Invia
                            </button>
                        </div>
                        <div class="mt-2 d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Premi Invio per inviare, Shift+Invio per andare a capo
                            </small>
                            <small class="text-muted" id="char-count">0/1000 caratteri</small>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.message-bubble {
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    border: 1px solid rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.bg-primary.message-bubble {
    border: none;
}

.message-bubble:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.message-text {
    line-height: 1.5;
    word-wrap: break-word;
}

.message-time {
    font-size: 0.75rem;
    opacity: 0.8;
}

.sender-name {
    font-size: 0.85rem;
    opacity: 0.9;
}

#messages-container {
    scroll-behavior: smooth;
    background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);
}

/* Scrollbar personalizzata */
#messages-container::-webkit-scrollbar {
    width: 8px;
}

#messages-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

#messages-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 10px;
}

#messages-container::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Pulsante rifiuto */
.reject-form {
    background: rgba(220, 53, 69, 0.05);
    border-radius: 5px;
    padding: 8px;
    margin-top: 10px;
}

.reject-form button {
    transition: all 0.3s ease;
}

.reject-form button:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3);
}

/* Messaggio di sistema */
.system-message {
    background-color: #f8f9fa;
    border-left: 4px solid #6c757d;
    padding: 10px;
    margin: 10px 0;
    font-style: italic;
    color: #6c757d;
}

.char-count-warning {
    color: #dc3545;
    font-weight: bold;
}
</style>

<script>
// Scroll automatico all'ultimo messaggio
function scrollToBottom() {
    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Scroll al primo messaggio
function scrollToTop() {
    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = 0;
    }
}

// Contatore caratteri
function updateCharCount() {
    const messageInput = document.getElementById('message-input');
    const charCount = document.getElementById('char-count');
    const maxChars = 1000;
    
    if (messageInput && charCount) {
        const length = messageInput.value.length;
        charCount.textContent = `${length}/${maxChars} caratteri`;
        
        if (length > maxChars * 0.9) {
            charCount.classList.add('char-count-warning');
        } else {
            charCount.classList.remove('char-count-warning');
        }
    }
}

// Conferma rifiuto invito
function confirmRejectInvite() {
    return confirm('Sei sicuro di voler rifiutare questo invito?\n\nRifiutando:\n• Non potrai più inviare messaggi in questa conversazione\n• Non potrai essere reinvitato per la stessa campagna\n• Lo stato della campagna cambierà in "Rifiutato"');
}

// Scroll al caricamento della pagina
document.addEventListener('DOMContentLoaded', function() {
    scrollToBottom();
    
    // Gestione Enter per inviare
    const messageInput = document.getElementById('message-input');
    if (messageInput) {
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('send-button').click();
            }
        });
        
        // Contatore caratteri in tempo reale
        messageInput.addEventListener('input', updateCharCount);
        
        // Auto-resize del textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 150) + 'px';
        });
        
        messageInput.focus();
        
        // Inizializza contatore caratteri
        updateCharCount();
    }
    
    // Mostra loading durante l'invio
    const sendButton = document.getElementById('send-button');
    const messageForm = document.getElementById('message-form');
    
    if (messageForm) {
        messageForm.addEventListener('submit', function() {
            if (sendButton) {
                sendButton.disabled = true;
                sendButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Invio...';
            }
        });
    }
    
    // Aggiorna il contatore messaggi dopo che la conversazione è stata visualizzata
    setTimeout(() => {
        if (typeof updateMessageCount === 'function') {
            updateMessageCount();
        }
    }, 1000);
});

// Gestione visibilità pagina - aggiorna contatore quando ritorna visibile
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        setTimeout(() => {
            if (typeof updateMessageCount === 'function') {
                updateMessageCount();
            }
        }, 500);
    }
});
</script>

<?php
// =============================================
// INCLUSIONE FOOTER
// =============================================
$footer_file = dirname(dirname(__DIR__)) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
}
?>