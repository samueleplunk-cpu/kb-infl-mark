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
    if (isset($_POST['save_brand'])) {
        $data = [
            'name' => trim($_POST['name']),
            'email' => trim($_POST['email']),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Aggiungi password se fornita
        if (!empty(trim($_POST['password'] ?? ''))) {
            $data['password'] = trim($_POST['password']);
        }
        
        // Per azione "add" (nuovo brand), mantieni la validazione
        // Per azione "edit" (modifica), rimuovi la validazione obbligatoria
        if ($action === 'add') {
            if (empty($data['name']) || empty($data['email'])) {
                $message = '<div class="alert alert-danger">Nome ed email sono obbligatori</div>';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $message = '<div class="alert alert-danger">Email non valida</div>';
            } else {
                $success = saveBrand($data, $id);
                if ($success) {
                    $message = '<div class="alert alert-success">Brand salvato con successo!</div>';
                    if (!$id) {
                        $action = 'list';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Errore nel salvataggio</div>';
                }
            }
        } else {
            // Per edit, valida solo l'email se fornita
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $message = '<div class="alert alert-danger">Email non valida</div>';
            } else {
                $success = saveBrand($data, $id);
                if ($success) {
                    $message = '<div class="alert alert-success">Brand salvato con successo!</div>';
                    if (!$id) {
                        $action = 'list';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Errore nel salvataggio</div>';
                }
            }
        }
    }
    
    // Gestione azioni rapide
    if (isset($_POST['action_type']) && isset($_POST['brand_id'])) {
        $brand_id = $_POST['brand_id'];
        $action_type = $_POST['action_type'];
        
        switch($action_type) {
            case 'suspend':
                $suspension_end = $_POST['suspension_end'] ?? null;
                updateBrandStatus($brand_id, 'suspend', $suspension_end);
                $message = '<div class="alert alert-warning">Brand sospeso</div>';
                break;
                
            case 'unsuspend':
                updateBrandStatus($brand_id, 'unsuspend');
                $message = '<div class="alert alert-success">Sospensione rimossa</div>';
                break;
                
            case 'block':
                updateBrandStatus($brand_id, 'block');
                $message = '<div class="alert alert-danger">Brand bloccato</div>';
                break;
                
            case 'unblock':
                updateBrandStatus($brand_id, 'unblock');
                $message = '<div class="alert alert-success">Blocco rimosso</div>';
                break;
                
            case 'delete':
                updateBrandStatus($brand_id, 'delete');
                $message = '<div class="alert alert-info">Brand eliminato (soft delete)</div>';
                break;
                
            case 'delete_completely':
                $success = deleteBrandCompletely($brand_id);
                if ($success) {
                    $message = '<div class="alert alert-info">Brand eliminato completamente dal sistema</div>';
                } else {
                    $message = '<div class="alert alert-danger">Errore nell\'eliminazione del brand</div>';
                }
                break;
                
            case 'restore':
                updateBrandStatus($brand_id, 'restore');
                $message = '<div class="alert alert-success">Brand ripristinato</div>';
                break;
        }
    }
}

// Pagina lista brands
if ($action === 'list') {
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 15;
    
    $filters = [
        'status' => $_GET['status'] ?? '',
        'search' => $_GET['search'] ?? ''
    ];
    
    $result = getBrands($page, $per_page, $filters);
    $brands = $result['data'];
    $total_pages = $result['total_pages'];
    $total_count = $result['total'];
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">Gestione Brands</h1>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Aggiungi Brand
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Filtri -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Filtri</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3 align-items-end">
                            <input type="hidden" name="action" value="list">
                            
                            <div class="col-md-4">
                                <label for="search" class="form-label">Cerca</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Nome, email o azienda...">
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
                            
                            <div class="col-md-4 d-flex gap-2">
                                <button type="submit" class="btn btn-outline-primary flex-fill">Applica</button>
                                <a href="?action=list" class="btn btn-outline-secondary flex-fill">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabella Brands -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            Lista Brands 
                            <span class="badge bg-secondary"><?php echo $total_count; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($brands)): ?>
                            <div class="alert alert-info">Nessun brand trovato</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Azienda</th>
                                            <th>Email</th>
                                            <th>Stato</th>
                                            <th>Data Registrazione</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($brands as $brand): ?>
                                            <tr>
                                                <td><?php echo $brand['id']; ?></td>
                                                <td>
    <?php 
    $company_name = !empty($brand['company_display_name']) 
        ? $brand['company_display_name'] 
        : $brand['name'];
    echo htmlspecialchars_decode($company_name); 
    ?>
</td>
                                                <td><?php echo htmlspecialchars($brand['email']); ?></td>
                                                <td>
                                                    <?php 
                                                    $status_badge = '';
                                                    if ($brand['deleted_at']) {
                                                        $status_badge = '<span class="badge bg-dark">Eliminato</span>';
                                                    } elseif ($brand['is_blocked']) {
                                                        $status_badge = '<span class="badge bg-danger">Bloccato</span>';
                                                    } elseif ($brand['is_suspended']) {
                                                        $status_badge = '<span class="badge bg-warning">Sospeso</span>';
                                                    } elseif ($brand['is_active']) {
                                                        $status_badge = '<span class="badge bg-success">Attivo</span>';
                                                    } else {
                                                        $status_badge = '<span class="badge bg-secondary">Inattivo</span>';
                                                    }
                                                    echo $status_badge;
                                                    ?>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($brand['created_at'])); ?></td>
                                                <td>
                                                    <div class="d-flex gap-1 flex-wrap">
                                                        <a href="?action=edit&id=<?php echo $brand['id']; ?>" 
                                                           class="btn btn-outline-primary btn-sm" title="Modifica">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <?php if ($brand['deleted_at']): ?>
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                                                                <input type="hidden" name="action_type" value="restore">
                                                                <button type="submit" class="btn btn-outline-success btn-sm" title="Ripristina">
                                                                    <i class="fas fa-undo"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <?php if ($brand['is_suspended']): ?>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                                                                    <input type="hidden" name="action_type" value="unsuspend">
                                                                    <button type="submit" class="btn btn-outline-warning btn-sm" title="Rimuovi sospensione">
                                                                        <i class="fas fa-play"></i>
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <button type="button" class="btn btn-outline-warning btn-sm" 
                                                                        data-bs-toggle="modal" data-bs-target="#suspendModal<?php echo $brand['id']; ?>"
                                                                        title="Sospendi">
                                                                    <i class="fas fa-pause"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($brand['is_blocked']): ?>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                                                                    <input type="hidden" name="action_type" value="unblock">
                                                                    <button type="submit" class="btn btn-outline-info btn-sm" title="Sblocca">
                                                                        <i class="fas fa-unlock"></i>
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                                                                    <input type="hidden" name="action_type" value="block">
                                                                    <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                                            onclick="return confirm('Sei sicuro di voler bloccare questo brand?')"
                                                                            title="Blocca">
                                                                        <i class="fas fa-ban"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            
                                                            <!-- Pulsante Elimina completamente (APRE il modal) -->
                                                            <button type="button" class="btn btn-outline-dark btn-sm" 
                                                                    data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $brand['id']; ?>"
                                                                    title="Elimina completamente">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Modal Sospensione -->
                                                    <?php if (!$brand['deleted_at'] && !$brand['is_suspended']): ?>
                                                    <div class="modal fade" id="suspendModal<?php echo $brand['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="post">
                                                                    <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                                                                    <input type="hidden" name="action_type" value="suspend">
                                                                    
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Sospendi Brand</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label for="suspension_end<?php echo $brand['id']; ?>" class="form-label">
                                                                                Data fine sospensione
                                                                            </label>
                                                                            <input type="datetime-local" class="form-control" 
                                                                                   id="suspension_end<?php echo $brand['id']; ?>" 
                                                                                   name="suspension_end" required>
                                                                            <div class="form-text">
                                                                                Il brand non potrà accedere al sistema fino a questa data.
                                                                            </div>
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
                                                    <div class="modal fade" id="deleteModal<?php echo $brand['id']; ?>" tabindex="-1" 
                                                         aria-labelledby="deleteModalLabel<?php echo $brand['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-danger text-white">
                                                                    <h5 class="modal-title" id="deleteModalLabel<?php echo $brand['id']; ?>">
                                                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                                                        Conferma Eliminazione Permanente
                                                                    </h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="card mb-3">
                                                                        <div class="card-header bg-light">
                                                                            <strong>Dati Brand da Eliminare</strong>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="row mb-2">
                                                                                <div class="col-4"><strong>ID:</strong></div>
                                                                                <div class="col-8"><?php echo $brand['id']; ?></div>
                                                                            </div>
                                                                            <div class="row mb-2">
    <div class="col-4"><strong>Azienda:</strong></div>
    <div class="col-8">
        <?php 
        $company_name = !empty($brand['company_display_name']) 
            ? $brand['company_display_name'] 
            : (!empty($brand['company_name']) ? $brand['company_name'] : 'N/A');
        echo htmlspecialchars_decode($company_name);
        ?>
    </div>
