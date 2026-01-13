<?php
// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG
// =============================================
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

// =============================================
// INCLUSIONE FUNZIONI
// =============================================
$functions_file = dirname(__DIR__) . '/includes/functions.php';
if (!file_exists($functions_file)) {
    die("Errore: File funzioni non trovato in: " . $functions_file);
}
require_once $functions_file;

// =============================================
// VERIFICA AUTENTICAZIONE
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    die("Accesso negato: Questa area è riservata ai brand.");
}

// =============================================
// PAGINAZIONE
// =============================================
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$influencers_per_page = MATCHING_RESULTS_PER_PAGE;
$offset = ($current_page - 1) * $influencers_per_page;

// =============================================
// RECUPERO DATI CAMPAGNA
// =============================================
$campaign = null;
$influencers = [];
$total_influencers = 0;
$total_pages = 0;
$error = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID campagna non specificato");
}

$campaign_id = intval($_GET['id']);

try {
    // Recupera brand
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brand) {
        header("Location: create-profile.php");
        exit();
    }
    
    // Recupera campagna specifica
    $stmt = $pdo->prepare("
        SELECT c.*, b.company_name 
        FROM campaigns c 
        JOIN brands b ON c.brand_id = b.id 
        WHERE c.id = ? AND c.brand_id = ?
    ");
    $stmt->execute([$campaign_id, $brand['id']]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        die("Campagna non trovata o accesso negato");
    }
    
    // Recupera SOLO l'ultima richiesta di pausa attiva/pendente
$pause_requests_stmt = $pdo->prepare("
    SELECT cpr.*, a.username as admin_name 
    FROM campaign_pause_requests cpr 
    LEFT JOIN admins a ON cpr.admin_id = a.id 
    WHERE cpr.campaign_id = ? 
    AND cpr.status IN ('pending', 'documents_uploaded', 'under_review', 'changes_requested')
    ORDER BY cpr.created_at DESC 
    LIMIT 1
");
$pause_requests_stmt->execute([$campaign_id]);
$pause_requests = $pause_requests_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recupera documenti caricati per le richieste di pausa
    $pause_documents = [];
    if (!empty($pause_requests)) {
        $request_ids = array_column($pause_requests, 'id');
        $placeholders = str_repeat('?,', count($request_ids) - 1) . '?';
        
        $documents_stmt = $pdo->prepare("
            SELECT cpd.*, cpr.campaign_id 
            FROM campaign_pause_documents cpd 
            JOIN campaign_pause_requests cpr ON cpd.pause_request_id = cpr.id 
            WHERE cpd.pause_request_id IN ($placeholders)
            ORDER BY cpd.uploaded_at DESC
        ");
        $documents_stmt->execute($request_ids);
        $pause_documents = $documents_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Conta totale influencer
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM campaign_influencers ci
        JOIN influencers i ON ci.influencer_id = i.id
        WHERE ci.campaign_id = ?
    ");
    $count_stmt->execute([$campaign_id]);
    $total_influencers = $count_stmt->fetchColumn();
    $total_pages = ceil($total_influencers / $influencers_per_page);
    
    // Recupera influencer con paginazione
    $stmt = $pdo->prepare("
        SELECT ci.*, i.full_name, i.niche, i.instagram_handle, i.tiktok_handle, 
               i.youtube_handle, i.rate, i.rating, i.profile_views
        FROM campaign_influencers ci
        JOIN influencers i ON ci.influencer_id = i.id
        WHERE ci.campaign_id = ?
        ORDER BY ci.match_score DESC, i.rating DESC, i.profile_views DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $campaign_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $influencers_per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $influencers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento della campagna: " . $e->getMessage();
}

// =============================================
// GESTIONE AZIONI INFLUENCER
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $influencer_id = intval($_POST['influencer_id']);
        
        switch ($_POST['action']) {
            case 'invite':
                // 1. Aggiorna lo stato dell'invito
                $stmt = $pdo->prepare("
                    UPDATE campaign_influencers 
                    SET status = 'invited', brand_notes = ?
                    WHERE campaign_id = ? AND influencer_id = ?
                ");
                $stmt->execute([$_POST['notes'] ?? '', $campaign_id, $influencer_id]);
                
                // 2. Crea o recupera conversazione usando la funzione
                $conversation_id = startConversation($pdo, $brand['id'], $influencer_id, $campaign_id);
                
                if ($conversation_id) {
                    // 3. Crea messaggio di invito automatico con il nuovo formato
                    $invite_message = "Vorrei invitarti a collaborare alla mia campagna \"" . $campaign['name'] . "\".\n\n";
                    
                    if (!empty($_POST['notes'])) {
                        $invite_message .= $_POST['notes'] . "\n\n";
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO messages (conversation_id, sender_id, sender_type, message, sent_at, is_read) 
                        VALUES (?, ?, 'brand', ?, NOW(), 0)
                    ");
                    $stmt->execute([$conversation_id, $brand['id'], $invite_message]);
                    
                    // 4. Aggiorna timestamp conversazione
                    $stmt = $pdo->prepare("
                        UPDATE conversations SET updated_at = NOW() WHERE id = ?
                    ");
                    $stmt->execute([$conversation_id]);
                }
				
				// === AGGIUNGI MESSAGGIO DI CONFERMA ===
    $_SESSION['success_message'] = "Il tuo invito è stato inviato con successo. Attendi risposta dall'influencer.";
	
                break;
                
            case 'update_status':
                $stmt = $pdo->prepare("
                    UPDATE campaign_influencers 
                    SET status = ?, brand_notes = ?
                    WHERE campaign_id = ? AND influencer_id = ?
                ");
                $stmt->execute([$_POST['status'], $_POST['notes'] ?? '', $campaign_id, $influencer_id]);
                break;
        }
        
        // Ricarica la pagina
        header("Location: campaign-details.php?id=" . $campaign_id . "&page=" . $current_page);
        exit();
        
    } catch (PDOException $e) {
        $error = "Errore nell'aggiornamento: " . $e->getMessage();
        error_log("ERROR in invite process: " . $e->getMessage());
    }
}

// =============================================
// GESTIONE UPLOAD DOCUMENTI PAUSA
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    try {
        $pause_request_id = intval($_POST['pause_request_id']);
        $brand_comment = $_POST['brand_comment'] ?? '';
        
        // Verifica che la richiesta di pausa appartenga a questa campagna
$check_stmt = $pdo->prepare("
    SELECT cpr.* 
    FROM campaign_pause_requests cpr 
    JOIN campaigns c ON cpr.campaign_id = c.id 
    JOIN brands b ON c.brand_id = b.id 
    WHERE cpr.id = ? AND b.user_id = ? AND cpr.status IN ('pending', 'documents_uploaded', 'changes_requested')
");
        $check_stmt->execute([$pause_request_id, $_SESSION['user_id']]);
        $pause_request = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pause_request) {
            $error = "Richiesta di pausa non valida o già completata";
        } else {
            if (empty($brand_comment) && (empty($_FILES['document']['name']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE)) {
                $error = "Inserisci almeno un commento o seleziona un file da caricare.";
            } else {
                $upload_success = true;
                $upload_error = '';
                $has_file = false;
                
                if (isset($_FILES['document']) && !empty($_FILES['document']['name']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                    $has_file = true;
                    $upload_result = handlePauseDocumentUpload($_FILES['document'], $pause_request_id, $_SESSION['user_id']);
                    
                    if (!$upload_result['success']) {
                        $upload_success = false;
                        $upload_error = $upload_result['error'];
                    }
                } elseif (isset($_FILES['document']) && $_FILES['document']['error'] !== UPLOAD_ERR_OK && $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE) {
                    // Errore di upload diverso da "nessun file"
                    $upload_success = false;
                    $upload_error = "Errore nel caricamento del file. Codice errore: " . $_FILES['document']['error'];
                }
                
                if ($upload_success) {
                    $new_status = 'under_review';
                    
                    // Aggiorna lo stato della richiesta e il commento del brand
$stmt = $pdo->prepare("
    UPDATE campaign_pause_requests 
    SET status = ?, 
        brand_upload_comment = COALESCE(?, brand_upload_comment), 
        brand_comment_at = NOW(),
        updated_at = NOW() 
    WHERE id = ?
");
$stmt->execute([$new_status, $brand_comment, $pause_request_id]);
                    
                    // Messaggio di successo personalizzato
                    $success_message = "Informazioni inviate con successo! La richiesta è ora in revisione.";
                    if ($has_file && !empty($brand_comment)) {
                        $success_message = "Documento e commento caricati con successo! La richiesta è ora in revisione.";
                    } elseif ($has_file) {
                        $success_message = "Documento caricato con successo! La richiesta è ora in revisione.";
                    } elseif (!empty($brand_comment)) {
                        $success_message = "Commento inviato con successo! La richiesta è ora in revisione.";
                    }
                    
                    $_SESSION['success_message'] = $success_message;
                    header("Location: campaign-details.php?id=" . $campaign_id);
                    exit();
                } else {
                    $error = $upload_error ?: "Si è verificato un errore durante l'invio delle informazioni.";
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Errore nel caricamento del documento: " . $e->getMessage();
    }
}

// =============================================
// INCLUSIONE HEADER
// =============================================
$header_file = dirname(__DIR__) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Dettaglio Campagna</h2>
            <a href="campaigns.php" class="btn btn-outline-secondary">
                ← Torna alle Campagne
            </a>
        </div>

        <!-- Messaggi di stato -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Dettagli Campagna -->
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Informazioni Campagna</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4><?php echo htmlspecialchars($campaign['name']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($campaign['description']); ?></p>
                                
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
                                    <?php
                                    $status_badges = [
                                        'draft' => 'secondary',
                                        'active' => 'success',
                                        'paused' => 'warning',
                                        'completed' => 'primary',
                                        'cancelled' => 'danger'
                                    ];
                                    $badge_class = $status_badges[$campaign['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst($campaign['status']); ?>
                                    </span>
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
                                
                                <div class="mb-3">
                                    <strong>Data Creazione:</strong>
                                    <?php echo date('d/m/Y H:i', strtotime($campaign['created_at'])); ?>
                                </div>
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
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">Statistiche Matching</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center">
                            <h3><?php echo $total_influencers; ?></h3>
                            <p class="text-muted">Influencer Trovati</p>
                        </div>
                        
                        <?php 
                        $status_counts = [
                            'invited' => 0,
                            'accepted' => 0,
                            'rejected' => 0,
                            'completed' => 0
                        ];
                        
                        foreach ($influencers as $inf) {
                            if (isset($status_counts[$inf['status']])) {
                                $status_counts[$inf['status']]++;
                            }
                        }
                        ?>
                        
                        <div class="mt-3">
                            <?php foreach ($status_counts as $status => $count): ?>
                                <?php if ($count > 0): ?>
                                <div class="d-flex justify-content-between mb-1">
                                    <small><?php echo ucfirst($status); ?></small>
                                    <small class="fw-bold"><?php echo $count; ?></small>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($campaign['status'] === 'draft'): ?>
                <div class="card">
                    <div class="card-body text-center">
                        <p>Questa campagna è in bozza e non è visibile agli influencer.</p>
                        <a href="edit-campaign.php?id=<?php echo $campaign['id']; ?>" 
                           class="btn btn-primary w-100">Modifica Campagna</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SEZIONE RICHIESTE INTEGRAZIONI PAUSA - SOLO ULTIMA RICHIESTA ATTIVA -->
        <?php if (!empty($pause_requests) && ($campaign['status'] === 'paused' || !empty($pause_requests))): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>Richieste Integrazioni
                </h5>
            </div>
            <div class="card-body">
                <?php 
                // Mostra solo l'ultima richiesta (già filtrata dalla query)
                $request = $pause_requests[0];
                $request_documents = array_filter($pause_documents, function($doc) use ($request) {
                    return $doc['pause_request_id'] == $request['id'];
                });
                $is_pending = $request['status'] === 'pending';
                $is_documents_uploaded = $request['status'] === 'documents_uploaded';
                $is_under_review = $request['status'] === 'under_review';
                $is_changes_requested = $request['status'] === 'changes_requested';
                $is_overdue = $request['deadline'] && strtotime($request['deadline']) < time();
                ?>
                <div class="border rounded p-3 mb-3 <?php echo $is_overdue && ($is_pending || $is_documents_uploaded || $is_under_review) ? 'border-danger' : 'border-warning'; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0">
    Richiesta del <?php echo date('d/m/Y', strtotime($request['created_at'])) . ' - ' . date('H:i', strtotime($request['created_at'])); ?>
</h6>
                        <span class="badge bg-<?php 
    echo $is_pending ? ($is_overdue ? 'danger' : 'warning') : 
         ($is_under_review ? 'info' : 
         ($is_documents_uploaded ? 'info' : 
         ($is_changes_requested ? 'warning text-dark' : 'success'))); 
?>">
    <?php 
    echo $is_pending ? ($is_overdue ? 'Scaduta' : 'In attesa documenti') : 
         ($is_under_review ? 'In revisione' : 
         ($is_documents_uploaded ? 'Documenti caricati' : 
         ($is_changes_requested ? 'Modifiche Richieste' : 'Completata'))); 
    ?>
</span>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Motivo della pausa:</strong>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($request['pause_reason'])); ?></p>
                    </div>
                    
                    <?php if (!empty($request['required_documents'])): ?>
                    <div class="mb-3">
                        <strong>Informazioni aggiuntive richieste:</strong>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($request['required_documents'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($request['deadline']): ?>
                    <div class="mb-3">
                        <strong>Scadenza:</strong>
                        <span class="<?php echo $is_overdue && ($is_pending || $is_documents_uploaded || $is_under_review) ? 'text-danger fw-bold' : ''; ?>">
                            <?php echo date('d/m/Y', strtotime($request['deadline'])); ?>
                            <?php if ($is_overdue && ($is_pending || $is_documents_uploaded || $is_under_review)): ?>
                                <i class="fas fa-exclamation-triangle ms-1"></i>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- File caricati -->
                    <div class="mb-3">
                        <strong>File caricati:</strong>
                        <?php if (!empty($request_documents)): ?>
                            <div class="mt-2">
                                <?php foreach ($request_documents as $doc): ?>
                                    <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                                        <div>
                                            <i class="fas fa-file me-2"></i>
                                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                               target="_blank" class="text-decoration-none">
                                                <?php echo htmlspecialchars($doc['original_name']); ?>
                                            </a>
                                            <small class="text-muted ms-2">
                                                (<?php echo formatFileSize($doc['file_size']); ?>)
                                            </small>
                                        </div>
                                        <div>
                                            <small class="text-muted me-3">
                                                <?php echo date('d/m/Y H:i', strtotime($doc['uploaded_at'])); ?>
                                            </small>
                                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                               class="btn btn-sm btn-outline-primary" target="_blank" download>
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-2">Nessun file caricato</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Commento Brand -->
<?php if (!empty($request['brand_upload_comment'])): ?>
    <div class="alert alert-light border mb-3">
        <div class="d-flex justify-content-between align-items-start">
            <strong>Il tuo commento:</strong>
            <?php if (!empty($request['brand_comment_at'])): ?>
                <small class="text-muted"><?php echo date('d/m/Y - H:i', strtotime($request['brand_comment_at'])); ?></small>
            <?php endif; ?>
        </div>
        <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($request['brand_upload_comment'])); ?></p>
    </div>
<?php endif; ?>

<!-- Commento Admin -->
<?php if (!empty($request['admin_review_comment'])): ?>
    <div class="alert alert-info mb-3">
        <div class="d-flex justify-content-between align-items-start">
            <strong>Commento Admin:</strong>
            <?php if (!empty($request['admin_comment_at'])): ?>
                <small class="text-muted"><?php echo date('d/m/Y - H:i', strtotime($request['admin_comment_at'])); ?></small>
            <?php endif; ?>
        </div>
        <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($request['admin_review_comment'])); ?></p>
    </div>
<?php endif; ?>
                    
                    <?php 
// Variabile per richiesta modifiche da parte di un admin
$is_changes_requested = $request['status'] === 'changes_requested';
?>

<!-- Form upload documenti (solo per richieste attive) -->
<?php if ($is_pending || $is_documents_uploaded || $is_changes_requested): ?>
<div class="border-top pt-3">
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="pause_request_id" value="<?php echo $request['id']; ?>">
        
        <div class="mb-3">
            <label class="form-label">Commento</label>
            <textarea class="form-control" name="brand_comment" rows="3" 
                      placeholder="Scrivi un commento..."></textarea>
            <div class="form-text">
                Campo opzionale per aggiungere note alle informazioni inviate
                <?php if ($is_changes_requested): ?>
                    <br><span class="text-dark fw-medium">Stai rispondendo alla richiesta di modifiche dell'admin.</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Allega file</label>
            <input type="file" class="form-control" name="document" 
                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt">
            <div class="form-text">
                Tipi file consentiti: PDF, DOC, DOCX, JPG, PNG, TXT (max 2MB)
                <?php if ($is_documents_uploaded || $is_changes_requested): ?>
                    <br><span class="text-dark fw-medium">Puoi caricare file aggiuntivi se necessario.</span>
                <?php endif; ?>
            </div>
        </div>
        
        <button type="submit" name="upload_document" class="btn btn-primary">
            Invia informazioni
        </button>
    </form>
</div>
<?php elseif ($is_under_review): ?>
<div class="border-top pt-3">
    <div class="alert alert-info">
        <div class="d-flex align-items-center">
            <div>
                <h6 class="mb-1">Le informazioni sono state inviate con successo e sono in fase di revisione. Riceverai una notifica quando sarà completata la revisione.</h6>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SEZIONE INFLUENCER MATCHING SOLO PER CAMPAGNE ATTIVE -->
        <?php if ($campaign['status'] === 'active'): ?>
        <!-- Lista Influencer Matching -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Influencer Matching</h5>
                <div>
                    <small class="text-muted">
                        <?php echo $total_influencers; ?> risultati totali
                    </small>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($influencers)): ?>
                    <div class="text-center py-4">
                        <h5>Nessun influencer trovato</h5>
                        <p class="text-muted">
                            Modifica i criteri della campagna per trovare influencer matching
                        </p>
                    </div>
                <?php else: ?>
                    <!-- PAGINAZIONE TOP -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Paginazione influencer">
                        <ul class="pagination justify-content-center">
                            <!-- Previous Page -->
                            <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="campaign-details.php?id=<?php echo $campaign_id; ?>&page=<?php echo $current_page - 1; ?>">
                                    ← Precedente
                                </a>
                            </li>
                            
                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                       href="campaign-details.php?id=<?php echo $campaign_id; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <!-- Next Page -->
                            <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="campaign-details.php?id=<?php echo $campaign_id; ?>&page=<?php echo $current_page + 1; ?>">
                                    Successiva →
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Influencer</th>
                                    <th>Match Type</th>
                                    <th>Piattaforme</th>
                                    <th>Rate & Affordability</th>
                                    <th>Match Score</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($influencers as $influencer): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($influencer['full_name']); ?></strong><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($influencer['niche']); ?> • 
                                                Rating: <?php echo number_format($influencer['rating'], 1); ?> ★
                                            </small>
                                        </td>
                                        <td>
                                            <?php 
                                            // Funzione semplificata per badge match
                                            $match_score = $influencer['match_score'] ?? 0;
                                            if ($match_score >= 80) {
                                                echo '<span class="badge bg-success">Ottimo Match</span>';
                                            } elseif ($match_score >= 60) {
                                                echo '<span class="badge bg-primary">Buon Match</span>';
                                            } elseif ($match_score >= 40) {
                                                echo '<span class="badge bg-warning">Match Moderato</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary">Match Basso</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $platforms = [];
                                            if (!empty($influencer['instagram_handle'])) $platforms[] = '<span title="Instagram">📷</span>';
                                            if (!empty($influencer['tiktok_handle'])) $platforms[] = '<span title="TikTok">🎵</span>';
                                            if (!empty($influencer['youtube_handle'])) $platforms[] = '<span title="YouTube">📺</span>';
                                            echo implode(' ', $platforms);
                                            ?>
                                        </td>
                                        <td>
                                            <strong>€<?php echo number_format($influencer['rate'], 2); ?></strong><br>
                                            <?php 
                                            // Indicatore affordability semplificato
                                            $budget = $campaign['budget'] ?? 0;
                                            $rate = $influencer['rate'] ?? 0;
                                            if ($budget > 0 && $rate > 0) {
                                                $percentage = ($rate / $budget) * 100;
                                                if ($percentage <= 25) {
                                                    echo '<span class="badge bg-success">Ottimo</span>';
                                                } elseif ($percentage <= 50) {
                                                    echo '<span class="badge bg-primary">Buono</span>';
                                                } elseif ($percentage <= 75) {
                                                    echo '<span class="badge bg-warning">Accettabile</span>';
                                                } else {
                                                    echo '<span class="badge bg-danger">Alto</span>';
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 20px;" 
                                                 title="Match Score: <?php echo $influencer['match_score']; ?>%">
                                                <div class="progress-bar 
                                                    <?php echo $influencer['match_score'] >= 70 ? 'bg-success' : 
                                                          ($influencer['match_score'] >= 40 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                    role="progressbar" 
                                                    style="width: <?php echo $influencer['match_score']; ?>%">
                                                    <?php echo $influencer['match_score']; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'pending' => 'secondary',
                                                'invited' => 'primary',
                                                'accepted' => 'success',
                                                'rejected' => 'danger',
                                                'completed' => 'info'
                                            ];
                                            $badge_class = $status_badges[$influencer['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php echo ucfirst($influencer['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#influencerModal<?php echo $influencer['influencer_id']; ?>">
                                                    Dettagli
                                                </button>
                                                
                                                <?php if ($influencer['status'] === 'pending' && $campaign['status'] === 'active'): ?>
                                                    <button type="button" class="btn btn-outline-success" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#inviteModal<?php echo $influencer['influencer_id']; ?>">
                                                        Invita
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Modal Dettagli Influencer -->
                                    <div class="modal fade" id="influencerModal<?php echo $influencer['influencer_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        <?php echo htmlspecialchars($influencer['full_name']); ?>
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6>Informazioni Base</h6>
                                                            <p><strong>Niche:</strong> <?php echo htmlspecialchars($influencer['niche']); ?></p>
                                                            <p><strong>Rate:</strong> €<?php echo number_format($influencer['rate'], 2); ?></p>
                                                            <p><strong>Rating:</strong> <?php echo number_format($influencer['rating'], 1); ?> ★</p>
                                                            <p><strong>Profile Views:</strong> <?php echo number_format($influencer['profile_views']); ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6>Dettagli Match</h6>
                                                            <p><strong>Score Totale:</strong> <?php echo $influencer['match_score']; ?>%</p>
                                                            <div class="progress mb-2">
                                                                <div class="progress-bar 
                                                                    <?php echo $influencer['match_score'] >= 70 ? 'bg-success' : 
                                                                          ($influencer['match_score'] >= 40 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                                    role="progressbar" 
                                                                    style="width: <?php echo $influencer['match_score']; ?>%">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mt-3">
                                                        <div class="col-12">
                                                            <h6>Piattaforme Social</h6>
                                                            <div class="d-flex gap-2">
                                                                <?php if (!empty($influencer['instagram_handle'])): ?>
                                                                    <span class="badge bg-instagram">Instagram: @<?php echo htmlspecialchars($influencer['instagram_handle']); ?></span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($influencer['tiktok_handle'])): ?>
                                                                    <span class="badge bg-dark">TikTok: @<?php echo htmlspecialchars($influencer['tiktok_handle']); ?></span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($influencer['youtube_handle'])): ?>
                                                                    <span class="badge bg-danger">YouTube: <?php echo htmlspecialchars($influencer['youtube_handle']); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($influencer['brand_notes'])): ?>
                                                        <div class="mt-3">
                                                            <strong>Note Brand:</strong>
                                                            <p><?php echo nl2br(htmlspecialchars($influencer['brand_notes'])); ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal Invita Influencer -->
                                    <?php if ($influencer['status'] === 'pending' && $campaign['status'] === 'active'): ?>
                                    <div class="modal fade" id="inviteModal<?php echo $influencer['influencer_id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Invita <?php echo htmlspecialchars($influencer['full_name']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="influencer_id" value="<?php echo $influencer['influencer_id']; ?>">
                                                        <input type="hidden" name="action" value="invite">
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label">Note (opzionale)</label>
                                                            <textarea class="form-control" name="notes" rows="3" 
                                                                      placeholder="Aggiungi note per l'influencer..."></textarea>
                                                        </div>
                                                        
                                                        <div class="alert alert-info">
                                                            <small>
                                                                <strong>Match Score:</strong> <?php echo $influencer['match_score']; ?>%<br>
                                                                <strong>Rate:</strong> €<?php echo number_format($influencer['rate'], 2); ?><br>
                                                                Invitando questo influencer, gli verrà notificata 
                                                                la tua campagna e potrà accettare o rifiutare la collaborazione.
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                        <button type="submit" class="btn btn-success">Invia Invito</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINAZIONE BOTTOM -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Paginazione influencer">
                        <ul class="pagination justify-content-center">
                            <!-- Previous Page -->
                            <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="campaign-details.php?id=<?php echo $campaign_id; ?>&page=<?php echo $current_page - 1; ?>">
                                    ← Precedente
                                </a>
                            </li>
                            
                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                       href="campaign-details.php?id=<?php echo $campaign_id; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <!-- Next Page -->
                            <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="campaign-details.php?id=<?php echo $campaign_id; ?>&page=<?php echo $current_page + 1; ?>">
                                    Successiva →
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.bg-instagram {
    background: linear-gradient(45deg, #405DE6, #5851DB, #833AB4, #C13584, #E1306C, #FD1D1D, #F56040, #F77737, #FCAF45, #FFDC80) !important;
}
.progress {
    height: 20px;
}
.progress-bar {
    font-weight: bold;
}
</style>

<?php
// =============================================
// FUNZIONI HELPER
// =============================================

/**
 * Formatta la dimensione del file in formato leggibile
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return "0 Bytes";
    $k = 1024;
    $sizes = ["Bytes", "KB", "MB", "GB"];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . " " . $sizes[$i];
}

/**
 * Gestisce l'upload dei file per le richieste di pausa
 */
function handlePauseDocumentUpload($file, $pause_request_id, $user_id) {
    global $pdo;
    
    // Verifica se il file è stato effettivamente caricato
    if (!isset($file) || empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'message' => 'Nessun file da caricare'];
    }
    
    // Configurazione upload - usa la costante centrale
    $upload_dir = dirname(__DIR__) . '/uploads/pause_documents/';
    $max_file_size = MAX_UPLOAD_SIZE; // Usa la costante da config.php
    $allowed_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'txt' => 'text/plain'
    ];
    
    // Crea directory se non esiste
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validazioni
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Errore nel caricamento del file'];
    }
    
    if ($file['size'] > $max_file_size) {
        return ['success' => false, 'error' => 'File troppo grande (max 2MB)'];
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_type = $file['type'];
    
    if (!in_array($file_extension, array_keys($allowed_types)) || 
        !in_array($file_type, array_values($allowed_types))) {
        return ['success' => false, 'error' => 'Tipo file non supportato'];
    }
    
    // Genera nome file sicuro
    $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $file['name']);
    $file_path = $upload_dir . $filename;
    
    // Sposta file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => false, 'error' => 'Errore nel salvataggio del file'];
    }
    
    // Salva nel database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO campaign_pause_documents 
            (pause_request_id, filename, original_name, file_path, file_size, file_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $pause_request_id,
            $filename,
            $file['name'],
            $file_path,
            $file['size'],
            $file_type,
            $user_id
        ]);
        
        return ['success' => true, 'file_path' => $file_path, 'message' => 'File caricato con successo'];
        
    } catch (PDOException $e) {
        // Cancella file in caso di errore database
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        return ['success' => false, 'error' => 'Errore nel salvataggio nel database'];
    }
}

// =============================================
// INCLUSIONE FOOTER
// =============================================
$footer_file = dirname(__DIR__) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}