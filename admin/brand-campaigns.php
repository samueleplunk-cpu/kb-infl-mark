<?php

ob_start();

// Includi file necessari
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
require_once '../includes/admin_header.php';

// Verifica login
checkAdminLogin();

// Genera CSRF token se non esiste
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Inizializza variabili
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$message = '';

// Controllo IMMEDIATO delle campagne scadute - SEMPRE eseguito
checkAndUpdateExpiredCampaigns();

// Controllo aggiuntivo casuale per campagne in pausa (solo occasionalmente per performance)
if (mt_rand(1, 10) === 1) {
    checkExpiredPausedCampaigns();
}

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_campaign'])) {
        // Gestione salvataggio campagna
        $data = [
            'brand_id' => isset($_POST['brand_id']) ? intval($_POST['brand_id']) : null,
            'name' => isset($_POST['name']) ? trim($_POST['name']) : '',
            'description' => isset($_POST['description']) ? trim($_POST['description']) : '',
            'budget' => isset($_POST['budget']) ? floatval($_POST['budget']) : 0,
            'currency' => isset($_POST['currency']) ? trim($_POST['currency']) : 'EUR',
            'niche' => isset($_POST['niche']) ? trim($_POST['niche']) : '',
            'platforms' => isset($_POST['platforms']) ? json_encode($_POST['platforms']) : '[]',
            'target_audience' => isset($_POST['target_audience']) ? trim($_POST['target_audience']) : '',
            'status' => isset($_POST['status']) ? trim($_POST['status']) : 'active',
            'start_date' => isset($_POST['start_date']) ? trim($_POST['start_date']) : '',
            'end_date' => isset($_POST['end_date']) ? trim($_POST['end_date']) : '',
            'requirements' => isset($_POST['requirements']) ? trim($_POST['requirements']) : '',
            'is_public' => isset($_POST['is_public']) ? 1 : 0,
            'allow_applications' => isset($_POST['allow_applications']) ? 1 : 0,
            'deadline_date' => isset($_POST['deadline_date']) ? trim($_POST['deadline_date']) : null
        ];
        
        if (empty($data['name']) || empty($data['brand_id'])) {
            $message = '<div class="alert alert-danger">Nome campagna e brand sono obbligatori</div>';
        } else {
            $success = saveCampaign($data, $id);
            if ($success) {
                $message = '<div class="alert alert-success">Campagna salvata con successo!</div>';
                if (!$id) {
                    $action = 'list';
                }
            } else {
                $message = '<div class="alert alert-danger">Errore nel salvataggio</div>';
            }
        }
    }
    
    // Gestione azioni rapide
    if (isset($_POST['action_type']) && isset($_POST['campaign_id'])) {
        $campaign_id = intval($_POST['campaign_id']);
        $action_type = $_POST['action_type'];
        
        switch($action_type) {
            case 'pause':
                // FIX: Prevenzione duplicazione - Verifica se esiste già una richiesta attiva
                if (hasActivePauseRequest($campaign_id)) {
                    $message = '<div class="alert alert-warning">Esiste già una richiesta di integrazione attiva per questa campagna.</div>';
                    break;
                }
                
                // Nuova gestione pausa con richiesta documenti
                if (isset($_POST['pause_reason']) && !empty($_POST['pause_reason'])) {
                    $pause_data = [
                        'campaign_id' => $campaign_id,
                        'admin_id' => $_SESSION['admin_id'],
                        'pause_reason' => $_POST['pause_reason'],
                        'required_documents' => isset($_POST['required_documents']) ? $_POST['required_documents'] : '',
                        'deadline' => isset($_POST['deadline']) ? $_POST['deadline'] : null
                    ];
                    
                    $success = createPauseRequest($pause_data);
                    if ($success) {
                        // Aggiorna anche la campagna con la data di scadenza
                        updateCampaignDeadline($campaign_id, $_POST['deadline']);
                        updateCampaignStatus($campaign_id, 'paused');
                        $message = '<div class="alert alert-warning">Campagna messa in pausa con richiesta integrazioni</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Errore nella creazione della richiesta di pausa</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Motivo della pausa obbligatorio</div>';
                }
                break;
                
            case 'resume':
                updateCampaignStatus($campaign_id, 'active');
                // Completa eventuali richieste di pausa pendenti
                completePendingPauseRequests($campaign_id);
                $message = '<div class="alert alert-success">Campagna ripresa</div>';
                break;
                
            case 'complete':
                updateCampaignStatus($campaign_id, 'completed');
                $message = '<div class="alert alert-info">Campagna completata</div>';
                break;

            case 'reactivate':
                // Riattiva una campagna scaduta
                updateCampaignStatus($campaign_id, 'active');
                $message = '<div class="alert alert-success">Campagna riattivata</div>';
                break;
                
            case 'delete':
                $success = deleteCampaign($campaign_id);
                if ($success) {
                    $message = '<div class="alert alert-info">Campagna eliminata con successo</div>';
                } else {
                    $message = '<div class="alert alert-danger">Errore nell\'eliminazione della campagna</div>';
                }
                break;
        }
    }
    
    // Gestione richieste di pausa
    if (isset($_POST['update_pause_request'])) {
        $request_id = intval($_POST['pause_request_id']);
        $request_action = $_POST['request_action']; // Cambiato nome per evitare conflitto
        
        switch($request_action) {
            case 'complete':
                $success = completePauseRequest($request_id);
                $message = $success ? 
                    '<div class="alert alert-success">Richiesta contrassegnata come completata</div>' :
                    '<div class="alert alert-danger">Errore nell\'aggiornamento</div>';
                break;
                
            case 'cancel':
                $success = cancelPauseRequest($request_id);
                $message = $success ? 
                    '<div class="alert alert-info">Richiesta cancellata</div>' :
                    '<div class="alert alert-danger">Errore nella cancellazione</div>';
                break;
        }
    }
    
    // Gestione approvazione documenti
    if (isset($_POST['review_action']) && isset($_POST['pause_request_id'])) {
        $request_id = intval($_POST['pause_request_id']);
        $review_action = $_POST['review_action'];
        $admin_comment = isset($_POST['admin_comment']) ? trim($_POST['admin_comment']) : '';
        
        // Recupera campaign_id per il redirect
        $campaign_id = getCampaignIdFromPauseRequest($request_id);
        
        switch($review_action) {
            case 'mark_under_review':
                $success = markPauseRequestUnderReview($request_id, $_SESSION['admin_id']);
                if ($success) {
                    $_SESSION['admin_message'] = '<div class="alert alert-info">Richiesta messa in revisione</div>';
                } else {
                    $_SESSION['admin_message'] = '<div class="alert alert-danger">Errore nell\'aggiornamento</div>';
                }
                break;
                
            case 'approve':
                if (empty($admin_comment)) {
                    $message = '<div class="alert alert-danger">Il commento è obbligatorio per approvare i documenti</div>';
                } else {
                    $success = updatePauseRequestStatus($request_id, 'approved', $admin_comment, $_SESSION['admin_id']);
                    if ($success) {
                        sendPauseRequestStatusNotification($request_id, 'approved', $admin_comment);
                        $_SESSION['admin_message'] = '<div class="alert alert-success">Documenti approvati con successo! La campagna è stata riattivata.</div>';
                    } else {
                        $_SESSION['admin_message'] = '<div class="alert alert-danger">Errore nell\'approvazione dei documenti</div>';
                    }
                }
                break;
                
            case 'request_changes':
                if (empty($admin_comment)) {
                    $message = '<div class="alert alert-danger">Il commento è obbligatorio per richiedere modifiche</div>';
                } else {
                    $success = updatePauseRequestStatus($request_id, 'changes_requested', $admin_comment, $_SESSION['admin_id']);
                    if ($success) {
                        sendPauseRequestStatusNotification($request_id, 'changes_requested', $admin_comment);
                        $_SESSION['admin_message'] = '<div class="alert alert-warning">Modifiche richieste al brand</div>';
                    } else {
                        $_SESSION['admin_message'] = '<div class="alert alert-danger">Errore nella richiesta di modifiche</div>';
                    }
                }
                break;
        }
        
        // === UNICO REDIRECT PER TUTTE LE AZIONI ===
        if (isset($_GET['id']) && !empty($_GET['id'])) {
            header("Location: brand-campaigns.php?action=edit&id=" . $_GET['id']);
        } elseif ($campaign_id) {
            header("Location: brand-campaigns.php?action=edit&id=" . $campaign_id);
        } else {
            header("Location: brand-campaigns.php?action=list");
        }
        exit;
    }
}