</div>
                                                                            <div class="row">
                                                                                <div class="col-4"><strong>Email:</strong></div>
                                                                                <div class="col-8"><?php echo htmlspecialchars($brand['email']); ?></div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <div class="card border-danger mb-3">
                                                                        <div class="card-header bg-danger-subtle text-danger">
                                                                            <strong><i class="fas fa-times-circle me-1"></i> Verranno eliminati definitivamente:</strong>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <ul class="mb-0">
                                                                                <li>Tutti i dati del brand dal database</li>
                                                                                <li>L'email del brand (potrà registrarsi nuovamente)</li>
                                                                                <li>Le immagini/avatar del brand</li>
                                                                                <li>Le campagne pubblicate dal brand</li>
                                                                                <li>Tutti i dati correlati</li>
                                                                            </ul>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                                        <i class="fas fa-times me-1"></i> Annulla
                                                                    </button>
                                                                    <form method="post" class="d-inline">
                                                                        <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                                                                        <input type="hidden" name="action_type" value="delete_completely">
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
                                    (Totale: <?php echo $total_count; ?> brands)
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
}

// Pagina aggiungi/modifica brand
elseif ($action === 'add' || $action === 'edit') {
    $brand = null;
    $brand_details = null;
    $campaign_stats = [
        'total_campaigns' => 0,
        'active_campaigns' => 0
    ];
    $title = $action === 'add' ? 'Aggiungi Brand' : 'Modifica Brand';
    
    if ($action === 'edit' && $id) {
        $brand = getBrandById($id);
        if (!$brand) {
            header('Location: brands.php');
            exit;
        }
        
        // Recupera i dettagli aggiuntivi dalla tabella brands (dove c'è il logo e la nazionalità)
        try {
            global $pdo;
            $stmt = $pdo->prepare("SELECT logo, company_name as brands_company_name, nationality FROM brands WHERE user_id = ?");
            $stmt->execute([$id]);
            $brand_details = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // CORREZIONE: Prima otteniamo l'ID della tabella brands associato all'utente
            $stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
            $stmt->execute([$id]);
            $brand_record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($brand_record && isset($brand_record['id'])) {
                $brand_table_id = $brand_record['id'];
                
                // Conta campagne totali (incluse bozze, attive, pubbliche, etc.)
                $stmt = $pdo->prepare("SELECT COUNT(*) as total_campaigns FROM campaigns WHERE brand_id = ? AND deleted_at IS NULL");
                $stmt->execute([$brand_table_id]);
                $campaign_stats['total_campaigns'] = $stmt->fetchColumn();
                
                // Conta campagne attive (stato 'active' e non scadute)
                $stmt = $pdo->prepare("SELECT COUNT(*) as active_campaigns FROM campaigns WHERE brand_id = ? AND status = 'active' AND deleted_at IS NULL");
                $stmt->execute([$brand_table_id]);
                $campaign_stats['active_campaigns'] = $stmt->fetchColumn();
            } else {
                // Se non esiste record nella tabella brands, le campagne sono 0
                $campaign_stats['total_campaigns'] = 0;
                $campaign_stats['active_campaigns'] = 0;
            }
            
        } catch (PDOException $e) {
            error_log("Errore nel recupero del logo brand o statistiche campagne: " . $e->getMessage());
            $brand_details = null;
        }
        
        // Recupera il nome azienda corretto (company_display_name dalla funzione getBrandById)
        $company_display_name = $brand['company_display_name'] ?? '';
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
                            <?php if ($action === 'edit'): ?>
                            <!-- Layout a 2 colonne solo per EDIT -->
                            <div class="row">
                                <!-- Colonna Sinistra: Form -->
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-12">
                                            <!-- Campo Azienda senza frasi esplicative -->
                                            <div class="mb-3">
    <label for="company_name" class="form-label">Azienda</label>
    <input type="text" class="form-control" id="company_name" name="company_name" 
           value="<?php echo htmlspecialchars_decode($company_display_name); ?>">
</div>
                                            
                                            <div class="mb-3">
    <label for="contact_name" class="form-label">Nome Contatto</label>
    <input type="text" class="form-control" id="contact_name" name="name" 
           value="<?php echo htmlspecialchars_decode($brand['name'] ?? ''); ?>">
    <div class="form-text">
        Nome della persona di contatto dell'azienda.
    </div>
</div>
                                            
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($brand['email'] ?? ''); ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="password" class="form-label">Password</label>
                                                <input type="password" class="form-control" id="password" name="password" 
                                                       placeholder="Lasciare vuoto per non modificare">
                                                <div class="form-text">Inserisci una nuova password per cambiarla</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3 form-check form-switch">
                                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                               <?php echo ($brand['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Account attivo</label>
                                    </div>
                                    
                                    <!-- MODIFICA APPLICATA: Sezione Informazioni Account con layout verticale e statistiche campagne -->
                                    <div class="card mt-4 mb-4">
    <div class="card-header">
        <h6 class="mb-0">Informazioni Account</h6>
    </div>
    <div class="card-body">
        <!-- Layout verticale su singola riga per ogni voce -->
        <div class="mb-2">
            <small class="text-muted">Creato il:</small>
            <span class="ms-1"><?php echo date('d/m/Y - H:i', strtotime($brand['created_at'])); ?></span>
        </div>
        
        <!-- NUOVA VOCE: Nazione -->
        <div class="mb-2">
            <small class="text-muted">Nazione:</small>
            <span class="ms-1">
                <?php
                // Recupera la nazione del brand dalla tabella brands
                $brand_country = "Non specificata";
                if ($brand_details && isset($brand_details['nationality']) && !empty($brand_details['nationality'])) {
                    $brand_country = htmlspecialchars($brand_details['nationality']);
                }
                echo $brand_country;
                ?>
            </span>
        </div>
        
        <?php if ($brand['updated_at'] && $brand['updated_at'] != $brand['created_at']): ?>
        <div class="mb-2">
            <small class="text-muted">Modificato il:</small>
            <span class="ms-1"><?php echo date('d/m/Y - H:i', strtotime($brand['updated_at'])); ?></span>
        </div>
        <?php endif; ?>
        
        <!-- Nuove voci statistiche campagne -->
        <div class="mb-2">
            <small class="text-muted">Campagne totali:</small>
            <span class="ms-1"><?php echo $campaign_stats['total_campaigns']; ?></span>
        </div>
        
        <div class="mb-2">
            <small class="text-muted">Campagne attive:</small>
            <span class="ms-1"><?php echo $campaign_stats['active_campaigns']; ?></span>
        </div>
        
        <!-- NUOVA VOCE: Campagne segnalate -->
        <?php
        // Conta le segnalazioni delle campagne del brand
        $reported_campaigns_count = 0;
        if ($brand_record && isset($brand_record['id'])) {
            try {
                // Query per contare le segnalazioni delle campagne del brand
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as reported_count 
                    FROM campaign_reports cr 
                    INNER JOIN campaigns c ON cr.campaign_id = c.id 
                    WHERE c.brand_id = ?
                ");
                $stmt->execute([$brand_table_id]);
                $reported_campaigns_count = $stmt->fetchColumn();
            } catch (PDOException $e) {
                error_log("Errore nel conteggio campagne segnalate per brand ID {$brand_table_id}: " . $e->getMessage());
                $reported_campaigns_count = 0;
            }
        }
        ?>
        <div class="mb-0">
            <small class="text-muted">Campagne segnalate:</small>
            <span class="ms-1"><?php echo $reported_campaigns_count; ?></span>
        </div>
    </div>
</div>
                                </div>
                                
                                <!-- Colonna Destra: Immagine del brand -->
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">Logo del Brand</h5>
                                        </div>
                                        <div class="card-body text-center">
                                            <?php
                                            // Determina l'immagine da mostrare
                                            $logo_path = null;
                                            $image_to_show = null;
                                            $placeholder_path = '/uploads/placeholder/brand_admin_edit.png';
                                            
                                            if ($brand_details && !empty($brand_details['logo'])) {
                                                // Gestione corretta dei percorsi
                                                $logo_from_db = $brand_details['logo'];
                                                
                                                // Rimuove eventuali slash iniziali
                                                $logo_from_db = ltrim($logo_from_db, '/');
                                                
                                                // Prova diversi percorsi possibili
                                                $possible_paths = [
                                                    '/' . $logo_from_db,     // percorso relativo alla root
                                                    '/uploads/brands/' . basename($logo_from_db), // solo nome file in directory brands
                                                ];
                                                
                                                // Aggiungi anche il percorso originale dal DB
                                                $possible_paths[] = '/' . $logo_from_db;
                                                
                                                // Cerca il file in tutti i percorsi possibili
                                                foreach ($possible_paths as $possible_path) {
                                                    $full_path = $_SERVER['DOCUMENT_ROOT'] . $possible_path;
                                                    if (file_exists($full_path)) {
                                                        $logo_path = $possible_path;
                                                        $image_to_show = $possible_path;
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            // Se non abbiamo trovato un'immagine valida, usa il placeholder
                                            if (!$image_to_show) {
                                                $image_to_show = $placeholder_path;
                                            }
                                            ?>
                                            
                                            <div class="mb-3">
                                                <img src="<?php echo $image_to_show; ?>" 
                                                     alt="Logo Brand" 
                                                     class="img-fluid rounded" 
                                                     style="max-height: 200px; max-width: 100%;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php else: ?>
                            <!-- Layout a singola colonna per ADD (NON MODIFICATO) -->
                            <div class="row">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nome Contatto <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($brand['name'] ?? ''); ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($brand['email'] ?? ''); ?>" 
                                               required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="company_name" class="form-label">Nome Azienda</label>
                                        <input type="text" class="form-control" id="company_name" name="company_name" 
                                               value="<?php echo htmlspecialchars($brand['company_name'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>                            
                            <div class="mb-3 form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       <?php echo ($brand['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Account attivo</label>
                                <div class="form-text">Se disattivato, il brand non potrà accedere al sistema</div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                Alla creazione verrà generata una password temporanea che il brand potrà cambiare al primo accesso.
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="save_brand" class="btn btn-primary">
                                    Salva
                                </button>
                                <a href="?action=list" class="btn btn-secondary">Annulla</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php
}

require_once '../includes/admin_footer.php';
?>