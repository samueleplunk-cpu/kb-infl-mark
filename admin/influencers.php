<?php
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
require_once '../includes/admin_header.php';

checkAdminLogin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = '';

// Gestione azioni
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_influencer'])) {
        $data = [
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Gestione password per nuovo influencer (ADD)
        if ($action === 'add') {
            if (isset($_POST['generate_password']) && $_POST['generate_password'] == 'on') {
                // Usa password generata
                if (!empty($_POST['generated_password'])) {
                    $data['password'] = $_POST['generated_password'];
                } else {
                    // Genera una nuova password casuale
                    $data['password'] = generateRandomPassword();
                }
            } elseif (!empty($_POST['password'])) {
                // Usa password inserita manualmente
                $data['password'] = $_POST['password'];
            } else {
                $message = '<div class="alert alert-danger">È necessario specificare una password per il nuovo influencer</div>';
            }
        }
        
        // Gestione password per modifica influencer (EDIT)
        if ($action === 'edit' && !empty($_POST['password'])) {
            $data['password'] = $_POST['password'];
        }
        
        // Validazione password se presente
        if (!empty($data['password'])) {
            $password_validation = validatePasswordStrength($data['password']);
            if ($password_validation !== true) {
                $message = '<div class="alert alert-danger">' . $password_validation . '</div>';
            }
        }
        
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $message = '<div class="alert alert-danger">Email non valida</div>';
} elseif (empty($message)) {
    $success = saveInfluencer($data, $id);
    if ($success) {
        $message = '<div class="alert alert-success">Influencer salvato con successo!</div>';
        if (!$id) {
            $action = 'list';
        }
    } else {
        $message = '<div class="alert alert-danger">Errore nel salvataggio</div>';
    }
}
    }
    
    // Gestione azioni rapide
    if (isset($_POST['action_type']) && isset($_POST['influencer_id'])) {
        $influencer_id = $_POST['influencer_id'];
        $action_type = $_POST['action_type'];
        
        switch($action_type) {
            case 'suspend':
                $suspension_end = $_POST['suspension_end'] ?? null;
                updateInfluencerStatus($influencer_id, 'suspend', $suspension_end);
                $message = '<div class="alert alert-warning">Influencer sospeso</div>';
                break;
                
            case 'unsuspend':
                updateInfluencerStatus($influencer_id, 'unsuspend');
                $message = '<div class="alert alert-success">Sospensione rimossa</div>';
                break;
                
            case 'block':
                updateInfluencerStatus($influencer_id, 'block');
                $message = '<div class="alert alert-danger">Influencer bloccato</div>';
                break;
                
            case 'unblock':
                updateInfluencerStatus($influencer_id, 'unblock');
                $message = '<div class="alert alert-success">Blocco rimosso</div>';
                break;
                
            case 'delete':
                // Usa l'eliminazione completa invece del soft delete
                $success = deleteInfluencerCompletely($influencer_id);
                if ($success) {
                    $message = '<div class="alert alert-info">Influencer eliminato completamente dal sistema</div>';
                } else {
                    $message = '<div class="alert alert-danger">Errore nell\'eliminazione dell\'influencer</div>';
                }
                break;
                
            case 'restore':
                updateInfluencerStatus($influencer_id, 'restore');
                $message = '<div class="alert alert-success">Influencer ripristinato</div>';
                break;
        }
    }
}

// Pagina lista influencer
if ($action === 'list') {
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 15;
    
    $filters = [
        'status' => $_GET['status'] ?? '',
        'search' => $_GET['search'] ?? ''
        // Rimosso i filtri date_from e date_to
    ];
    
    $result = getInfluencers($page, $per_page, $filters);
    $influencers = $result['data'];
    $total_pages = $result['total_pages'];
    $total_count = $result['total'];
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">Gestione Influencer</h1>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Aggiungi Influencer
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Filtri</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <input type="hidden" name="action" value="list">
                            
                            <div class="col-md-4">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Nome o email...">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="status" class="form-label">Stato</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Attivi</option>
                                    <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inattivi</option>
                                    <option value="suspended" <?php echo $filters['status'] === 'suspended' ? 'selected' : ''; ?>>Sospesi</option>
                                    <option value="blocked" <?php echo $filters['status'] === 'blocked' ? 'selected' : ''; ?>>Bloccati</option>
                                    <option value="deleted" <?php echo $filters['status'] === 'deleted' ? 'selected' : ''; ?>>Eliminati</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-outline-primary w-50">Applica</button>
                                <a href="?action=list" class="btn btn-outline-danger w-50">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabella Influencer -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            Lista Influencer 
                            <span class="badge bg-secondary"><?php echo $total_count; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($influencers)): ?>
                            <div class="alert alert-info">Nessun influencer trovato</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Email</th>
                                            <th>Stato</th>
                                            <th>Data Registrazione</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($influencers as $influencer): ?>
                                            <tr>
                                                <td><?php echo $influencer['id']; ?></td>
                                                <td>
    <?php 
    $display_name = $influencer['display_name'] ?? $influencer['name'];
    echo htmlspecialchars_decode($display_name); 
    ?>
