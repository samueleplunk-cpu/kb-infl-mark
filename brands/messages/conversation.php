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

if (!is_brand()) {
    die("Accesso negato: Questa area è riservata ai brand.");
}

// =============================================
// VERIFICA PARAMETRI
// =============================================
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID conversazione non valido");
}

$conversation_id = intval($_GET['id']);

// =============================================
// RECUPERO BRAND_ID E BRAND_LOGO
// =============================================
$brand_id = null;
$brand_logo = null;
$stmt = $pdo->prepare("SELECT id, logo FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$brand) {
    die("Profilo brand non trovato. Completa prima il profilo brand.");
}
$brand_id = $brand['id'];
$brand_logo = $brand['logo'];

// =============================================
// RECUPERA CONVERSAZIONE
// =============================================
$stmt = $pdo->prepare("
    SELECT c.*, 
           inf.full_name as influencer_name,
           inf.profile_image as influencer_image,
           camp.name as campaign_title,
           camp.id as campaign_id
    FROM conversations c
    LEFT JOIN influencers inf ON c.influencer_id = inf.id
    LEFT JOIN campaigns camp ON c.campaign_id = camp.id
    WHERE c.id = ? AND c.brand_id = ?
");
$stmt->execute([$conversation_id, $brand_id]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    die("Conversazione non trovata o accesso negato");
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
    $new_message = trim($_POST['message']);
    
    try {
        $insert_stmt = $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, sender_type, message, sent_at) 
            VALUES (?, ?, 'brand', ?, NOW())
        ");
        $insert_stmt->execute([$conversation_id, $brand_id, $new_message]);
        
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
                <h2 class="mb-0">Conversazione con <?php echo htmlspecialchars_decode(htmlspecialchars($conversation['influencer_name']), ENT_QUOTES); ?></h2>
                <small class="text-muted"><?php echo htmlspecialchars_decode(htmlspecialchars($conversation['influencer_name']), ENT_QUOTES); ?></small>
            </div>
            <div class="d-flex gap-2">
                <?php if (!empty($conversation['campaign_id'])): ?>
                    <a href="/brands/campaign-details.php?id=<?php echo $conversation['campaign_id']; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-bullhorn me-1"></i> Vedi Campagna
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
                            <i class="fas fa-user me-2"></i>Influencer
                        </h5>
                        <div class="d-flex align-items-center">
                            <?php 
                            // Determina quale immagine mostrare per l'influencer
                            $influencer_profile_src = '';
                            $influencer_profile_alt = htmlspecialchars_decode(htmlspecialchars($conversation['influencer_name']), ENT_QUOTES);
                            
                            if (!empty($conversation['influencer_image']) && image_exists($conversation['influencer_image'])) {
                                // Se l'influencer ha caricato un'immagine personalizzata
                                $influencer_profile_src = get_image_path($conversation['influencer_image'], 'influencer');
                            } else {
                                // Se NON ha un'immagine personalizzata, mostra il placeholder
                                $influencer_profile_src = "/uploads/placeholder/influencer_admin_edit.png";
                                $influencer_profile_alt = "Placeholder - " . $influencer_profile_alt;
                            }
                            ?>
                            
                            <img src="<?php echo $influencer_profile_src; ?>" 
                                 class="rounded-circle me-3" 
                                 width="60" 
                                 height="60" 
                                 alt="<?php echo $influencer_profile_alt; ?>"
                                 style="object-fit: cover;">
                                
                            <div>
                                <strong class="h6"><?php echo htmlspecialchars_decode(htmlspecialchars($conversation['influencer_name']), ENT_QUOTES); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars_decode(htmlspecialchars($conversation['influencer_name']), ENT_QUOTES); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($conversation['campaign_title'])): ?>
                    <div class="col-md-6">
                        <h5 class="card-title">
                            <i class="fas fa-bullhorn me-2"></i>Campagna
                        </h5>
                        <p class="mb-0"><?php echo htmlspecialchars($conversation['campaign_title']); ?></p>
                        <?php if (!empty($conversation['campaign_id'])): ?>
                            <small>
                                <a href="/brands/campaign-details.php?id=<?php echo $conversation['campaign_id']; ?>" class="text-decoration-none">
                                    Vedi dettagli campagna
                                </a>
                            </small>
                        <?php endif; ?>
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
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="scrollToBottom()">
                    <i class="fas fa-arrow-down me-1"></i> Vai all'ultimo
                </button>
            </div>
            <div class="card-body p-0">
                <div id="messages-container" style="max-height: 500px; overflow-y: auto; padding: 1.25rem;">
                    <?php if (empty($messages)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">Nessun messaggio ancora</h4>
                            <p class="text-muted">Inizia la conversazione inviando il primo messaggio!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <?php 
                            $is_own_message = $message['sender_type'] === 'brand';
                            $message_class = $is_own_message ? 'text-end' : 'text-start';
                            $bubble_class = $is_own_message ? 'bg-primary text-white' : 'bg-light';
                            $time_class = $is_own_message ? 'text-white-50' : 'text-muted';
                            ?>
                            
                            <div class="message mb-4 <?php echo $message_class; ?>" id="message-<?php echo $message['id']; ?>">
                                <div class="d-flex <?php echo $is_own_message ? 'justify-content-end' : 'justify-content-start'; ?>">
                                    <div class="message-bubble <?php echo $bubble_class; ?> rounded-3 p-3 position-relative" 
                                         style="max-width: 70%;">
                                        <!-- Nome mittente per i messaggi altrui -->
                                        <?php if (!$is_own_message): ?>
                                            <div class="sender-name mb-1">
                                                <strong><?php echo htmlspecialchars_decode(htmlspecialchars($message['sender_name']), ENT_QUOTES); ?></strong>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Contenuto messaggio -->
                                        <div class="message-text">
                                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                        </div>
                                        
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
                <form method="POST" action="" id="message-form">
                    <div class="input-group">
                        <textarea name="message" class="form-control" placeholder="Scrivi il tuo messaggio..." 
                                  rows="3" required id="message-input"></textarea>
                        <button type="submit" class="btn btn-primary" id="send-button">
                            <i class="fas fa-paper-plane me-1"></i> Invia
                        </button>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Premi Invio per inviare, Shift+Invio per andare a capo
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.message-bubble {
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    border: 1px solid rgba(0,0,0,0.1);
}

.bg-primary.message-bubble {
    border: none;
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
}

/* Scrollbar personalizzata */
#messages-container::-webkit-scrollbar {
    width: 6px;
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
</style>

<script>
// Scroll automatico all'ultimo messaggio
function scrollToBottom() {
    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
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
        
        // Auto-resize del textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 150) + 'px';
        });
        
        messageInput.focus();
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