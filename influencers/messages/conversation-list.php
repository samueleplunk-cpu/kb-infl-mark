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
// RECUPERO INFLUENCER_ID
// =============================================
$influencer_id = null;
$stmt = $pdo->prepare("SELECT id FROM influencers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$influencer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$influencer) {
    die("Profilo influencer non trovato. Completa prima il profilo influencer.");
}
$influencer_id = $influencer['id'];

// =============================================
// MODIFICA: RECUPERA CONVERSAZIONI CON INFO BRAND STATUS E MESSAGGI NON LETTI
// =============================================
$stmt = $pdo->prepare("
    SELECT c.*, 
           b.company_name as brand_name,
           b.logo as brand_image,
           camp.name as campaign_title,
           u.is_deleted as brand_deleted,
           u.deleted_at as brand_deleted_at,
           (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY sent_at DESC LIMIT 1) as last_message,
           (SELECT sent_at FROM messages WHERE conversation_id = c.id ORDER BY sent_at DESC LIMIT 1) as last_message_time,
           (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND is_read = 0 AND sender_type = 'brand') as unread_count
    FROM conversations c
    LEFT JOIN brands b ON c.brand_id = b.id
    LEFT JOIN campaigns camp ON c.campaign_id = camp.id
    LEFT JOIN users u ON c.brand_id = u.id
    WHERE c.influencer_id = ?
    ORDER BY unread_count > 0 DESC, last_message_time DESC, c.created_at DESC
");
$stmt->execute([$influencer_id]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            <h2>Le tue conversazioni</h2>
            <a href="../dashboard.php" class="btn btn-outline-primary">
                ← Torna alla Dashboard
            </a>
        </div>

        <!-- LISTA CONVERSAZIONI -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Conversazioni (<?php echo count($conversations); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($conversations)): ?>
                    <div class="text-center py-5">
                        <h4 class="text-muted">Nessuna conversazione</h4>
                        <p class="text-muted">Le conversazioni con i brand appariranno qui</p>
                        <a href="../campaigns/list.php" class="btn btn-primary mt-3">
                            Cerca campagne
                        </a>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($conversations as $conversation): 
                            $has_unread = isset($conversation['unread_count']) && $conversation['unread_count'] > 0;
                            $brand_deleted = isset($conversation['brand_deleted']) && $conversation['brand_deleted'] == 1;
                            $bg_class = $has_unread ? 'bg-light' : '';
                        ?>
                            <a href="conversation.php?id=<?php echo $conversation['id']; ?>" 
                               class="list-group-item list-group-item-action <?php echo $bg_class; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <!-- Immagine profilo brand -->
                                        <?php 
                                        $brand_image = $conversation['brand_image'] ?? '';
                                        if (!empty($brand_image)) {
                                            $brand_image_path = get_image_path($brand_image, 'brand');
                                        } else {
                                            $brand_image_path = '/uploads/placeholder/brand_admin_edit.png';
                                        }
                                        ?>
                                        <img src="<?php echo htmlspecialchars($brand_image_path); ?>" 
                                             class="rounded-circle me-3" width="50" height="50" alt="Brand Logo"
                                             onerror="this.onerror=null; this.src='<?php echo get_placeholder_path('brand'); ?>';">
                                        
                                        <div>
                                            <!-- MODIFICA: Aggiungi indicatore account eliminato -->
                                            <h5 class="mb-1">
                                                <?php echo htmlspecialchars_decode(htmlspecialchars($conversation['brand_name']), ENT_QUOTES); ?>
                                                <?php if ($brand_deleted): ?>
                                                    <span class="badge bg-danger ms-2" title="Account eliminato">
                                                        <i class="fas fa-user-slash"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </h5>
                                            
                                            <?php if (!empty($conversation['campaign_title'])): ?>
                                                <small class="text-muted">Campagna: <?php echo htmlspecialchars($conversation['campaign_title']); ?></small>
                                            <?php endif; ?>
                                            
                                            <!-- MODIFICA: Aggiungi indicazione account eliminato -->
                                            <?php if ($brand_deleted): ?>
                                                <div class="mt-1">
                                                    <small class="text-danger">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>Questo brand ha eliminato l'account
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($conversation['last_message'])): ?>
                                                <p class="mb-1 text-muted">
                                                    <?php 
                                                    $last_message = htmlspecialchars($conversation['last_message']);
                                                    echo strlen($last_message) > 100 ? substr($last_message, 0, 100) . '...' : $last_message;
                                                    ?>
                                                </p>
                                            <?php else: ?>
                                                <p class="mb-1 text-muted">Nessun messaggio ancora</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end">
                                        <?php if (!empty($conversation['last_message_time'])): ?>
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($conversation['last_message_time'])); ?>
                                            </small>
                                        <?php endif; ?>
                                        <div class="mt-2">
                                            <?php if ($has_unread): ?>
                                                <span class="badge bg-warning">
                                                    <?php 
                                                    if ($conversation['unread_count'] == 1) {
                                                        echo "1 nuovo messaggio";
                                                    } else {
                                                        echo $conversation['unread_count'] . " nuovi messaggi";
                                                    }
                                                    ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">Leggi conversazione</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.list-group-item:hover {
    background-color: #f8f9fa;
    transform: translateX(2px);
    transition: all 0.2s ease;
}

/* Sfondo per messaggi non letti */
.list-group-item.bg-light {
    background-color: #f8f9fa !important;
    border-left: 3px solid #ffc107;
}

/* Miglioramenti per le immagini */
.rounded-circle {
    object-fit: cover;
    border: 2px solid #e9ecef;
}

.list-group-item {
    border: 1px solid rgba(0,0,0,0.125);
    margin-bottom: 5px;
    border-radius: 8px !important;
}

.list-group-item:hover {
    border-color: #007bff;
}

/* Stile per brand eliminati */
.bg-danger.badge {
    font-size: 0.7em;
    padding: 2px 6px;
}
</style>

<script>
// Script aggiuntivo per gestire eventuali errori di caricamento immagini
document.addEventListener('DOMContentLoaded', function() {
    // Controlla tutte le immagini e sostituisci con placeholder se danno errore
    const images = document.querySelectorAll('.list-group-item img');
    images.forEach(img => {
        // Verifica se l'immagine è già caricata correttamente
        if (img.complete && img.naturalHeight === 0) {
            // Immagine non valida, sostituisci con placeholder
            img.src = '<?php echo get_placeholder_path("brand"); ?>';
        }
        
        // Gestione errori aggiuntiva
        img.addEventListener('error', function() {
            this.src = '<?php echo get_placeholder_path("brand"); ?>';
        });
    });
    
    // Aggiungi tooltip per badge account eliminato
    const deletedBadges = document.querySelectorAll('.badge.bg-danger[title]');
    deletedBadges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.setAttribute('data-bs-toggle', 'tooltip');
            this.setAttribute('data-bs-placement', 'top');
        });
    });
    
    // Inizializza tooltip di Bootstrap se disponibile
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
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