</td>
                                                <td><?php echo htmlspecialchars($influencer['email']); ?></td>
                                                <td>
                                                    <?php 
                                                    $status_badge = '';
                                                    if ($influencer['deleted_at']) {
                                                        $status_badge = '<span class="badge bg-dark">Eliminato</span>';
                                                    } elseif ($influencer['is_blocked']) {
                                                        $status_badge = '<span class="badge bg-danger">Bloccato</span>';
                                                    } elseif ($influencer['is_suspended']) {
                                                        $status_badge = '<span class="badge bg-warning">Sospeso</span>';
                                                    } elseif ($influencer['is_active']) {
                                                        $status_badge = '<span class="badge bg-success">Attivo</span>';
                                                    } else {
                                                        $status_badge = '<span class="badge bg-secondary">Inattivo</span>';
                                                    }
                                                    echo $status_badge;
                                                    ?>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($influencer['created_at'])); ?></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <a href="?action=edit&id=<?php echo $influencer['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Modifica">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <?php if ($influencer['deleted_at']): ?>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                                <input type="hidden" name="action_type" value="restore">
                                                                <button type="submit" class="btn btn-outline-success" title="Ripristina">
                                                                    <i class="fas fa-undo"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <?php if ($influencer['is_suspended']): ?>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                                    <input type="hidden" name="action_type" value="unsuspend">
                                                                    <button type="submit" class="btn btn-outline-warning" title="Rimuovi sospensione">
                                                                        <i class="fas fa-play"></i>
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <button type="button" class="btn btn-outline-warning" 
                                                                        data-bs-toggle="modal" data-bs-target="#suspendModal<?php echo $influencer['id']; ?>"
                                                                        title="Sospendi">
                                                                    <i class="fas fa-pause"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($influencer['is_blocked']): ?>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                                    <input type="hidden" name="action_type" value="unblock">
                                                                    <button type="submit" class="btn btn-outline-info" title="Sblocca">
                                                                        <i class="fas fa-unlock"></i>
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                                    <input type="hidden" name="action_type" value="block">
                                                                    <button type="submit" class="btn btn-outline-danger" 
                                                                            onclick="return confirm('Sei sicuro di voler bloccare questo influencer?')"
                                                                            title="Blocca">
                                                                        <i class="fas fa-ban"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            
                                                            <!-- Pulsante Elimina completamente (APRE il modal) -->
                                                            <button type="button" class="btn btn-outline-dark" 
                                                                    data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $influencer['id']; ?>"
                                                                    title="Elimina completamente">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Modal Sospensione -->
                                                    <?php if (!$influencer['deleted_at'] && !$influencer['is_suspended']): ?>
                                                    <div class="modal fade" id="suspendModal<?php echo $influencer['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="post">
                                                                    <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                                    <input type="hidden" name="action_type" value="suspend">
                                                                    
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Sospendi Influencer</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label for="suspension_end<?php echo $influencer['id']; ?>" class="form-label">
                                                                                Data fine sospensione
                                                                            </label>
                                                                            <input type="datetime-local" class="form-control" 
                                                                                   id="suspension_end<?php echo $influencer['id']; ?>" 
                                                                                   name="suspension_end" required>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                                        <button type="submit" class="btn btn-warning">Conferma Sospensione</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Modal Eliminazione Definitiva -->
