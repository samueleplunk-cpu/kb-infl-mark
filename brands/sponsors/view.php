<?php
// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG CON PERCORSO ASSOLUTO CORRETTO
// =============================================
$config_file = dirname(dirname(dirname(__FILE__))) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

// =============================================
// VERIFICA AUTENTICAZIONE
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    die("Accesso negato: Questa area è riservata ai brand.");
}

// =============================================
// RECUPERO DATI BRAND
// =============================================
$brand = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brand) {
        header("Location: ../create-profile.php");
        exit();
    }
} catch (PDOException $e) {
    die("Errore nel caricamento del profilo: " . $e->getMessage());
}

// =============================================
// VERIFICA ID SPONSOR
// =============================================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: list.php?error=ID_sponsor_richiesto");
    exit();
}

$sponsor_id = intval($_GET['id']);

// =============================================
// RECUPERO DATI SPONSOR CON JOIN INFLUENCER
// =============================================
$sponsor = null;
try {
    // Query semplificata usando solo colonne che sappiamo esistere
    // basata sulla query usata in list.php
    $query = "
        SELECT s.*, 
               i.id as influencer_id,
               i.full_name as influencer_name,
               i.profile_image,
               i.niche,
               i.rating,
               i.bio,
               i.instagram_handle,
               i.tiktok_handle,
               i.youtube_handle,
               i.facebook_handle,
               u.email
        FROM sponsors s
        JOIN influencers i ON s.influencer_id = i.id
        JOIN users u ON i.user_id = u.id
        WHERE s.id = ? 
          AND s.status = 'active'
          AND s.deleted_at IS NULL
          AND u.is_active = 1 
          AND u.is_suspended = 0 
          AND u.is_blocked = 0 
          AND u.deleted_at IS NULL
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$sponsor_id]);
    $sponsor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sponsor) {
        header("Location: list.php?sponsor_deleted=1");
        exit();
    }
} catch (PDOException $e) {
    die("Errore nel caricamento dei dettagli sponsor: " . $e->getMessage());
}

// =============================================
// VERIFICA SE SPONSOR È NEI PREFERITI
// =============================================
$is_favorite = false;
try {
    $stmt = $pdo->prepare("SELECT id FROM favorite_sponsors WHERE brand_id = ? AND sponsor_id = ?");
    $stmt->execute([$brand['id'], $sponsor_id]);
    $is_favorite = $stmt->fetch() !== false;
} catch (PDOException $e) {
    error_log("Errore verifica preferiti sponsor: " . $e->getMessage());
}

// =============================================
// INCLUSIONE FUNZIONI SOCIAL NETWORK
// =============================================
require_once dirname(dirname(dirname(__FILE__))) . '/includes/social_network_functions.php';

// =============================================
// INCLUSIONE HEADER
// =============================================
$header_file = dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;
?>

