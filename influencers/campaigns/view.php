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
    header("Location: /auth/login.php");
    exit();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'influencer') {
    die("Accesso negato: Questa area è riservata agli influencer.");
}

// =============================================
// RECUPERO DATI INFLUENCER
// =============================================
$influencer = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM influencers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        header("Location: create-profile.php");
        exit();
    }
} catch (PDOException $e) {
    die("Errore nel caricamento del profilo: " . $e->getMessage());
}

// =============================================
// RECUPERO CAMPAGNA
// =============================================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID campagna non specificato");
}

$campaign_id = intval($_GET['id']);
$campaign = null;
$has_applied = false;
$application = null;
$campaign_paused = false;

try {
    // Verifica prima se l'influencer si è candidato
    $stmt = $pdo->prepare("
        SELECT * FROM campaign_applications 
        WHERE campaign_id = ? AND influencer_id = ?
    ");
    $stmt->execute([$campaign_id, $influencer['id']]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_applied = $application !== false;
    
    // Recupera campagna con condizioni diverse per campagne in pausa
    if ($has_applied) {
        // Se l'influencer ha già una candidatura, permette di vedere anche campagne in pausa
        $stmt = $pdo->prepare("
            SELECT c.*, b.company_name, b.website as brand_website, b.description as brand_description
            FROM campaigns c
            JOIN brands b ON c.brand_id = b.id
            WHERE c.id = ? AND c.is_public = TRUE
        ");
    } else {
        // Altrimenti, solo campagne attive
        $stmt = $pdo->prepare("
            SELECT c.*, b.company_name, b.website as brand_website, b.description as brand_description
            FROM campaigns c
            JOIN brands b ON c.brand_id = b.id
            WHERE c.id = ? AND c.status = 'active' AND c.is_public = TRUE AND c.allow_applications = TRUE
        ");
    }
    
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        die("Campagna non trovata o non disponibile");
    }
    
    // Verifica se la campagna è in pausa
    $campaign_paused = ($campaign['status'] === 'paused');
    
} catch (PDOException $e) {
    die("Errore nel caricamento della campagna: " . $e->getMessage());
}

// =============================================
// VERIFICA SE LA CAMPAGNA È NEI PREFERITI
// =============================================
$is_favorite = false;
if ($influencer) {
    try {
        $stmt_fav = $pdo->prepare("SELECT id FROM favorite_campaigns WHERE influencer_id = ? AND campaign_id = ?");
        $stmt_fav->execute([$influencer['id'], $campaign_id]);
        $is_favorite = $stmt_fav->fetch() !== false;
    } catch (PDOException $e) {
        // Silenzioso in caso di errore
    }
}

// =============================================
// VERIFICA SE L'INFLUENCER HA RIFIUTATO L'INVITO PER QUESTA CAMPAGNA
// =============================================
$has_rejected_invitation = false;
if ($influencer) {
    try {
        $stmt_reject = $pdo->prepare("
            SELECT status 
            FROM campaign_influencers 
            WHERE campaign_id = ? AND influencer_id = ? AND status = 'rejected'
        ");
        $stmt_reject->execute([$campaign_id, $influencer['id']]);
        $has_rejected_invitation = $stmt_reject->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Errore verifica rifiuto invito campagna: " . $e->getMessage());
        $has_rejected_invitation = false;
    }
}

// =============================================
// GESTIONE CANDIDATURA (VERSIONE SEMPLIFICATA)
// =============================================
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    if ($has_applied) {
        $error_msg = "Ti sei già candidato a questa campagna.";
    } elseif ($campaign_paused) {
        $error_msg = "Non puoi candidarti a questa campagna perché è attualmente in fase di revisione.";
    } elseif ($has_rejected_invitation) {
        $error_msg = "Non puoi candidarti a questa campagna perché hai rifiutato l'invito a collaborare.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // 1. Crea candidatura
            $application_message = $_POST['application_message'] ?? '';
            $stmt = $pdo->prepare("
                INSERT INTO campaign_applications (campaign_id, influencer_id, application_message, status)
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([$campaign_id, $influencer['id'], $application_message]);
            
            // 2. Crea conversazione automatica (SOLO conversazione - niente participants separati)
            $stmt = $pdo->prepare("
                INSERT INTO conversations (brand_id, influencer_id, campaign_id, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$campaign['brand_id'], $influencer['id'], $campaign_id]);
            $conversation_id = $pdo->lastInsertId();
            
            // 3. Messaggio automatico di presentazione
            $auto_message = "Ciao! Mi chiamo " . htmlspecialchars($influencer['full_name']) . 
                          " e mi sono appena candidato alla tua campagna \"" . htmlspecialchars($campaign['name']) . "\".\n\n";
            
            if (!empty($application_message)) {
                $auto_message .= "Il mio messaggio di presentazione:\n" . $application_message . "\n\n";
            }
            
            $auto_message .= "Sono specializzato in " . htmlspecialchars($influencer['niche']) . 
                          " e spero di poter collaborare con te!";
            
            $stmt = $pdo->prepare("
                INSERT INTO messages (conversation_id, sender_id, sender_type, message, sent_at)
                VALUES (?, ?, 'influencer', ?, NOW())
            ");
            $stmt->execute([$conversation_id, $_SESSION['user_id'], $auto_message]);
            
            $pdo->commit();
            $success_msg = "Candidatura inviata con successo! Il brand è stato notificato.";
            $has_applied = true;
            
            // Ricarica application
            $stmt = $pdo->prepare("
                SELECT * FROM campaign_applications 
                WHERE campaign_id = ? AND influencer_id = ?
            ");
            $stmt->execute([$campaign_id, $influencer['id']]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_msg = "Errore nell'invio della candidatura: " . $e->getMessage();
        }
    }
}

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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Dettaglio Campagna</h2>
            <div>
                <a href="list.php" class="btn btn-outline-secondary">
                    ← Torna alle Campagne
                </a>
            </div>
        </div>

        <!-- Messaggi di stato -->
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Dettagli Campagna -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header <?php echo $campaign_paused ? 'bg-warning' : 'bg-primary'; ?> text-white">
                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($campaign['name']); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if ($campaign_paused): ?>
                            <div class="alert alert-warning">
                                <strong>Campagna in fase di revisione</strong><br>
                                Questa campagna è attualmente in fase di revisione. Il brand valuterà la tua candidatura e, se interessato, provvederà a contattarti.
                            </div>
                        <?php endif; ?>
                        
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($campaign['description'])); ?></p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong>Budget:</strong> 
                                    <span class="badge bg-success fs-6">€<?php echo number_format($campaign['budget'], 2); ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Niche:</strong>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($campaign['niche']); ?></span>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Stato:</strong>
                                    <?php if ($campaign_paused): ?>
                                        <span class="badge bg-warning">In fase di revisione</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Attiva</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong>Piattaforme:</strong><br>
                                    <?php 
                                    $platforms = json_decode($campaign['platforms'], true);
                                    if ($platforms): 
                                        foreach ($platforms as $platform): 
                                            $platform_names = [
                                                'instagram' => 'Instagram',
                                                'tiktok' => 'TikTok',
                                                'youtube' => 'YouTube',
                                                'facebook' => 'Facebook',
                                                'twitter' => 'Twitter/X'
                                            ];
                                    ?>
                                        <span class="badge bg-light text-dark me-1 mb-1"><?php echo $platform_names[$platform] ?? $platform; ?></span>
                                    <?php endforeach; endif; ?>
                                </div>
                                
                                <?php if ($campaign['start_date']): ?>
                                <div class="mb-3">
                                    <strong>Data Inizio:</strong>
                                    <?php echo date('d/m/Y', strtotime($campaign['start_date'])); ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($campaign['end_date']): ?>
                                <div class="mb-3">
                                    <strong>Data Fine:</strong>
                                    <?php echo date('d/m/Y', strtotime($campaign['end_date'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($campaign['requirements']): ?>
                        <div class="mt-3">
                            <strong>Requisiti Specifici:</strong>
                            <p class="mt-1"><?php echo nl2br(htmlspecialchars($campaign['requirements'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php 
                        $target_audience = json_decode($campaign['target_audience'], true);
                        if ($target_audience && array_filter($target_audience)): 
                        ?>
                        <div class="mt-3">
                            <strong>Target Audience:</strong>
                            <div class="row mt-2">
                                <?php if (!empty($target_audience['age_range'])): ?>
                                <div class="col-md-3">
                                    <small><strong>Età:</strong> <?php echo htmlspecialchars($target_audience['age_range']); ?></small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($target_audience['gender'])): ?>
                                <div class="col-md-3">
                                    <small><strong>Genere:</strong> <?php echo htmlspecialchars($target_audience['gender']); ?></small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($target_audience['location'])): ?>
                                <div class="col-md-3">
                                    <small><strong>Località:</strong> <?php echo htmlspecialchars($target_audience['location']); ?></small>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($target_audience['interests'])): ?>
                                <div class="col-md-3">
                                    <small><strong>Interessi:</strong> <?php echo htmlspecialchars($target_audience['interests']); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar - Brand e Candidatura -->
            <div class="col-md-4">
                <!-- Informazioni Brand -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">Informazioni Brand</h5>
                    </div>
                    <div class="card-body">
                        <h6><?php echo htmlspecialchars_decode(htmlspecialchars($campaign['company_name']), ENT_QUOTES); ?></h6>
                        <?php if ($campaign['brand_description']): ?>
                            <p class="small"><?php echo nl2br(htmlspecialchars($campaign['brand_description'])); ?></p>
                        <?php endif; ?>
                        <?php if ($campaign['brand_website']): ?>
                            <a href="<?php echo htmlspecialchars($campaign['brand_website']); ?>" 
                               target="_blank" class="btn btn-outline-primary btn-sm w-100">
                                Visita Sito Web
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Stato Candidatura -->
                <div class="card mb-4">
                    <div class="card-header 
                        <?php echo !$has_applied ? 'bg-warning' : 
                              ($application['status'] === 'accepted' ? 'bg-success' : 
                              ($application['status'] === 'rejected' ? 'bg-danger' : 'bg-info')); ?> text-white">
                        <h5 class="card-title mb-0">Stato Candidatura</h5>
                    </div>
                    <div class="card-body text-center">
                        <?php if (!$has_applied): ?>
                            <?php if ($campaign_paused): ?>
                                <p class="card-text text-muted">Campagna in fase di revisione</p>
                                <button type="button" class="btn btn-secondary w-100" disabled>
                                    Candidatura Non Disponibile
                                </button>
                                <!-- Pulsante Preferiti - quando campagna in pausa -->
                                <div class="mt-3">
                                    <button type="button" 
                                            class="btn <?php echo $is_favorite ? 'btn-outline-danger' : 'btn-outline-primary'; ?> w-100 favorite-campaign-btn"
                                            data-campaign-id="<?php echo $campaign_id; ?>"
                                            data-is-favorite="<?php echo $is_favorite ? '1' : '0'; ?>">
                                        <i class="<?php echo $is_favorite ? 'fas fa-heart text-danger' : 'far fa-heart text-primary'; ?> me-1"></i>
                                        <?php echo $is_favorite ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'; ?>
                                    </button>
                                    
                                    <!-- NUOVO PULSANTE SEGNALA CAMPAGNA - quando campagna in pausa -->
                                    <button type="button" 
                                            class="btn btn-outline-warning w-100 mt-2"
                                            data-bs-toggle="modal"
                                            data-bs-target="#reportCampaignModal">
                                        <i class="fas fa-flag text-warning me-1"></i>
                                        Segnala campagna
                                    </button>
                                </div>
                            <?php elseif ($has_rejected_invitation): ?>
                                <!-- Mostra il messaggio di rifiuto invece del pulsante -->
                                <div class="alert alert-warning text-center mb-0">
                                    Hai rifiutato l'invito a collaborare
                                </div>
                                <!-- Pulsante Preferiti - quando campagna rifiutata -->
                                <div class="mt-3">
                                    <button type="button" 
                                            class="btn <?php echo $is_favorite ? 'btn-outline-danger' : 'btn-outline-primary'; ?> w-100 favorite-campaign-btn"
                                            data-campaign-id="<?php echo $campaign_id; ?>"
                                            data-is-favorite="<?php echo $is_favorite ? '1' : '0'; ?>">
                                        <i class="<?php echo $is_favorite ? 'fas fa-heart text-danger' : 'far fa-heart text-primary'; ?> me-1"></i>
                                        <?php echo $is_favorite ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'; ?>
                                    </button>
                                    
                                    <!-- NUOVO PULSANTE SEGNALA CAMPAGNA - quando campagna rifiutata -->
                                    <button type="button" 
                                            class="btn btn-outline-warning w-100 mt-2"
                                            data-bs-toggle="modal"
                                            data-bs-target="#reportCampaignModal">
                                        <i class="fas fa-flag text-warning me-1"></i>
                                        Segnala campagna
                                    </button>
                                </div>
                            <?php else: ?>
                                <p class="card-text">Non ti sei ancora candidato a questa campagna</p>
                                <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#applyModal">
                                    Candidati Ora
                                </button>
                                <!-- Pulsante Preferiti - NUOVA POSIZIONE -->
                                <div class="mt-3">
                                    <button type="button" 
                                            class="btn <?php echo $is_favorite ? 'btn-outline-danger' : 'btn-outline-primary'; ?> w-100 favorite-campaign-btn"
                                            data-campaign-id="<?php echo $campaign_id; ?>"
                                            data-is-favorite="<?php echo $is_favorite ? '1' : '0'; ?>">
                                        <i class="<?php echo $is_favorite ? 'fas fa-heart text-danger' : 'far fa-heart text-primary'; ?> me-1"></i>
                                        <?php echo $is_favorite ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'; ?>
                                    </button>
                                    
                                    <!-- NUOVO PULSANTE SEGNALA CAMPAGNA -->
                                    <button type="button" 
                                            class="btn btn-outline-warning w-100 mt-2"
                                            data-bs-toggle="modal"
                                            data-bs-target="#reportCampaignModal">
                                        <i class="fas fa-flag text-warning me-1"></i>
                                        Segnala campagna
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php
                            $status_texts = [
                                'pending' => 'In Attesa',
                                'accepted' => 'Accettata',
                                'rejected' => 'Rifiutata'
                            ];
                            $status_badges = [
                                'pending' => 'warning',
                                'accepted' => 'success',
                                'rejected' => 'danger'
                            ];
                            ?>
                            <h4 class="text-<?php echo $status_badges[$application['status']]; ?>">
                                <?php echo $status_texts[$application['status']]; ?>
                            </h4>
                            <p class="small text-muted">
                                Candidatura inviata il: <?php echo date('d/m/Y H:i', strtotime($application['created_at'])); ?>
                            </p>
                            
                            <!-- Pulsante Preferiti - NUOVA POSIZIONE (quando già candidato) -->
                            <div class="mb-3">
                                <button type="button" 
                                        class="btn <?php echo $is_favorite ? 'btn-outline-danger' : 'btn-outline-primary'; ?> w-100 favorite-campaign-btn"
                                        data-campaign-id="<?php echo $campaign_id; ?>"
                                        data-is-favorite="<?php echo $is_favorite ? '1' : '0'; ?>">
                                    <i class="<?php echo $is_favorite ? 'fas fa-heart text-danger' : 'far fa-heart text-primary'; ?> me-1"></i>
                                    <?php echo $is_favorite ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'; ?>
                                </button>
                                
                                <!-- NUOVO PULSANTE SEGNALA CAMPAGNA - quando già candidato -->
                                <button type="button" 
                                        class="btn btn-outline-warning w-100 mt-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#reportCampaignModal">
                                    <i class="fas fa-flag text-warning me-1"></i>
                                    Segnala campagna
                                </button>
                            </div>
                            
                            <?php if ($application['status'] === 'accepted'): ?>
                                <div class="alert alert-success mt-3">
                                    <strong>Congratulazioni!</strong> Il brand ha accettato la tua candidatura.
                                    Controlla i messaggi per i dettagli della collaborazione.
                                </div>
                            <?php elseif ($application['status'] === 'rejected'): ?>
                                <div class="alert alert-danger mt-3">
                                    <strong>Ci dispiace.</strong> Il brand ha declinato la tua candidatura.
                                    Continua a cercare altre opportunità!
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mt-3">
                                    <strong>In attesa di risposta.</strong> Il brand riceverà una notifica 
                                    e ti risponderà al più presto.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Match con il tuo profilo -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Match con il tuo Profilo</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $match_score = 0;
                        $match_details = [];
                        
                        // Calcola match niche
                        if ($influencer['niche'] === $campaign['niche']) {
                            $match_score += 40;
                            $match_details[] = "🎯 Niche identica (+40%)";
                        } elseif (similar_niche($influencer['niche'], $campaign['niche'])) {
                            $match_score += 25;
                            $match_details[] = "📈 Niche simile (+25%)";
                        }
                        
                        // Calcola match piattaforme
                        $user_platforms = [];
                        if (!empty($influencer['instagram_handle'])) $user_platforms[] = 'instagram';
                        if (!empty($influencer['tiktok_handle'])) $user_platforms[] = 'tiktok';
                        if (!empty($influencer['youtube_handle'])) $user_platforms[] = 'youtube';
                        
                        $campaign_platforms = json_decode($campaign['platforms'], true) ?: [];
                        $common_platforms = array_intersect($user_platforms, $campaign_platforms);
                        
                        if (!empty($common_platforms)) {
                            $platform_score = count($common_platforms) * 15;
                            $match_score += min($platform_score, 30);
                            $match_details[] = "📱 " . count($common_platforms) . " piattaforme in comune (+" . $platform_score . "%)";
                        }
                        
                        // Calcola affordability
                        if (!empty($influencer['rate']) && !empty($campaign['budget'])) {
                            $affordability_ratio = $influencer['rate'] / $campaign['budget'];
                            if ($affordability_ratio <= 0.3) {
                                $match_score += 30;
                                $match_details[] = "💰 Budget ottimale (+30%)";
                            } elseif ($affordability_ratio <= 0.6) {
                                $match_score += 15;
                                $match_details[] = "💸 Budget accettabile (+15%)";
                            }
                        }
                        
                        $match_score = min($match_score, 100);
                        ?>
                        
                        <div class="text-center mb-3">
                            <h3 class="text-<?php echo $match_score >= 70 ? 'success' : ($match_score >= 40 ? 'warning' : 'danger'); ?>">
                                <?php echo $match_score; ?>%
                            </h3>
                            <p class="text-muted">Match Score</p>
                        </div>
                        
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar 
                                <?php echo $match_score >= 70 ? 'bg-success' : 
                                      ($match_score >= 40 ? 'bg-warning' : 'bg-danger'); ?>" 
                                role="progressbar" 
                                style="width: <?php echo $match_score; ?>%">
                                <?php echo $match_score; ?>%
                            </div>
                        </div>
                        
                        <?php if (!empty($match_details)): ?>
                            <div class="small">
                                <?php foreach ($match_details as $detail): ?>
                                    <div class="mb-1"><?php echo $detail; ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Candidatura -->
<?php if (!$has_applied && !$campaign_paused && !$has_rejected_invitation): ?>
<div class="modal fade" id="applyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Candidati a "<?php echo htmlspecialchars($campaign['name']); ?>"</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="apply" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Messaggio di Presentazione (opzionale)</label>
                        <textarea class="form-control" name="application_message" rows="4" 
                                  placeholder="Presentati al brand e spiega perché sei perfetto per questa campagna..."></textarea>
                        <div class="form-text">
                            Questo messaggio verrà inviato automaticamente al brand insieme alla tua candidatura.
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <strong>Cosa succede dopo:</strong><br>
                            • Verrà creata una conversazione con il brand<br>
                            • Riceverai una notifica quando il brand risponderà<br>
                            • Puoi seguire lo stato della candidatura dalla tua dashboard
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-success">Invia Candidatura</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Segnala Campagna -->
<div class="modal fade" id="reportCampaignModal" tabindex="-1" aria-labelledby="reportCampaignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="reportCampaignForm" method="POST" action="/includes/report_campaign.php">
                <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">
                <input type="hidden" name="reporter_influencer_id" value="<?php echo $influencer['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="reportCampaignModalLabel">
                        <i class="fas fa-flag me-2"></i>Segnala campagna
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">
                        Stai per segnalare la campagna <strong>"<?php echo htmlspecialchars($campaign['name']); ?>"</strong>.
                        La tua segnalazione verrà esaminata dal supporto Kibbiz.
                    </p>
                    
                    <div class="mb-3">
                        <label for="reportCampaignReason" class="form-label">
                            <strong>Motivo della segnalazione:</strong>
                        </label>
                        <textarea class="form-control" 
                                id="reportCampaignReason" 
                                name="reason" 
                                rows="5" 
                                placeholder="Descrivi il motivo della segnalazione (es. campagna sospetta, contenuti inappropriati, informazioni fuorvianti...)" 
                                required
                                maxlength="1000"></textarea>
                        
                        <!-- Prima riga: Contatore caratteri allineato a destra -->
                        <div class="text-end mt-1">
                            <div class="text-muted small">
                                <span id="reportCampaignCharCount">1000</span> caratteri rimanenti
                            </div>
                        </div>
                        
                        <!-- Seconda riga: Testo informativo -->
                        <div class="form-text mt-1">
                            Fornisci più dettagli possibili per aiutare il nostro supporto a valutare la segnalazione.
                        </div>
                    </div>
                    
                    <div id="reportCampaignMessage" class="alert" style="display:none;"></div>
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
// Gestione Preferiti Campagne con AJAX (per view.php)
document.addEventListener('DOMContentLoaded', function() {
    // Trova tutti i pulsanti preferiti campagne
    const favoriteCampaignButtons = document.querySelectorAll('.favorite-campaign-btn');
    
    favoriteCampaignButtons.forEach(button => {
        button.addEventListener('click', function() {
            const campaignId = this.getAttribute('data-campaign-id');
            const isFavorite = this.getAttribute('data-is-favorite') === '1';
            
            // Disabilita il pulsante durante la richiesta
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ';
            
            // Invia richiesta AJAX
            fetch('toggle-campaign-favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `campaign_id=${campaignId}&action=${isFavorite ? 'remove' : 'add'}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Aggiorna lo stato del pulsante
                    const isNowFavorite = data.is_favorite;
                    
                    this.setAttribute('data-is-favorite', isNowFavorite ? '1' : '0');
                    
                    if (isNowFavorite) {
                        // Per view.php (testo e stile)
                        this.innerHTML = '<i class="fas fa-heart text-danger me-1"></i> Rimuovi dai preferiti';
                        this.classList.remove('btn-outline-primary');
                        this.classList.add('btn-outline-danger');
                    } else {
                        // Per view.php (testo e stile)
                        this.innerHTML = '<i class="far fa-heart text-primary me-1"></i> Aggiungi ai preferiti';
                        this.classList.remove('btn-outline-danger');
                        this.classList.add('btn-outline-primary');
                    }
                    
                    // Mostra notifica
                    showToast(isNowFavorite ? 'Campagna aggiunta ai preferiti!' : 'Campagna rimossa dai preferiti!', 'success');
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
    });
    
    // Funzione per mostrare notifiche toast
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
    
    // Gestione contatore caratteri per segnalazione campagna
    const reportCampaignTextarea = document.getElementById('reportCampaignReason');
    const charCountElementCampaign = document.getElementById('reportCampaignCharCount');
    
    if (reportCampaignTextarea && charCountElementCampaign) {
        const updateCharCountCampaign = () => {
            const currentLength = reportCampaignTextarea.value.length;
            const remaining = 1000 - currentLength;
            charCountElementCampaign.textContent = remaining;
            
            if (remaining <= 100) {
                charCountElementCampaign.style.color = remaining <= 20 ? '#dc3545' : '#ffc107';
            } else {
                charCountElementCampaign.style.color = '';
            }
        };
        
        updateCharCountCampaign();
        reportCampaignTextarea.addEventListener('input', updateCharCountCampaign);
        
        const reportCampaignModal = document.getElementById('reportCampaignModal');
        if (reportCampaignModal) {
            reportCampaignModal.addEventListener('shown.bs.modal', updateCharCountCampaign);
        }
    }
    
    // Gestione invio segnalazione campagna
    const reportCampaignForm = document.getElementById('reportCampaignForm');
    if (reportCampaignForm) {
        reportCampaignForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            const messageDiv = document.getElementById('reportCampaignMessage');
            
            // Disabilita pulsante durante l'invio
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Invio in corso...';
            
            // Prepara i dati del form
            const formData = new FormData(this);
            
            // Invia richiesta AJAX
            fetch('/includes/report_campaign.php', {
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
                    if (reportCampaignTextarea && charCountElementCampaign) {
                        charCountElementCampaign.textContent = '1000';
                        charCountElementCampaign.style.color = '';
                    }
                    
                    // Chiudi modale dopo 5 secondi
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('reportCampaignModal'));
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
});
</script>

<style>
/* Stili per i pulsanti preferiti campagne - MODIFICATO */
.favorite-campaign-btn.btn-outline-danger {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
    background-color: transparent !important;
}

.favorite-campaign-btn.btn-outline-primary {
    color: #0d6efd !important;
    border-color: #0d6efd !important;
    background-color: transparent !important;
}

/* NESSUN effetto hover per entrambi i pulsanti preferiti */
.favorite-campaign-btn.btn-outline-primary:hover {
    background-color: transparent !important;
    color: #0d6efd !important;
}

.favorite-campaign-btn.btn-outline-danger:hover {
    background-color: transparent !important;
    color: #dc3545 !important;
}

/* Stili per il pulsante segnala campagna */
.btn-outline-warning {
    color: #ffc107 !important;
    border-color: #ffc107 !important;
    background-color: transparent !important;
}

.btn-outline-warning:hover {
    background-color: #ffc107 !important;
    color: #212529 !important;
}

/* Toast notifications */
.toast {
    min-width: 250px;
}

/* Stili per l'alert di rifiuto invito */
.alert-warning.text-center {
    border: 1px solid #ffc107;
    background-color: #fff3cd;
    color: #856404;
    padding: 1rem;
    border-radius: 0.375rem;
    margin-bottom: 0;
}

.alert-warning.text-center i {
    font-size: 1.2em;
}
</style>

<?php
// Funzione helper per niche simili
function similar_niche($niche1, $niche2) {
    $similar_groups = [
        ['Fashion', 'Beauty', 'Lifestyle'],
        ['Fitness', 'Health', 'Wellness'],
        ['Travel', 'Adventure', 'Photography'],
        ['Food', 'Cooking', 'Restaurant'],
        ['Gaming', 'Tech', 'Electronics']
    ];
    
    foreach ($similar_groups as $group) {
        if (in_array($niche1, $group) && in_array($niche2, $group)) {
            return true;
        }
    }
    return false;
}

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