<div class="modal fade" id="deleteModal<?php echo $influencer['id']; ?>" tabindex="-1" 
     aria-labelledby="deleteModalLabel<?php echo $influencer['id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel<?php echo $influencer['id']; ?>">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Conferma Eliminazione Permanente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <strong>Dati Influencer da Eliminare</strong>
                    </div>
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-4"><strong>ID:</strong></div>
                            <div class="col-8"><?php echo $influencer['id']; ?></div>
                        </div>
                        <div class="row mb-2">
    <div class="col-4"><strong>Nome:</strong></div>
    <div class="col-8"><?php 
        $display_name = $influencer['display_name'] ?? $influencer['name'];
        echo htmlspecialchars_decode($display_name); 
    ?></div>
</div>
                        <div class="row">
                            <div class="col-4"><strong>Email:</strong></div>
                            <div class="col-8"><?php echo htmlspecialchars($influencer['email']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="card border-danger mb-3">
                    <div class="card-header bg-danger-subtle text-danger">
                        <strong><i class="fas fa-times-circle me-1"></i> Verranno eliminati definitivamente:</strong>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Tutti i dati dell'influencer</li>
                            <li>Le immagini del profilo</li>
                            <li>Le relazioni con le campagne</li>
                            <li>Tutte le conversazioni</li>
                        </ul>
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    <strong>Questa operazione NON può essere annullata!</strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Annulla
                </button>
                <form method="post" class="d-inline">
                    <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                    <input type="hidden" name="action_type" value="delete">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i> Conferma Eliminazione
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Paginazione -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Paginazione">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=1<?php echo buildQueryString($filters); ?>">Prima</a>
                                    </li>
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $page - 1; ?><?php echo buildQueryString($filters); ?>">Prec</a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?action=list&page=<?php echo $i; ?><?php echo buildQueryString($filters); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $page + 1; ?><?php echo buildQueryString($filters); ?>">Succ</a>
                                    </li>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=list&page=<?php echo $total_pages; ?><?php echo buildQueryString($filters); ?>">Ultima</a>
                                    </li>
                                </ul>
                            </nav>
                            <?php endif; ?>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
}