<div class="row">
    <div class="col-md-12">
        <!-- Breadcrumb e pulsanti navigazione -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="list.php">Sponsor Disponibili</a></li>
                <li class="breadcrumb-item active" aria-current="page">Dettagli Sponsor</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Dettagli Sponsor</h2>
            <a href="list.php" class="btn btn-outline-secondary">
                ← Torna alla Lista
            </a>
        </div>

        <div class="row">
            <!-- Colonna principale - Dettagli Sponsor -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <!-- Header con titolo e badge -->
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($sponsor['title']); ?></h5>
                            <span class="badge bg-light text-primary">Attivo</span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Immagine sponsor (se presente) -->
                        <?php if ($sponsor['image_url']): ?>
                        <div class="text-center mb-4">
                            <img src="<?php echo htmlspecialchars($sponsor['image_url']); ?>" 
                                 class="img-fluid rounded" 
                                 alt="<?php echo htmlspecialchars($sponsor['title']); ?>"
                                 style="max-height: 300px; object-fit: cover;">
                        </div>
                        <?php endif; ?>
                        
                        <!-- Descrizione completa -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Descrizione</h6>
                            <p><?php echo nl2br(htmlspecialchars($sponsor['description'])); ?></p>
                        </div>
                        
                        <!-- Informazioni dettagliate -->
                        <div class="row">
                            <div class="col-md-6">
                                <!-- Budget -->
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Budget</h6>
                                    <h4 class="text-success">€ <?php echo number_format($sponsor['budget'], 0); ?></h4>
                                    <?php if ($sponsor['currency'] && $sponsor['currency'] !== 'EUR'): ?>
                                    <small class="text-muted">(Valuta: <?php echo htmlspecialchars($sponsor['currency']); ?>)</small>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Categoria -->
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Categoria</h6>
                                    <span class="badge bg-info fs-6">
                                        <?php echo ucfirst(htmlspecialchars($sponsor['category'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <!-- Piattaforme -->
                                <div class="mb-3">
                                    <h6 class="text-muted mb-1">Piattaforme Social</h6>
                                    <?php 
                                    $platforms = json_decode($sponsor['platforms'], true);
                                    if ($platforms && is_array($platforms)): 
                                        $social_networks = get_active_social_networks();
                                        $network_map = [];
                                        foreach ($social_networks as $network) {
                                            $network_map[$network['slug']] = $network;
                                        }
                                    ?>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($platforms as $platform): 
                                                $network = $network_map[$platform] ?? null;
                                            ?>
                                                <?php if ($network): ?>
                                                <span class="badge bg-light text-dark p-2 d-flex align-items-center">
                                                    <?php 
                                                    $icon_class = '';
                                                    switch(strtolower($platform)) {
                                                        case 'instagram':
                                                            $icon_class = 'fa-brands fa-instagram text-danger';
                                                            break;
                                                        case 'facebook':
                                                            $icon_class = 'fa-brands fa-facebook text-primary';
                                                            break;
                                                        case 'tiktok':
                                                            $icon_class = 'fa-brands fa-tiktok text-dark';
                                                            break;
                                                        case 'pinterest':
                                                            $icon_class = 'fa-brands fa-pinterest text-danger';
                                                            break;
                                                        case 'youtube':
                                                            $icon_class = 'fa-brands fa-youtube text-danger';
                                                            break;
                                                        case 'twitch':
                                                            $icon_class = 'fa-brands fa-twitch text-purple';
                                                            break;
                                                        case 'telegram':
                                                            $icon_class = 'fa-brands fa-telegram text-primary';
                                                            break;
                                                        case 'threads':
                                                            $icon_class = 'fa-brands fa-threads text-dark';
                                                            break;
                                                        default:
                                                            $icon_class = 'fa-solid fa-share-nodes text-secondary';
                                                            break;
                                                    }
                                                    ?>
                                                    <i class="<?php echo $icon_class; ?> me-1 fs-5"></i>
                                                    <span><?php echo htmlspecialchars($network['name']); ?></span>
                                                </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted">Nessuna piattaforma specificata</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Target Audience -->
                        <?php if ($sponsor['target_audience']): ?>
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Target Audience</h6>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <?php 
                                    $target_audience = $sponsor['target_audience'];
                                    // Verifica se è JSON
                                    $decoded_audience = json_decode($target_audience, true);
                                    if ($decoded_audience && is_array($decoded_audience)) {
                                        // Visualizza come lista se è JSON strutturato
                                        echo '<ul class="mb-0">';
                                        foreach ($decoded_audience as $key => $value) {
                                            if (!empty($value)) {
                                                echo '<li><strong>' . ucfirst(htmlspecialchars($key)) . ':</strong> ' . htmlspecialchars($value) . '</li>';
                                            }
                                        }
                                        echo '</ul>';
                                    } else {
                                        // Altrimenti mostra come testo semplice
                                        echo nl2br(htmlspecialchars($target_audience));
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Informazioni aggiuntive -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="small text-muted">
                                    <i class="far fa-clock me-1"></i>
                                    Creato: <?php echo date('d/m/Y H:i', strtotime($sponsor['created_at'])); ?>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="small text-muted">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Ultimo aggiornamento: <?php echo date('d/m/Y H:i', strtotime($sponsor['updated_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar - Informazioni Influencer -->
            <div class="col-md-4">
                <!-- Card Influencer -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">Informazioni Influencer</h5>
                    </div>
                    
                    <div class="card-body">
                        <!-- Immagine profilo e nome -->
                        <div class="text-center mb-3">
                            <?php 
                            // Gestione immagine profilo
                            $has_profile_image = !empty($sponsor['profile_image']);
                            $profile_image_path = '';
                            
                            if ($has_profile_image) {
                                $profile_image_path = $sponsor['profile_image'];
                                
                                // Correzione percorso immagine profilo
                                if (strpos($profile_image_path, 'profiles/') === 0) {
                                    $profile_image_path = '/uploads/' . $profile_image_path;
                                } elseif (strpos($profile_image_path, '/profiles/') === 0) {
                                    $profile_image_path = '/uploads' . $profile_image_path;
                                } elseif (strpos($profile_image_path, '/') !== 0 && strpos($profile_image_path, 'http') !== 0) {
                                    $profile_image_path = '/uploads/profiles/' . $profile_image_path;
                                }
                            }
                            ?>
                            
                            <div class="mb-3" style="width: 100px; height: 100px; margin: 0 auto;">
                                <?php if ($has_profile_image): ?>
                                    <img src="<?php echo htmlspecialchars($profile_image_path); ?>" 
                                         class="rounded-circle" 
                                         width="100" 
                                         height="100" 
                                         alt="<?php echo htmlspecialchars($sponsor['influencer_name']); ?>"
                                         style="object-fit: cover; width: 100%; height: 100%;"
                                         onerror="this.style.display='none'; this.parentNode.innerHTML='<div class=\'rounded-circle bg-secondary d-flex align-items-center justify-content-center\' style=\'width: 100px; height: 100px;\'><i class=\'fas fa-user text-white fa-3x\'></i></div>';">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" 
                                         style="width: 100px; height: 100px;">
                                        <i class="fas fa-user text-white fa-3x"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <h5><?php echo htmlspecialchars($sponsor['influencer_name']); ?></h5>
                            
                            <?php if ($sponsor['rating']): ?>
                            <div class="mb-2">
                                <i class="fas fa-star text-warning"></i>
                                <strong><?php echo number_format($sponsor['rating'], 1); ?></strong>/5.0
                                <span class="text-muted">(Valutazione)</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Informazioni influener -->
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Niche / Settore</h6>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($sponsor['niche'] ?? 'N/A'); ?></span>
                        </div>
                        
                        <?php if ($sponsor['bio']): ?>
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Bio</h6>
                            <p class="small"><?php echo nl2br(htmlspecialchars(substr($sponsor['bio'], 0, 200))); ?><?php echo strlen($sponsor['bio']) > 200 ? '...' : ''; ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Handle social (se disponibili) -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-2">Profili Social</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if (!empty($sponsor['instagram_handle'])): ?>
                                <a href="https://instagram.com/<?php echo htmlspecialchars($sponsor['instagram_handle']); ?>" 
                                   target="_blank" class="badge bg-light text-dark p-2 d-flex align-items-center">
                                    <i class="fa-brands fa-instagram text-danger me-1"></i>
                                    Instagram
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($sponsor['tiktok_handle'])): ?>
                                <a href="https://tiktok.com/@<?php echo htmlspecialchars($sponsor['tiktok_handle']); ?>" 
                                   target="_blank" class="badge bg-light text-dark p-2 d-flex align-items-center">
                                    <i class="fa-brands fa-tiktok text-dark me-1"></i>
                                    TikTok
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($sponsor['youtube_handle'])): ?>
                                <a href="https://youtube.com/<?php echo htmlspecialchars($sponsor['youtube_handle']); ?>" 
                                   target="_blank" class="badge bg-light text-dark p-2 d-flex align-items-center">
                                    <i class="fa-brands fa-youtube text-danger me-1"></i>
                                    YouTube
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($sponsor['facebook_handle'])): ?>
                                <a href="https://facebook.com/<?php echo htmlspecialchars($sponsor['facebook_handle']); ?>" 
                                   target="_blank" class="badge bg-light text-dark p-2 d-flex align-items-center">
                                    <i class="fa-brands fa-facebook text-primary me-1"></i>
                                    Facebook
                                </a>
                                <?php endif; ?>
                                
                                <!-- Twitter/X è stato rimosso dalla query poiché non esiste la colonna -->
                            </div>
                        </div>
                        
                        <!-- Pulsanti azione -->
                        <div class="d-grid gap-2">
                            <!-- Pulsante Visualizza Profilo -->
                            <a href="<?php echo BASE_URL; ?>/influencers/profile.php?id=<?php echo $sponsor['influencer_id']; ?>" 
                               class="btn btn-outline-primary" target="_blank">
                                <i class="fas fa-external-link-alt me-1"></i>
                                Visualizza Profilo Completo
                            </a>
                            
                            <!-- Pulsante Contatta (placeholder) -->
                            <button type="button" 
                                    class="btn btn-success"
                                    onclick="contactInfluencer(<?php echo $sponsor['id']; ?>, '<?php echo htmlspecialchars(addslashes($sponsor['influencer_name'])); ?>')">
                                <i class="fas fa-envelope me-1"></i>
                                Contatta Influencer
                            </button>
                            
                            <!-- NUOVO PULSANTE SEGNALA SPONSOR -->
                            <button type="button" 
                                    class="btn btn-outline-warning"
                                    data-bs-toggle="modal"
                                    data-bs-target="#reportSponsorModal">
                                <i class="fas fa-flag text-warning me-1"></i>
                                Segnala sponsor
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-footer text-center">
                        <small class="text-muted">
                            ID Influencer: <?php echo $sponsor['influencer_id']; ?> | 
                            Sponsor attivo dal <?php echo date('d/m/Y', strtotime($sponsor['created_at'])); ?>
                        </small>
                    </div>
                </div>
                
                <!-- Card Azioni Rapide -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Azioni Rapide</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="list.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>
                                Torna alla Lista
                            </a>
                            
                            <a href="../dashboard.php" class="btn btn-outline-secondary">
                                <i class="fas fa-home me-1"></i>
                                Vai alla Dashboard
                            </a>
                            
                            <!-- Pulsante per salvare/rimuovere dai preferiti (MODIFICATO) -->
                            <button type="button" 
                                    class="btn <?php echo $is_favorite ? 'btn-danger' : 'btn-outline-danger'; ?>" 
                                    id="saveToFavorites"
                                    data-sponsor-id="<?php echo $sponsor['id']; ?>"
                                    data-is-favorite="<?php echo $is_favorite ? 'true' : 'false'; ?>">
                                <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart me-1"></i>
                                <?php echo $is_favorite ? 'Rimuovi dai Preferiti' : 'Salva nei Preferiti'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sezione Match (opzionale - per funzionalità future) -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Potenziale Collaborazione</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <div class="mb-2">
                            <i class="fas fa-chart-line fa-3x text-primary"></i>
                        </div>
                        <h6>Allineamento di Settore</h6>
                        <div class="progress mb-2" style="height: 20px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 85%">85%</div>
                        </div>
                        <small class="text-muted">Il tuo brand e l'influencer condividono lo stesso target</small>
                    </div>
                    
                    <div class="col-md-4 text-center">
                        <div class="mb-2">
                            <i class="fas fa-users fa-3x text-info"></i>
                        </div>
                        <h6>Copertura Pubblico</h6>
                        <div class="progress mb-2" style="height: 20px;">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 70%">70%</div>
                        </div>
                        <small class="text-muted">Match con il tuo pubblico target</small>
                    </div>
                    
                    <div class="col-md-4 text-center">
                        <div class="mb-2">
                            <i class="fas fa-euro-sign fa-3x text-success"></i>
                        </div>
                        <h6>Rapporto Qualità-Prezzo</h6>
                        <div class="progress mb-2" style="height: 20px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 90%">90%</div>
                        </div>
                        <small class="text-muted">Budget in linea con le prestazioni attese</small>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb me-2"></i>
                        <strong>Suggerimento:</strong> Questo influencer sembra essere un'ottima scelta per la tua campagna. 
                        Considera di contattarlo per discutere i dettagli della collaborazione.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Segnala Sponsor -->
<div class="modal fade" id="reportSponsorModal" tabindex="-1" aria-labelledby="reportSponsorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="reportSponsorForm" method="POST" action="/includes/report_sponsor.php">
                <input type="hidden" name="sponsor_id" value="<?php echo $sponsor['id']; ?>">
                <input type="hidden" name="reporter_brand_id" value="<?php echo $brand['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="reportSponsorModalLabel">
                        <i class="fas fa-flag me-2"></i>Segnala sponsor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">
                        Stai per segnalare lo sponsor <strong>"<?php echo htmlspecialchars($sponsor['title']); ?>"</strong>
                        dell'influencer <strong><?php echo htmlspecialchars($sponsor['influencer_name']); ?></strong>.
                        La tua segnalazione verrà esaminata dal supporto Kibbiz.
                    </p>
                    
                    <div class="mb-3">
                        <label for="reportSponsorReason" class="form-label">
                            <strong>Motivo della segnalazione:</strong>
                        </label>
                        <textarea class="form-control" 
                                id="reportSponsorReason" 
                                name="reason" 
                                rows="5" 
                                placeholder="Descrivi il motivo della segnalazione (es. sponsor sospetto, contenuti inappropriati, informazioni fuorvianti, prezzi non trasparenti...)" 
                                required
                                maxlength="1000"></textarea>
                        
                        <!-- Prima riga: Contatore caratteri allineato a destra -->
                        <div class="text-end mt-1">
                            <div class="text-muted small">
                                <span id="reportSponsorCharCount">1000</span> caratteri rimanenti
                            </div>
                        </div>
                        
                        <!-- Seconda riga: Testo informativo -->
                        <div class="form-text mt-1">
                            Fornisci più dettagli possibili per aiutare il nostro supporto a valutare la segnalazione.
                        </div>
                    </div>
                    
                    <div id="reportSponsorMessage" class="alert" style="display:none;"></div>
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Annulla
                    </button>
                    <button type="submit" class="btn btn-warning">
                        Invia segnalazione
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/**
 * Funzione per contattare l'influencer (placeholder)
 */
function contactInfluencer(sponsorId, influencerName) {
    // Qui puoi implementare la logica per contattare l'influencer
    // Per ora mostriamo un alert con opzioni
    const message = `Funzionalità "Contatta Influencer"\n\n` +
                   `Sponsor ID: ${sponsorId}\n` +
                   `Influencer: ${influencerName}\n\n` +
                   `Questa funzionalità sarà implementata per:\n` +
                   `• Aprire una chat con l'influencer\n` +
                   `• Inviare una proposta di collaborazione\n` +
                   `• Pianificare una chiamata conoscitiva\n\n` +
                   `[Placeholder - Funzionalità in sviluppo]`;
    
    alert(message);
    
    // In futuro, questa funzione potrebbe:
    // 1. Aprire un modal con un form per inviare un messaggio
    // 2. Reindirizzare a una pagina di messaggistica
    // 3. Inviare una richiesta AJAX per creare una conversazione
}

/**
 * Funzione per salvare nei preferiti (MODIFICATA)
 */
document.getElementById('saveToFavorites')?.addEventListener('click', function() {
    const sponsorId = this.getAttribute('data-sponsor-id');
    const isCurrentlyFavorite = this.getAttribute('data-is-favorite') === 'true';
    
    // Disabilita il pulsante durante la richiesta
    this.disabled = true;
    const originalHTML = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Caricamento...';
    
    // Invia richiesta AJAX
    fetch('toggle-sponsor-favorite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `sponsor_id=${sponsorId}&action=${isCurrentlyFavorite ? 'remove' : 'add'}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const isNowFavorite = data.is_favorite;
            
            // Aggiorna attributo data-is-favorite
            this.setAttribute('data-is-favorite', isNowFavorite ? 'true' : 'false');
            
            if (isNowFavorite) {
                // Sponsor ora è nei preferiti
                this.classList.remove('btn-outline-danger');
                this.classList.add('btn-danger');
                this.innerHTML = '<i class="fas fa-heart me-1"></i> Rimuovi dai Preferiti';
                showToast('Sponsor salvato nei preferiti!', 'success');
            } else {
                // Sponsor rimosso dai preferiti
                this.classList.remove('btn-danger');
                this.classList.add('btn-outline-danger');
                this.innerHTML = '<i class="far fa-heart me-1"></i> Salva nei Preferiti';
                showToast('Sponsor rimosso dai preferiti!', 'success');
            }
        } else {
            showToast('Errore: ' + data.message, 'error');
            this.innerHTML = originalHTML;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Errore di connessione', 'error');
        this.innerHTML = originalHTML;
    })
    .finally(() => {
        this.disabled = false;
    });
});

/**
 * Funzione per mostrare notifiche toast
 */
function showToast(message, type = 'success') {
    // Crea elemento toast se non esiste
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1055;';
        document.body.appendChild(toastContainer);
    }
    
    const toastId = 'toast-' + Date.now();
    const bgColor = type === 'success' ? 'bg-success' : 'bg-danger';
    
    const toastHTML = `
        <div id="${toastId}" class="toast show align-items-center text-white ${bgColor} border-0 mb-2" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('afterbegin', toastHTML);
    
    // Rimuovi automaticamente dopo 3 secondi
    setTimeout(() => {
        const toast = document.getElementById(toastId);
        if (toast) {
            toast.remove();
        }
    }, 3000);
}

// Gestione contatore caratteri per segnalazione sponsor
const reportSponsorTextarea = document.getElementById('reportSponsorReason');
const charCountElementSponsor = document.getElementById('reportSponsorCharCount');

if (reportSponsorTextarea && charCountElementSponsor) {
    const updateCharCountSponsor = () => {
        const currentLength = reportSponsorTextarea.value.length;
        const remaining = 1000 - currentLength;
        charCountElementSponsor.textContent = remaining;
        
        if (remaining <= 100) {
            charCountElementSponsor.style.color = remaining <= 20 ? '#dc3545' : '#ffc107';
        } else {
            charCountElementSponsor.style.color = '';
        }
    };
    
    updateCharCountSponsor();
    reportSponsorTextarea.addEventListener('input', updateCharCountSponsor);
    
    const reportSponsorModal = document.getElementById('reportSponsorModal');
    if (reportSponsorModal) {
        reportSponsorModal.addEventListener('shown.bs.modal', updateCharCountSponsor);
    }
}

// Gestione invio segnalazione sponsor
const reportSponsorForm = document.getElementById('reportSponsorForm');
if (reportSponsorForm) {
    reportSponsorForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        const messageDiv = document.getElementById('reportSponsorMessage');
        
        // Disabilita pulsante durante l'invio
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Invio in corso...';
        
        // Prepara i dati del form
        const formData = new FormData(this);
        
        // Invia richiesta AJAX
        fetch('/includes/report_sponsor.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Successo
                messageDiv.className = 'alert alert-success';
                messageDiv.innerHTML = `
                    <i class="fas fa-check-circle me-2"></i>
                    ${data.message || 'Segnalazione inviata con successo. Il nostro supporto la esaminerà al più presto.'}
                `;
                messageDiv.style.display = 'block';
                
                // Resetta form
                this.reset();
                if (reportSponsorTextarea && charCountElementSponsor) {
                    charCountElementSponsor.textContent = '1000';
                    charCountElementSponsor.style.color = '';
                }
                
                // Chiudi modale dopo 5 secondi
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('reportSponsorModal'));
                    if (modal) modal.hide();
                }, 5000);
                
            } else {
                // Errore
                messageDiv.className = 'alert alert-danger';
                messageDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${data.message || 'Si è verificato un errore durante l\'invio della segnalazione.'}
                `;
                messageDiv.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messageDiv.className = 'alert alert-danger';
            messageDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                Errore di connessione. Riprova più tardi.
            `;
            messageDiv.style.display = 'block';
        })
        .finally(() => {
            // Ripristina pulsante
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
}

// Inizializzazione tooltip di Bootstrap (se presenti)
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<style>
/* Stili personalizzati per la pagina dettagli */
.card {
    border-radius: 10px;
    border: 1px solid #e0e0e0;
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.progress {
    border-radius: 10px;
}

.progress-bar {
    border-radius: 10px;
    font-weight: 500;
}

.badge {
    font-weight: 500;
    padding: 6px 12px;
}

.btn {
    border-radius: 6px;
    font-weight: 500;
}

.btn-outline-primary:hover {
    background-color: #0d6efd;
    color: white;
}

.btn-success {
    background-color: #198754;
    border-color: #198754;
}

.btn-success:hover {
    background-color: #157347;
    border-color: #146c43;
}

/* Stili per le icone delle piattaforme social */
.fa-instagram {
    background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.fa-tiktok {
    color: #000000;
}

.fa-youtube {
    color: #FF0000;
}

.fa-facebook {
    color: #1877F2;
}

.fa-twitch {
    color: #9146FF;
}

.fa-telegram {
    color: #0088cc;
}

.fa-threads {
    color: #000000;
}

/* Stili per il pulsante segnala sponsor */
.btn-outline-warning {
    color: #ffc107 !important;
    border-color: #ffc107 !important;
    background-color: transparent !important;
}

.btn-outline-warning:hover {
    background-color: #ffc107 !important;
    color: #212529 !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card-body {
        padding: 1rem;
    }
    
    .display-4 {
        font-size: 2rem;
    }
    
    .btn {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
}
</style>

<?php
// =============================================
// INCLUSIONE FOOTER
// =============================================
$footer_file = dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>