// All'inizio del file, dopo la gestione POST:
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}

// Gestione delle diverse azioni
if ($action === 'list') {
    // Pagina lista campagne
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 15;
    
    $filters = [
        'status' => isset($_GET['status']) ? $_GET['status'] : '',
        'search' => isset($_GET['search']) ? $_GET['search'] : '',
        'brand_id' => isset($_GET['brand_id']) ? $_GET['brand_id'] : '',
        'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
        'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : ''
    ];
    
    // Ottieni dati
    $result = getCampaigns($page, $per_page, $filters);
    $campaigns = $result['data'];
    $total_pages = $result['total_pages'];
    $total_count = $result['total'];
    $brands_list = getAllBrands();
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">
                        <i class="fas fa-bullhorn me-2"></i>Gestione Campagne Brand
                    </h1>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nuova Campagna
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Statistiche Rapide -->
<div class="row mb-4">
    <div class="col">
        <div class="card bg-success text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fs-4 fw-bold"><?php echo getCampaignsCount('active'); ?></div>
                        <div>Campagne Attive</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-play-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card bg-warning text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fs-4 fw-bold"><?php echo getCampaignsCount('paused'); ?></div>
                        <div>In Pausa</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-pause-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card bg-info text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fs-4 fw-bold"><?php echo getCampaignsCount('completed'); ?></div>
                        <div>Completate</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card bg-danger text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fs-4 fw-bold"><?php echo getCampaignsCount('expired'); ?></div>
                        <div>Scadute</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card bg-primary text-white mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="fs-4 fw-bold"><?php echo getCampaignsCount(); ?></div>
                        <div>Totale Campagne</div>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-bullhorn fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
                
                <!-- Filtri -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-filter me-2"></i>Filtri
        </h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <input type="hidden" name="action" value="list">
            
            <div class="col-md-3">
                <label for="search" class="form-label">Cerca</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                       placeholder="Nome campagna...">
            </div>
            
            <div class="col-md-2">
                <label for="brand_id" class="form-label">Brand</label>
                <select class="form-select" id="brand_id" name="brand_id">
                    <option value="">Tutti i brand</option>
                    <?php foreach ($brands_list as $brand): ?>
                        <option value="<?php echo $brand['id']; ?>" 
                                <?php echo $filters['brand_id'] == $brand['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($brand['company_name'] ?: $brand['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Stato</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Tutti</option>
                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Attive</option>
                    <option value="paused" <?php echo $filters['status'] === 'paused' ? 'selected' : ''; ?>>In pausa</option>
                    <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completate</option>
                    <option value="expired" <?php echo $filters['status'] === 'expired' ? 'selected' : ''; ?>>Scadute</option>
                </select>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 me-2">
                    <i class="fas fa-search"></i> Cerca
                </button>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
                <a href="?action=list" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-refresh"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>
                
                <!-- Tabella Campagne -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista Campagne 
                            <span class="badge bg-secondary"><?php echo $total_count; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($campaigns)): ?>
                            <div class="alert alert-info text-center py-4">
                                <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                                <h5>Nessuna campagna trovata</h5>
                                <p class="text-muted">Utilizza i filtri per trovare le campagne o <a href="?action=add">creane una nuova</a>.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Titolo Campagna</th>
                                            <th>Brand</th>
                                            <th>Budget</th>
                                            <th>Stato</th>
                                            <th>Date</th>
                                            <th>Scadenza</th>
                                            <th>Richieste Pausa</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($campaigns as $campaign): 
    $pause_requests = getCampaignPauseRequests($campaign['id']);
    $pending_requests = array_filter($pause_requests, function($req) {
        return $req['status'] === 'pending';
    });

    
    // CORREZIONE MIGLIORATA: Logica più robusta per rilevare campagne scadute
    $current_time = time();
    $deadline_time = $campaign['deadline_date'] ? strtotime($campaign['deadline_date']) : null;
    
    $is_expired = ($campaign['status'] === 'expired') || 
                 ($deadline_time && $deadline_time < $current_time && 
                  in_array($campaign['status'], ['paused', 'active']));

?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <?php if (!$campaign['is_public']): ?>
                                                                <span class="badge bg-secondary" title="Campagna privata">
                                                                    <i class="fas fa-lock"></i>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($campaign['name']); ?></strong>
                                                            <?php if ($campaign['niche']): ?>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($campaign['niche']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
    <strong><?php echo htmlspecialchars_decode($campaign['brand_display_name']); ?></strong>
</td>
                                                <td>
                                                    <strong><?php echo number_format($campaign['budget'], 2); ?> <?php echo htmlspecialchars($campaign['currency']); ?></strong>
                                                </td>
                                                <td>
    <?php 
    // CALCOLO STATO: Priorità alla scadenza
    if ($campaign['status'] === 'expired') {
        // Caso 1: Status esplicitamente 'expired'
        echo '<span class="badge bg-danger"><i class="fas fa-clock me-1"></i> Scaduta</span>';
    } elseif ($campaign['deadline_date'] && strtotime($campaign['deadline_date']) < time()) {
        // Caso 2: Deadline passata per campagne active/paused
        echo '<span class="badge bg-danger"><i class="fas fa-clock me-1"></i> Scaduta</span>';
    } else {
        // Caso 3: Altri stati normali
        switch ($campaign['status']) {
            case 'active':
                echo '<span class="badge bg-success"><i class="fas fa-play me-1"></i> Attiva</span>';
                break;
            case 'paused':
                echo '<span class="badge bg-warning"><i class="fas fa-pause me-1"></i> In pausa</span>';
                if (count($pending_requests) > 0) {
                    echo ' <span class="badge bg-danger" title="Richieste integrazioni pendenti"><i class="fas fa-exclamation-circle"></i></span>';
                }
                break;
            case 'completed':
                echo '<span class="badge bg-info"><i class="fas fa-check me-1"></i> Completata</span>';
                break;
            case 'draft':
                echo '<span class="badge bg-secondary"><i class="fas fa-edit me-1"></i> Bozza</span>';
                break;
            default:
                echo '<span class="badge bg-light text-dark">' . htmlspecialchars($campaign['status']) . '</span>';
        }
    }
    ?>
</td>
                                                <td>
                                                    <small>
                                                        <strong>Inizio:</strong> <?php echo date('d/m/Y', strtotime($campaign['start_date'])); ?><br>
                                                        <?php if ($campaign['end_date']): ?>
                                                            <strong>Fine:</strong> <?php echo date('d/m/Y', strtotime($campaign['end_date'])); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Nessuna fine</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($campaign['deadline_date']): ?>
                                                        <small class="<?php echo strtotime($campaign['deadline_date']) < time() ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                                            <?php echo date('d/m/Y', strtotime($campaign['deadline_date'])); ?>
                                                            <?php if (strtotime($campaign['deadline_date']) < time()): ?>
                                                                <br><span class="badge bg-danger">Scaduta</span>
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Nessuna</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (count($pause_requests) > 0): ?>
                                                        <div class="small">
                                                            <span class="badge bg-<?php echo count($pending_requests) > 0 ? 'warning' : 'secondary'; ?>">
                                                                <?php echo count($pending_requests); ?> pendenti
                                                            </span>
                                                            <span class="badge bg-info">
                                                                <?php echo count($pause_requests); ?> totali
                                                            </span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Nessuna</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" style="gap: 2px;">
                                                        <!-- Modifica -->
                                                        <a href="?action=edit&id=<?php echo $campaign['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Modifica">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <!-- Pausa/Riprendi/Riattiva -->
<?php if ($campaign['status'] === 'active'): ?>
    <button type="button" class="btn btn-outline-warning" 
            data-bs-toggle="modal" 
            data-bs-target="#pauseModal<?php echo $campaign['id']; ?>"
            title="Metti in pausa">
        <i class="fas fa-pause"></i>
    </button>
<?php elseif ($campaign['status'] === 'paused' && !$is_expired): ?>
    <form method="post" class="d-inline">
        <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
        <input type="hidden" name="action_type" value="resume">
        <button type="submit" class="btn btn-outline-success" 
                onclick="return confirm('Riprendi questa campagna?')"
                title="Riprendi">
            <i class="fas fa-play"></i>
        </button>
    </form>
<?php elseif ($campaign['status'] === 'expired' || $is_expired): ?>
    <form method="post" class="d-inline">
        <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
        <input type="hidden" name="action_type" value="reactivate">
        <button type="submit" class="btn btn-outline-success" 
                onclick="return confirm('Riattivare questa campagna scaduta?')"
                title="Riattiva">
            <i class="fas fa-redo"></i>
        </button>
    </form>
<?php endif; ?>
                                                        
                                                        <!-- Completa -->
                                                        <?php if (in_array($campaign['status'], ['active', 'paused']) && !$is_expired): ?>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                                <input type="hidden" name="action_type" value="complete">
                                                                <button type="submit" class="btn btn-outline-info" 
                                                                        onclick="return confirm('Segnare come completata questa campagna?')"
                                                                        title="Segna come completata">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Elimina -->
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                            <input type="hidden" name="action_type" value="delete">
                                                            <button type="submit" class="btn btn-outline-danger" 
                                                                    onclick="return confirm('Sei sicuro di voler eliminare questa campagna?')"
                                                                    title="Elimina">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Modal Pausa Campagna -->
                                            <div class="modal fade" id="pauseModal<?php echo $campaign['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="post">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Metti in Pausa Campagna</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                                                <input type="hidden" name="action_type" value="pause">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Motivo della pausa <span class="text-danger">*</span></label>
                                                                    <textarea class="form-control" name="pause_reason" rows="3" 
                                                                              placeholder="Spiega al brand perché la campagna viene messa in pausa..." required></textarea>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Informazioni aggiuntive richieste <span class="text-danger">*</span></label>
                                                                    <textarea class="form-control" name="required_documents" rows="2" 
                                                                              placeholder="Specifica quali informazioni aggiuntive sono richieste..." required></textarea>
                                                                    <div class="form-text">Es: Certificazioni, documenti fiscali, prove di conformità, etc.</div>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Scadenza per l'invio <span class="text-danger">*</span></label>
                                                                    <input type="date" class="form-control" name="deadline" 
                                                                           min="<?php echo date('Y-m-d'); ?>" required>
                                                                    <div class="form-text">La campagna scadrà automaticamente se il brand non fornisce le informazioni entro questa data</div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                                <button type="submit" class="btn btn-warning">Conferma Pausa</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginazione -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Paginazione campagne">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=1<?php echo buildQueryString($filters); ?>">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $page - 1; ?><?php echo buildQueryString($filters); ?>">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?action=list&page=<?php echo $i; ?><?php echo buildQueryString($filters); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $page + 1; ?><?php echo buildQueryString($filters); ?>">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $total_pages; ?><?php echo buildQueryString($filters); ?>">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                </ul>
                                <div class="text-center text-muted mt-2">
                                    Pagina <?php echo $page; ?> di <?php echo $total_pages; ?> 
                                    (Totale: <?php echo $total_count; ?> campagne)
                                </div>
                            </nav>
                            <?php endif; ?>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    
} elseif ($action === 'add' || $action === 'edit') {
    // Pagina aggiungi/modifica campagna
    $campaign = null;
    if ($action === 'edit' && $id) {
        $campaign = getCampaignById($id);
        if (!$campaign) {
            header('Location: brand-campaigns.php');
            exit;
        }
        
        // Recupera cronologia pause per questa campagna
        $pause_history = getCampaignPauseHistory($id);
        $pause_stats = getCampaignPauseStats($id);
    }
    
    $brands_list = getAllBrands();
    $platforms = ['instagram', 'facebook', 'tiktok', 'youtube', 'twitter', 'linkedin'];
    $niches = ['Fashion', 'Beauty', 'Lifestyle', 'Travel', 'Food', 'Fitness', 'Tech', 'Gaming', 'Parenting', 'Business'];
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">
                        <i class="fas fa-bullhorn me-2"></i><?php echo $action === 'add' ? 'Nuova Campagna' : 'Modifica Campagna'; ?>
                    </h1>
                    <a href="?action=list" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Torna alla lista
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Tab navigazione -->
                <ul class="nav nav-tabs mb-4" id="campaignTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" 
                                data-bs-target="#basic" type="button" role="tab">
                            <i class="fas fa-info-circle me-2"></i>Informazioni Base
                        </button>
                    </li>
                    <?php if ($action === 'edit'): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="integrations-tab" data-bs-toggle="tab" 
                                data-bs-target="#integrations" type="button" role="tab">
                            <i class="fas fa-pause-circle me-2"></i>Integrazione Informazioni
                            <?php if ($pause_stats['pending_requests'] > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $pause_stats['pending_requests']; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="tab-content" id="campaignTabsContent">
                    <!-- Tab Informazioni Base -->
                    <div class="tab-pane fade show active" id="basic" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <form method="post">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">Titolo Campagna <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="name" name="name" 
                                                       value="<?php echo htmlspecialchars($campaign['name'] ?? ''); ?>" 
                                                       required maxlength="100">
                                                <div class="form-text">Inserisci un titolo descrittivo per la campagna</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="brand_id" class="form-label">Brand <span class="text-danger">*</span></label>
                                                <select class="form-select" id="brand_id" name="brand_id" required>
                                                    <option value="">Seleziona un brand</option>
                                                    <?php foreach ($brands_list as $brand): ?>
                                                        <option value="<?php echo $brand['id']; ?>" 
                                                                <?php echo ($campaign['brand_id'] ?? '') == $brand['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($brand['company_name'] ?: $brand['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Descrizione</label>
                                        <textarea class="form-control" id="description" name="description" 
                                                  rows="4"><?php echo htmlspecialchars($campaign['description'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="budget" class="form-label">Budget</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="budget" name="budget" 
                                                           step="0.01" min="0"
                                                           value="<?php echo htmlspecialchars($campaign['budget'] ?? ''); ?>">
                                                    <select class="form-select" name="currency" style="max-width: 100px;">
                                                        <option value="EUR" <?php echo ($campaign['currency'] ?? 'EUR') === 'EUR' ? 'selected' : ''; ?>>EUR</option>
                                                        <option value="USD" <?php echo ($campaign['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD</option>
                                                        <option value="GBP" <?php echo ($campaign['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>GBP</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="niche" class="form-label">Categoria</label>
                                                <select class="form-select" id="niche" name="niche">
                                                    <option value="">Seleziona categoria</option>
                                                    <?php foreach ($niches as $niche): ?>
                                                        <option value="<?php echo $niche; ?>" 
                                                                <?php echo ($campaign['niche'] ?? '') === $niche ? 'selected' : ''; ?>>
                                                            <?php echo $niche; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Piattaforme Social</label>
                                        <div class="row">
                                            <?php 
                                            $selected_platforms = [];
                                            if (isset($campaign['platforms']) && $campaign['platforms']) {
                                                $selected_platforms = json_decode($campaign['platforms'], true) ?: [];
                                            }
                                            ?>
                                            <?php foreach ($platforms as $platform): ?>
                                                <div class="col-md-2 col-sm-4 col-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="platforms[]" 
                                                               value="<?php echo $platform; ?>" 
                                                               id="platform_<?php echo $platform; ?>"
                                                               <?php echo in_array($platform, $selected_platforms) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="platform_<?php echo $platform; ?>">
                                                            <i class="fab fa-<?php echo $platform; ?> me-1"></i>
                                                            <?php echo ucfirst($platform); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="target_audience" class="form-label">Target Audience</label>
                                        <input type="text" class="form-control" id="target_audience" name="target_audience" 
                                               value="<?php echo htmlspecialchars($campaign['target_audience'] ?? ''); ?>"
                                               placeholder="Es: 18-35 anni, Italia">
                                        <div class="form-text">Descrivi il pubblico target della campagna</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="requirements" class="form-label">Requisiti specifici per gli influencer</label>
                                        <textarea class="form-control" id="requirements" name="requirements" 
                                                  rows="3"><?php echo htmlspecialchars($campaign['requirements'] ?? ''); ?></textarea>
                                    </div>

                                    <!-- Campo Scadenza -->
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="deadline_date" class="form-label">Scadenza</label>
                                                <input type="date" class="form-control" id="deadline_date" name="deadline_date" 
                                                       value="<?php echo htmlspecialchars($campaign['deadline_date'] ?? ''); ?>">
                                                <div class="form-text">Data di scadenza per le campagne in pausa</div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">Stato</label>
                                                <select class="form-select" id="status" name="status">
                                                    <option value="draft" <?php echo ($campaign['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Bozza</option>
                                                    <option value="active" <?php echo ($campaign['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Attiva</option>
                                                    <option value="paused" <?php echo ($campaign['status'] ?? '') === 'paused' ? 'selected' : ''; ?>>In pausa</option>
                                                    <option value="completed" <?php echo ($campaign['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completata</option>
                                                    <option value="expired" <?php echo ($campaign['status'] ?? '') === 'expired' ? 'selected' : ''; ?>>Scaduta</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <div class="mb-3 form-check form-switch">
                                                <input type="checkbox" class="form-check-input" id="is_public" name="is_public" 
                                                       <?php echo ($campaign['is_public'] ?? 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="is_public">Campagna pubblica</label>
                                                <div class="form-text">Visibile agli influencer</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3 form-check form-switch">
                                                <input type="checkbox" class="form-check-input" id="allow_applications" name="allow_applications" 
                                                       <?php echo ($campaign['allow_applications'] ?? 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="allow_applications">Accetta applicazioni</label>
                                                <div class="form-text">Gli influencer possono candidarsi</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="save_campaign" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Salva Campagna
                                        </button>
                                        <a href="?action=list" class="btn btn-secondary">Annulla</a>
                                        
                                        <?php if ($action === 'edit' && $campaign): ?>
                                        <div class="ms-auto">
                                            <small class="text-muted">
                                                Creata il: <?php echo date('d/m/Y H:i', strtotime($campaign['created_at'])); ?>
                                                <?php if ($campaign['updated_at'] && $campaign['updated_at'] != $campaign['created_at']): ?>
                                                    <br>Modificata il: <?php echo date('d/m/Y H:i', strtotime($campaign['updated_at'])); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Integrazione Informazioni (solo per modifica) -->
                    <?php if ($action === 'edit'): ?>
                    <div class="tab-pane fade" id="integrations" role="tabpanel">
                        <!-- Statistiche Rapide -->
                        <div class="row mb-4">
                            <div class="col-xl-2 col-md-4 col-6">
                                <div class="card bg-primary text-white mb-2">
                                    <div class="card-body text-center p-3">
                                        <div class="fs-4 fw-bold"><?php echo $pause_stats['total_requests']; ?></div>
                                        <div class="small">Totali</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6">
                                <div class="card bg-warning text-white mb-2">
                                    <div class="card-body text-center p-3">
                                        <div class="fs-4 fw-bold"><?php echo $pause_stats['pending_requests']; ?></div>
                                        <div class="small">In Attesa</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6">
                                <div class="card bg-info text-white mb-2">
                                    <div class="card-body text-center p-3">
                                        <div class="fs-4 fw-bold"><?php echo $pause_stats['documents_uploaded_requests']; ?></div>
                                        <div class="small">Documenti Caricati</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6">
                                <div class="card bg-secondary text-white mb-2">
                                    <div class="card-body text-center p-3">
                                        <div class="fs-4 fw-bold"><?php echo $pause_stats['under_review_requests']; ?></div>
                                        <div class="small">In Revisione</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6">
                                <div class="card bg-success text-white mb-2">
                                    <div class="card-body text-center p-3">
                                        <div class="fs-4 fw-bold"><?php echo $pause_stats['approved_requests']; ?></div>
                                        <div class="small">Approvate</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6">
                                <div class="card bg-danger text-white mb-2">
                                    <div class="card-body text-center p-3">
                                        <div class="fs-4 fw-bold"><?php echo $pause_stats['changes_requested_requests']; ?></div>
                                        <div class="small">Modifiche Richieste</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Timeline Cronologia Pause - TRASFORMATA IN ACCORDION -->
                        <div class="card">
                            <div class="card-header bg-dark text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i>Cronologia Richieste di Pausa
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($pause_history)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-pause-circle fa-3x text-muted mb-3"></i>
                                        <h5>Nessuna richiesta di pausa</h5>
                                        <p class="text-muted">Non ci sono richieste di integrazione informazioni per questa campagna.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="accordion" id="pauseRequestsAccordion" data-exclude-sidebar-toggle="true">
                                        <?php foreach ($pause_history as $index => $pause): 
                                            $documents = getPauseRequestDocuments($pause['id']);
                                            $status_config = getPauseRequestStatusConfig($pause['status']);
                                            $accordion_id = 'pauseRequest' . $pause['id'];
                                        ?>
                                            <div class="accordion-item mb-3">
                                                <h2 class="accordion-header" id="heading<?php echo $accordion_id; ?>">
                                                    <button class="accordion-button collapsed" type="button" 
                                                            data-bs-toggle="collapse" 
                                                            data-bs-target="#collapse<?php echo $accordion_id; ?>" 
                                                            aria-expanded="false" 
                                                            aria-controls="collapse<?php echo $accordion_id; ?>">
                                                        <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                                            <div>
                                                                <i class="fas fa-chevron-down accordion-arrow me-2"></i>
                                                                <strong>Richiesta del <?php echo date('d/m/Y - H:i', strtotime($pause['created_at'])); ?></strong>
                                                            </div>
                                                            <div>
                                                                <span class="badge <?php echo $status_config['badge_class']; ?> me-2">
                                                                    <i class="<?php echo $status_config['icon']; ?> me-1"></i>
                                                                    <?php echo $status_config['label']; ?>
                                                                </span>
                                                                <?php if ($pause['deadline'] && strtotime($pause['deadline']) < time() && in_array($pause['status'], ['pending', 'documents_uploaded'])): ?>
                                                                    <span class="badge bg-danger">
                                                                        <i class="fas fa-clock me-1"></i>Scaduta
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </button>
                                                </h2>
                                                <div id="collapse<?php echo $accordion_id; ?>" 
                                                     class="accordion-collapse collapse" 
                                                     aria-labelledby="heading<?php echo $accordion_id; ?>" 
                                                     data-bs-parent="#pauseRequestsAccordion">
                                                    <div class="accordion-body">
                                                        <!-- Informazioni Base Richiesta -->
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <strong>Creata da:</strong>
                                                                <span class="text-muted"><?php echo htmlspecialchars($pause['admin_name'] ?? 'Admin'); ?></span>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <strong>Documenti:</strong>
                                                                <span class="badge bg-info"><?php echo $pause['documents_count']; ?> file</span>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Motivo Pausa -->
                                                        <div class="mb-3">
                                                            <strong>Motivo della pausa:</strong>
                                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($pause['pause_reason'])); ?></p>
                                                        </div>
                                                        
                                                        <!-- Documenti Richiesti -->
                                                        <?php if (!empty($pause['required_documents'])): ?>
                                                            <div class="mb-3">
                                                                <strong>Documenti richiesti:</strong>
                                                                <p class="mb-1"><?php echo nl2br(htmlspecialchars($pause['required_documents'])); ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Scadenza -->
                                                        <?php if ($pause['deadline']): ?>
                                                            <div class="mb-3">
                                                                <strong>Scadenza:</strong>
                                                                <span class="<?php echo strtotime($pause['deadline']) < time() ? 'text-danger fw-bold' : ''; ?>">
                                                                    <?php echo date('d/m/Y', strtotime($pause['deadline'])); ?>
                                                                    <?php if (strtotime($pause['deadline']) < time()): ?>
                                                                        <small class="text-muted">(Scaduta)</small>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Commento Brand -->
<?php if (!empty($pause['brand_upload_comment'])): ?>
    <div class="border rounded p-3 mb-3 bg-light" style="border-color: #6c757d !important; border-left-width: 4px;">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <strong>
                    <i class="fas fa-building me-2"></i>Commento del Brand:
                </strong>
            </div>
            <?php if ($pause['brand_uploaded_at']): ?>
    <small class="text-muted">
        <i class="fas fa-clock me-1"></i>
        <?php echo date('d/m/Y - H:i', strtotime($pause['brand_uploaded_at'])); ?>
    </small>
<?php endif; ?>
        </div>
        <p class="mb-0"><?php echo nl2br(htmlspecialchars($pause['brand_upload_comment'])); ?></p>
    </div>
<?php endif; ?>
                                                        
 <!-- Documenti Caricati -->
<?php if (!empty($documents)): ?>
    <div class="mb-3">
        <strong>Documenti caricati dal brand:</strong>
        <div class="mt-2">
            <?php foreach ($documents as $doc): ?>
                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                    <div>
                        <i class="fas fa-file me-2"></i>
                        <a href="/admin/download-document.php?id=<?php echo $doc['id']; ?>" 
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
                        <!-- Pulsante Download -->
                        <a href="/admin/download-document.php?id=<?php echo $doc['id']; ?>" 
                           class="btn btn-sm btn-outline-primary" target="_blank" title="Scarica documento">
                            <i class="fas fa-download"></i>
                        </a>
                        <!-- Pulsante Elimina - SEMPRE VISIBILE -->
                        <button type="button" class="btn btn-sm btn-outline-danger ms-1" 
                                data-bs-toggle="modal" 
                                data-bs-target="#deleteDocumentModal<?php echo $doc['id']; ?>"
                                title="Elimina documento">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>

                <!-- Modal Conferma Eliminazione - SEMPRE VISIBILE -->
                <div class="modal fade" id="deleteDocumentModal<?php echo $doc['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="post" action="/admin/delete-document.php">
                                <div class="modal-header">
                                    <h5 class="modal-title text-danger">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Conferma Eliminazione
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Sei sicuro di voler eliminare il documento <strong>"<?php echo htmlspecialchars($doc['original_name']); ?>"</strong>?</p>
                                    <p class="text-muted small">Questa azione non può essere annullata. Il file verrà rimosso definitivamente dal server.</p>
                                    
                                    <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash me-1"></i> Elimina Definitivamente
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
                                                        
                                                        <!-- Commento Admin e Azioni -->
                                                        <?php if (!empty($pause['admin_review_comment'])): ?>
    <div class="border rounded p-3 mb-3 bg-light" style="border-color: #0dcaf0 !important; border-left-width: 4px;">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <strong class="text-info">
                    <i class="fas fa-user-tie me-2"></i>Commento Admin:
                </strong>
            </div>
            <small class="text-muted">
    <i class="fas fa-clock me-1"></i>
    <?php echo date('d/m/Y - H:i', strtotime($pause['admin_reviewed_at'])); ?>
</small>
        </div>
        <p class="mb-2"><?php echo nl2br(htmlspecialchars($pause['admin_review_comment'])); ?></p>
        <small class="text-muted d-block mt-1">
    <i class="fas fa-user me-1"></i>
    Revisionato da: <?php echo htmlspecialchars($pause['reviewed_by_name'] ?? 'Admin'); ?>
</small>
    </div>
<?php endif; ?>
                                                        
                                                        <!-- Azioni Admin -->
                                                        <?php if (in_array($pause['status'], ['documents_uploaded', 'under_review', 'changes_requested'])): ?>
                                                            <div class="border-top pt-3">
                                                                <h6>Azioni Revisione:</h6>
                                                                <form method="post" class="row g-3">
                                                                    <input type="hidden" name="pause_request_id" value="<?php echo $pause['id']; ?>">
                                                                    
                                                                    <div class="col-md-8">
                                                                        <label class="form-label">Commento Admin <?php if (in_array($pause['status'], ['changes_requested', 'under_review'])): ?><span class="text-danger">*</span><?php endif; ?></label>
                                                                        <textarea class="form-control" name="admin_comment" rows="3" 
                                                                                  placeholder="Inserisci commento per il brand..." 
                                                                                  <?php echo in_array($pause['status'], ['changes_requested', 'under_review']) ? 'required' : ''; ?>><?php echo htmlspecialchars($pause['admin_review_comment'] ?? ''); ?></textarea>
                                                                        <?php if (in_array($pause['status'], ['changes_requested', 'under_review'])): ?>
                                                                            <div class="form-text text-danger">Il commento è obbligatorio quando si richiedono modifiche o si approva</div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    
                                                                    <div class="col-md-4">
                                                                        <label class="form-label">Azione</label>
                                                                        <div class="d-grid gap-2">
                                                                            <?php if ($pause['status'] === 'documents_uploaded'): ?>
                                                                                <button type="submit" name="review_action" value="mark_under_review" 
                                                                                        class="btn btn-info btn-sm">
                                                                                    <i class="fas fa-search me-1"></i>Metti in Revisione
                                                                                </button>
                                                                            <?php endif; ?>
                                                                            
                                                                            <button type="submit" name="review_action" value="approve" 
                                                                                    class="btn btn-success btn-sm">
                                                                                <i class="fas fa-check me-1"></i>Approva
                                                                            </button>
                                                                            
                                                                            <button type="submit" name="review_action" value="request_changes" 
                                                                                    class="btn btn-warning btn-sm">
                                                                                <i class="fas fa-edit me-1"></i>Richiedi Modifiche
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        <?php elseif ($pause['status'] === 'pending' && empty($documents)): ?>
                                                            <div class="alert alert-warning">
                                                                <i class="fas fa-clock me-2"></i>
                                                                In attesa che il brand carichi i documenti richiesti
                                                            </div>
                                                        <?php elseif ($pause['status'] === 'approved'): ?>
                                                            <div class="alert alert-success">
                                                                <i class="fas fa-check-circle me-2"></i>
                                                                Documenti approvati il <?php echo date('d/m/Y H:i', strtotime($pause['admin_reviewed_at'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    /* Stili per l'accordion delle richieste di pausa */
    .accordion-button:not(.collapsed) .accordion-arrow {
        transform: rotate(180deg);
        transition: transform 0.2s ease-in-out;
    }

    .accordion-arrow {
        transition: transform 0.2s ease-in-out;
        font-size: 0.8rem;
    }

    .accordion-button {
        font-weight: 500;
    }

    .accordion-button:focus {
        box-shadow: none;
        border-color: rgba(0,0,0,.125);
    }

    /* Stili per i pulsanti documento */
    .btn-document {
        transition: all 0.2s ease-in-out;
    }

    .btn-document:hover {
        transform: translateY(-1px);
    }

    /* Mantieni la timeline per altri utilizzi se necessario */
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    .timeline-item {
        position: relative;
    }
    .timeline-marker {
        position: absolute;
        left: -30px;
        top: 0;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
    }
    .timeline-content {
        margin-left: 0;
    }
    </style>
    
    <?php
    
} else {
    // Azione non riconosciuta, redirect alla lista
    header('Location: brand-campaigns.php');
    exit;
}

ob_end_flush();

require_once '../includes/admin_footer.php';

/**
 * Helper function per la configurazione degli stati
 */
function getPauseRequestStatusConfig($status) {
    $configs = [
        'pending' => [
            'label' => 'In attesa documenti',
            'badge_class' => 'bg-warning',
            'icon' => 'fas fa-clock'
        ],
        'documents_uploaded' => [
            'label' => 'Documenti caricati',
            'badge_class' => 'bg-info',
            'icon' => 'fas fa-file-upload'
        ],
        'under_review' => [
            'label' => 'In revisione',
            'badge_class' => 'bg-secondary',
            'icon' => 'fas fa-search'
        ],
        'approved' => [
            'label' => 'Approvato',
            'badge_class' => 'bg-success',
            'icon' => 'fas fa-check'
        ],
        'changes_requested' => [
            'label' => 'Modifiche richieste',
            'badge_class' => 'bg-warning text-dark',
            'icon' => 'fas fa-edit'
        ],
        'completed' => [
            'label' => 'Completato',
            'badge_class' => 'bg-primary',
            'icon' => 'fas fa-flag-checkered'
        ],
        'cancelled' => [
            'label' => 'Cancellato',
            'badge_class' => 'bg-danger',
            'icon' => 'fas fa-times'
        ]
    ];
    
    return $configs[$status] ?? $configs['pending'];
}

/**
 * Formatta la dimensione del file
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return "0 Bytes";
    $k = 1024;
    $sizes = ["Bytes", "KB", "MB", "GB"];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . " " . $sizes[$i];
}