// Pagina aggiungi/modifica influencer
elseif ($action === 'add' || $action === 'edit') {
    $influencer = null;
    $title = $action === 'add' ? 'Aggiungi Influencer' : 'Modifica Influencer';
    
    if ($action === 'edit' && $id) {
        $influencer = getInfluencerById($id);
        if (!$influencer) {
            header('Location: influencers.php');
            exit;
        }
    }
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3"><?php echo $title; ?></h1>
                    <a href="?action=list" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Torna alla lista
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="post">
                            <div class="row">
                                <!-- Colonna sinistra: Dati base -->
                                <div class="col-md-6">
                                   <div class="mb-3">
    <label for="name" class="form-label">Nome <span class="text-muted">(opzionale)</span></label>
    <input type="text" class="form-control" id="name" name="name" 
           value="<?php 
           $display_value = !empty($influencer['display_name']) ? $influencer['display_name'] : ($influencer['name'] ?? '');
           echo htmlspecialchars_decode($display_value); 
           ?>">
</div>

<div class="mb-3">
    <label for="email" class="form-label">Email <span class="text-muted">(opzionale)</span></label>
    <input type="email" class="form-control" id="email" name="email" 
           value="<?php echo htmlspecialchars($influencer['email'] ?? ''); ?>">
</div>
                                    
                                    <!-- Campo Password - Versione ADD -->
                                    <?php if ($action === 'add'): ?>
                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            Password <span class="text-danger">*</span>
                                            <small class="text-muted">(obbligatoria per nuovo account)</small>
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="Inserisci password per l'influencer"
                                                   minlength="6" required>
                                            <button type="button" class="btn btn-outline-secondary toggle-password" 
                                                    data-target="password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">
                                            La password deve avere almeno 6 caratteri, contenere almeno una lettera e un numero
                                        </div>
                                        
                                        <!-- Opzione per generare password casuale -->
                                        <div class="form-check mt-2">
                                            <input type="checkbox" class="form-check-input" id="generate_password" name="generate_password">
                                            <label class="form-check-label" for="generate_password">
                                                Genera password casuale automaticamente
                                            </label>
                                        </div>
                                        
                                        <!-- Campo per mostrare password generata -->
                                        <div id="generated_password_container" class="mt-2" style="display: none;">
                                            <div class="alert alert-info">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>Password generata:</strong>
                                                        <span id="generated_password_display" class="font-monospace"></span>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="copy_password">
                                                        <i class="fas fa-copy"></i> Copia
                                                    </button>
                                                </div>
                                                <small class="d-block mt-1">Salva questa password! Non sarà più visibile dopo la creazione.</small>
                                            </div>
                                            <input type="hidden" name="generated_password" id="generated_password">
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Campo Password - Versione EDIT -->
                                    <?php if ($action === 'edit'): ?>
                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            Password 
                                            <small class="text-muted">(lascia vuoto per mantenere l'attuale)</small>
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="Nuova password (opzionale)"
                                                   minlength="6">
                                            <button type="button" class="btn btn-outline-secondary toggle-password" 
                                                    data-target="password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">
                                            Inserisci una nuova password solo se vuoi cambiarla
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                               <?php echo ($influencer['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Account attivo</label>
                                    </div>
                                    
                                    <!-- SEZIONE "Informazioni Account"-->
<?php if ($action === 'edit'): ?>
<?php
// Ottieni l'ID dalla tabella influencers (non user_id)
global $pdo;
$influencer_sql = "SELECT i.id FROM influencers i WHERE i.user_id = ?";
$influencer_stmt = $pdo->prepare($influencer_sql);
$influencer_stmt->execute([$id]);
$influencer_row = $influencer_stmt->fetch(PDO::FETCH_ASSOC);
$actual_influencer_id = $influencer_row ? $influencer_row['id'] : null;

// Conta gli sponsor
$total_sponsors = $actual_influencer_id ? countInfluencerTotalSponsors($actual_influencer_id) : 0;
$active_sponsors = $actual_influencer_id ? countInfluencerActiveSponsors($actual_influencer_id) : 0;

// NUOVO: Conta le segnalazioni del profilo
$report_count = 0;
if ($id) {
    $report_sql = "SELECT COUNT(*) as count FROM user_reports WHERE reported_user_id = ?";
    $report_stmt = $pdo->prepare($report_sql);
    $report_stmt->execute([$id]);
    $report_result = $report_stmt->fetch(PDO::FETCH_ASSOC);
    $report_count = $report_result ? $report_result['count'] : 0;
}
?>
<div class="card bg-light mt-3">
    <div class="card-body">
        <h6 class="card-title">Informazioni Account</h6>
        <ul class="list-unstyled small">
            <li><strong>ID:</strong> <?php echo $influencer['id']; ?></li>
            <li><strong>Registrato il:</strong> <?php echo date('d/m/Y H:i', strtotime($influencer['created_at'])); ?></li>
            <li><strong>Ultimo aggiornamento:</strong> <?php echo date('d/m/Y H:i', strtotime($influencer['updated_at'])); ?></li>
            <!-- NUOVE VOCI SPONSOR -->
            <li><strong>Sponsor totali:</strong> <?php echo $total_sponsors; ?></li>
            <li><strong>Sponsor attivi:</strong> <?php echo $active_sponsors; ?></li>
            <!-- NUOVA VOCE SEGNALAZIONI -->
            <li><strong>Segnalazioni profilo:</strong> <?php echo $report_count; ?></li>
            <?php if ($influencer['last_login']): ?>
                <li><strong>Ultimo login:</strong> <?php echo date('d/m/Y H:i', strtotime($influencer['last_login'])); ?></li>
            <?php endif; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
                                </div>
                                
                                <!-- Colonna destra: SOLO Anteprima Immagine Profilo (senza testo) -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Immagine Profilo</label>
                                        <div class="text-center">
                                            <?php
                                            // Placeholder specifico per admin nella pagina edit
                                            $placeholder_admin = '../uploads/placeholder/influencer_admin_edit.png';
                                            
                                            // Placeholder generico come fallback
                                            $placeholder_generic = '../uploads/placeholder/user_placeholder.png';
                                            
                                            // Verifica quale placeholder esiste
                                            $placeholder_path = dirname(__DIR__) . '/uploads/placeholder/influencer_admin_edit.png';
                                            $placeholder_to_use = file_exists($placeholder_path) ? $placeholder_admin : $placeholder_generic;
                                            
                                            $avatar_url = $placeholder_to_use;
                                            
                                            if ($action === 'edit' && $influencer) {
                                                // Priorità 1: immagine profilo caricata dall'influencer (tabella influencers)
                                                if (!empty($influencer['influencer_avatar'])) {
                                                    $avatar_path = '../uploads/' . htmlspecialchars($influencer['influencer_avatar']);
                                                    // Verifica se il file esiste fisicamente
                                                    $full_path = dirname(__DIR__) . '/uploads/' . $influencer['influencer_avatar'];
                                                    if (file_exists($full_path)) {
                                                        $avatar_url = $avatar_path;
                                                    }
                                                } 
                                                // Priorità 2: avatar legacy dalla tabella users
                                                elseif (!empty($influencer['avatar'])) {
                                                    $avatar_path = '../uploads/' . htmlspecialchars($influencer['avatar']);
                                                    // Verifica se il file esiste fisicamente
                                                    $full_path = dirname(__DIR__) . '/uploads/' . $influencer['avatar'];
                                                    if (file_exists($full_path)) {
                                                        $avatar_url = $avatar_path;
                                                    }
                                                }
                                            }
                                            ?>
                                            <img src="<?php echo $avatar_url; ?>" 
                                                 class="img-thumbnail rounded-circle mb-3" 
                                                 id="avatar-preview"
                                                 style="width: 200px; height: 200px; object-fit: cover;"
                                                 alt="Anteprima immagine profilo"
                                                 onerror="this.onerror=null; this.src='<?php echo $placeholder_to_use; ?>';">
                                            <!-- L'immagine rimane visibile ma SENZA testo sotto -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" name="save_influencer" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salva
                                </button>
                                <a href="?action=list" class="btn btn-secondary">Annulla</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Funzione per mostrare/nascondere password
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordInput = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
        
        // Generazione password casuale (solo per ADD)
        const generatePasswordCheckbox = document.getElementById('generate_password');
        const passwordInput = document.getElementById('password');
        const generatedPasswordContainer = document.getElementById('generated_password_container');
        const generatedPasswordDisplay = document.getElementById('generated_password_display');
        const generatedPasswordInput = document.getElementById('generated_password');
        const copyPasswordButton = document.getElementById('copy_password');
        
        if (generatePasswordCheckbox && passwordInput) {
            generatePasswordCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    // Disabilita input manuale
                    passwordInput.disabled = true;
                    passwordInput.required = false;
                    passwordInput.placeholder = 'Password verrà generata automaticamente';
                    
                    // Genera password casuale
                    const randomPassword = generateRandomPassword();
                    generatedPasswordDisplay.textContent = randomPassword;
                    generatedPasswordInput.value = randomPassword;
                    generatedPasswordContainer.style.display = 'block';
                } else {
                    // Riabilita input manuale
                    passwordInput.disabled = false;
                    passwordInput.required = true;
                    passwordInput.placeholder = 'Inserisci password per l\'influencer';
                    passwordInput.focus();
                    
                    generatedPasswordContainer.style.display = 'none';
                    generatedPasswordInput.value = '';
                }
            });
        }
        
        // Funzione per copiare password
        if (copyPasswordButton) {
            copyPasswordButton.addEventListener('click', function() {
                const password = generatedPasswordInput.value;
                navigator.clipboard.writeText(password).then(() => {
                    const originalIcon = this.querySelector('i');
                    const originalText = this.innerHTML;
                    
                    this.innerHTML = '<i class="fas fa-check"></i> Copiata!';
                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('btn-success');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('btn-success');
                        this.classList.add('btn-outline-secondary');
                    }, 2000);
                });
            });
        }
        
        // Validazione password per nuovo influencer
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                if (!generatePasswordCheckbox || !generatePasswordCheckbox.checked) {
                    if (this.value.length > 0 && this.value.length < 6) {
                        this.setCustomValidity('La password deve avere almeno 6 caratteri');
                    } else {
                        this.setCustomValidity('');
                    }
                }
            });
        }
        
        // Funzione per generare password casuale
        function generateRandomPassword(length = 12) {
            const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            let password = '';
            
            // Assicura almeno una lettera maiuscola, una minuscola e un numero
            password += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)];
            password += 'abcdefghijklmnopqrstuvwxyz'[Math.floor(Math.random() * 26)];
            password += '0123456789'[Math.floor(Math.random() * 10)];
            
            // Riempi il resto
            for (let i = 3; i < length; i++) {
                password += charset[Math.floor(Math.random() * charset.length)];
            }
            
            // Mescola i caratteri
            return password.split('').sort(() => Math.random() - 0.5).join('');
        }
        
        // Esponi la funzione globalmente se necessario
        window.generateRandomPassword = generateRandomPassword;
    });
    </script>
    
    <?php
}

require_once '../includes/admin_footer.php';
?>