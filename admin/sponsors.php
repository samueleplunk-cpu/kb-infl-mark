<?php
ob_start();

// Includi file necessari
require_once '../includes/config.php';
require_once '../includes/admin_functions.php';
require_once '../includes/admin_header.php';
require_once '../includes/general_settings_functions.php';

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

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '<div class="alert alert-danger">Token di sicurezza non valido</div>';
    } else {
        if (isset($_POST['save_sponsor'])) {
            // Gestione salvataggio sponsor
            // Gestione target audience (campi separati o combinati)
            $target_audience_value = '';
            
            // Controlla se ci sono campi separati
            if (isset($_POST['target_age_range']) || isset($_POST['target_gender'])) {
                $age_range = isset($_POST['target_age_range']) ? trim($_POST['target_age_range']) : '';
                $gender = isset($_POST['target_gender']) ? trim($_POST['target_gender']) : '';
                
                // Crea il formato JSON come nell'influencer edit-sponsor.php
                $target_data = [
                    'age_range' => $age_range,
                    'gender' => $gender
                ];
                $target_audience_value = json_encode($target_data);
            } 
            // Fallback al campo combinato se esiste (per compatibilità)
            elseif (isset($_POST['target_audience'])) {
                $target_audience_value = trim($_POST['target_audience']);
            }
            
            $data = [
                'influencer_id' => isset($_POST['influencer_id']) ? intval($_POST['influencer_id']) : null,
                'title' => isset($_POST['title']) ? trim($_POST['title']) : '',
                'description' => isset($_POST['description']) ? trim($_POST['description']) : '',
                'budget' => isset($_POST['budget']) ? floatval($_POST['budget']) : 0,
                'category' => isset($_POST['category']) ? trim($_POST['category']) : '',
                'platforms' => isset($_POST['platforms']) ? json_encode($_POST['platforms']) : '[]',
                'target_audience' => $target_audience_value,
                'status' => isset($_POST['status']) ? trim($_POST['status']) : 'active'
            ];
            
            if (empty($data['title']) || empty($data['influencer_id'])) {
                $message = '<div class="alert alert-danger">Titolo e influencer sono obbligatori</div>';
            } else {
                $success = saveSponsor($data, $id);
                if ($success) {
                    $message = '<div class="alert alert-success">Sponsor salvato con successo!</div>';
                    if (!$id) {
                        $action = 'list';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Errore nel salvataggio</div>';
                }
            }
        }
        
        // Gestione eliminazione
        if (isset($_POST['delete_sponsor'])) {
            $sponsor_id = intval($_POST['sponsor_id']);
            $success = hardDeleteSponsor($sponsor_id);
            if ($success) {
                $message = '<div class="alert alert-success">Sponsor eliminato definitivamente!</div>';
            } else {
                $message = '<div class="alert alert-danger">Errore nell\'eliminazione dello sponsor</div>';
            }
        }
        
        // Gestione richieste informazioni
        if (isset($_POST['send_info_request'])) {
            $sponsor_id = intval($_POST['sponsor_id']);
            $message_text = trim($_POST['message']);
            
            if (empty($message_text)) {
                $message = '<div class="alert alert-danger">Il messaggio è obbligatorio</div>';
            } else {
                // Recupera l'ID dell'influencer dallo sponsor
                $influencer_id = getInfluencerIdFromSponsor($sponsor_id);
                
                if ($influencer_id) {
                    $success = createSponsorInfoRequest(
                        $sponsor_id,
                        $_SESSION['admin_id'],
                        $influencer_id,
                        $message_text
                    );
                    
                    if ($success) {
                        $message = '<div class="alert alert-success">Richiesta inviata con successo!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Errore nell\'invio della richiesta</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Impossibile trovare l\'influencer per questo sponsor</div>';
                }
            }
        }
    }
}

// Funzione helper per normalizzare le categorie nel filtro
function normalizeCategoryForFilter($category_name) {
    // Mappatura specifica per i casi problematici
    $category_mapping = [
        'Finance & Business' => 'finance-business',
        'Fitness & Wellness' => 'fitness-wellness',
        'Beauty & Makeup' => 'beauty-makeup',
        'Food & Beverage' => 'food-beverage',
        'Travel & Tourism' => 'travel-tourism',
        'Gaming & Esports' => 'gaming-esports',
        'Tech & Gadgets' => 'tech-gadgets',
        'Education & Learning' => 'education-learning',
        'Health & Medical' => 'health-medical',
        'Home & Decor' => 'home-decor',
        'Sports & Outdoors' => 'sports-outdoors',
        'Music & Entertainment' => 'music-entertainment',
        // Aggiungi qui altre mappature se necessario
    ];
    
    // Se abbiamo una mappatura esplicita, usala
    if (isset($category_mapping[$category_name])) {
        return $category_mapping[$category_name];
    }
    
    // Altrimenti, crea uno slug dal nome
    $slug = strtolower($category_name);
    $slug = preg_replace('/[&\s]+/', '-', $slug);
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
    $slug = trim($slug, '-');
    
    return $slug;
}

// Gestione delle diverse azioni
if ($action === 'list') {
    // Pagina lista sponsor
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 25;
    
    $filters = [
        'status' => isset($_GET['status']) ? $_GET['status'] : '',
        'search' => isset($_GET['search']) ? $_GET['search'] : '',
        'influencer_search' => isset($_GET['influencer_search']) ? $_GET['influencer_search'] : '',
        'category' => isset($_GET['category']) ? $_GET['category'] : ''
    ];
    
    // Ottieni dati
    $result = getSponsors($page, $per_page, $filters);
    $sponsors = $result['data'];
    $total_pages = $result['total_pages'];
    $total_count = $result['total'];
    $influencers_list = getAllInfluencers();
    
    // MODIFICA: Recupera le categorie dalla stessa fonte usata in general-settings.php
    $categories_list = get_active_categories_for_brands();
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <!-- MODIFICA 1: Rimosso pulsante "Nuovo Sponsor" -->
                <div class="mb-4">
                    <h1 class="h3">
                        <i class="fas fa-handshake me-2"></i>Gestione Sponsor Influencer
                    </h1>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Statistiche Rapide -->
                <div class="row mb-4">
                    <div class="col-xl">
                        <div class="card bg-success text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fs-4 fw-bold"><?php echo getSponsorsCount('active'); ?></div>
                                        <div>Sponsor Attivi</div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-play-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl">
                        <div class="card bg-warning text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fs-4 fw-bold"><?php echo getSponsorsCount('pending'); ?></div>
                                        <div>In revisione</div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl">
                        <div class="card bg-danger text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fs-4 fw-bold"><?php echo getSponsorsCount('rejected'); ?></div>
                                        <div>Rifiutati</div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-times-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl">
                        <div class="card bg-primary text-white mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fs-4 fw-bold"><?php echo getSponsorsCount(); ?></div>
                                        <div>Totale Sponsor</div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-handshake fa-2x"></i>
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
                            
                            <div class="col-md-2">
                                <label for="search" class="form-label">Titolo sponsor</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Cerca titolo...">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="status" class="form-label">Stato</label>
                                <!-- MODIFICA 2: Rimosse opzioni "Completati" e "Bozza" -->
                                <select class="form-select" id="status" name="status">
                                    <option value="">Tutti</option>
                                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Attivi</option>
                                    <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>In revisione</option>
                                    <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rifiutati</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="influencer_search" class="form-label">Nome influencer</label>
                                <input type="text" class="form-control" id="influencer_search" name="influencer_search" 
                                       value="<?php echo htmlspecialchars($filters['influencer_search'] ?? ''); ?>" 
                                       placeholder="Cerca nome...">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="category" class="form-label">Categoria</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">Tutte</option>
                                    <?php foreach ($categories_list as $category): ?>
                                        <?php 
                                        // Genera lo slug per questa categoria
                                        $category_slug = $category['slug'] ?? normalizeCategoryForFilter($category['name']);
                                        ?>
                                        <option value="<?php echo htmlspecialchars($category_slug); ?>" 
                                                <?php echo $filters['category'] === $category_slug ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- MODIFICA 3: Pulsante "Cerca" senza icona -->
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    Cerca
                                </button>
                            </div>
                            
                            <!-- MODIFICA 3: Pulsante "Reset" senza icona -->
                            <div class="col-md-2 d-flex align-items-end">
                                <a href="?action=list" class="btn btn-outline-secondary w-100">
                                    Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabella Sponsor -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Lista Sponsor 
                            <span class="badge bg-secondary"><?php echo $total_count; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sponsors)): ?>
                            <div class="alert alert-info text-center py-4">
                                <i class="fas fa-handshake fa-3x text-muted mb-3"></i>
                                <h5>Nessuno sponsor trovato</h5>
                                <p class="text-muted">Utilizza i filtri per trovare gli sponsor.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Copertina</th>
                                            <th>Titolo Sponsor</th>
                                            <th>Budget</th>
                                            <th>Categoria</th>
                                            <th>Stato</th>
                                            <th>Influencer</th> <!-- Spostata qui dopo Stato -->
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sponsors as $sponsor): ?>
                                            <tr>
                                                <!-- MODIFICA: Colonna Copertina - Mostra solo immagine se esiste -->
                                                <td>
                                                    <?php if ($sponsor['image_url']): ?>
                                                        <?php
                                                        // Correzione del percorso immagine
                                                        $image_path = '/uploads/sponsor/' . basename($sponsor['image_url']);
                                                        ?>
                                                        <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                                             alt="<?php echo htmlspecialchars($sponsor['title']); ?>" 
                                                             class="rounded" style="width: 40px; height: 40px; object-fit: cover;"
                                                             onerror="this.onerror=null; this.style.display='none';">
                                                    <?php else: ?>
                                                        <div class="rounded bg-light d-flex align-items-center justify-content-center" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="fas fa-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($sponsor['title']); ?></strong>
                                                        <?php if ($sponsor['platforms']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <?php
                                                                $platforms = json_decode($sponsor['platforms'], true) ?: [];
                                                                // MODIFICA: Recupera le icone corrette dalle impostazioni
                                                                require_once '../includes/general_settings_functions.php';
                                                                $social_networks_settings = get_social_networks_settings();
                                                                $social_networks = $social_networks_settings['social_networks'] ?? [];
                                                                
                                                                $platform_icons = [];
                                                                foreach ($social_networks as $social) {
                                                                    if (!empty($social['is_active']) || $social['active'] ?? true) {
                                                                        $platform_icons[$social['slug']] = $social['icon'];
                                                                    }
                                                                }
                                                                foreach ($platforms as $platform): 
                                                                    $icon_class = $platform_icons[$platform] ?? 'fa-globe';
                                                                ?>
                                                                    <i class="<?php echo $icon_class; ?> me-1"></i>
                                                                <?php endforeach; ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                
                                                <td>
                                                    <strong><?php echo number_format($sponsor['budget'], 2); ?> €</strong>
                                                </td>
                                                <td>
                                                    <?php 
                                                    // Mappa lo slug della categoria al nome completo
                                                    $category_slug = $sponsor['category'];
                                                    $category_name = $category_slug; // Default: mostra lo slug
                                                    
                                                    // Cerca il nome della categoria nella lista
                                                    foreach ($categories_list as $category) {
                                                        if (($category['slug'] ?? normalizeCategoryForFilter($category['name'])) === $category_slug) {
                                                            $category_name = $category['name'];
                                                            break;
                                                        }
                                                    }
                                                    
                                                    echo htmlspecialchars($category_name);
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_badge = '';
                                                    switch ($sponsor['status']) {
                                                        case 'active':
                                                            $status_badge = '<span class="badge bg-success"><i class="fas fa-play me-1"></i> Attivo</span>';
                                                            break;
                                                        case 'pending':
                                                            $status_badge = '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i> In revisione</span>';
                                                            break;
                                                        case 'completed':
                                                            $status_badge = '<span class="badge bg-info"><i class="fas fa-check me-1"></i> Completato</span>';
                                                            break;
                                                        case 'rejected':
                                                            $status_badge = '<span class="badge bg-danger"><i class="fas fa-times me-1"></i> Rifiutato</span>';
                                                            break;
                                                        case 'draft':
                                                            $status_badge = '<span class="badge bg-secondary"><i class="fas fa-edit me-1"></i> Bozza</span>';
                                                            break;
                                                        default:
                                                            $status_badge = '<span class="badge bg-light text-dark">' . htmlspecialchars($sponsor['status']) . '</span>';
                                                    }
                                                    echo $status_badge;
                                                    ?>
                                                </td>
                                                
                                                <!-- MODIFICA: Colonna Influencer spostata qui dopo Stato -->
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($sponsor['influencer_email']); ?></strong>
                                                        <?php if (!empty($sponsor['influencer_name'])): ?>
                                                            <br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($sponsor['influencer_name']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                
                                                <td>
    <div class="d-flex gap-2">
        <!-- Modifica -->
        <a href="?action=edit&id=<?php echo $sponsor['id']; ?>" 
           class="btn btn-outline-primary btn-sm" title="Modifica">
            <i class="fas fa-edit"></i>
        </a>
        
        <!-- Elimina -->
        <button type="button" class="btn btn-outline-danger btn-sm" 
                data-bs-toggle="modal" 
                data-bs-target="#deleteModal<?php echo $sponsor['id']; ?>"
                title="Elimina">
            <i class="fas fa-trash"></i>
        </button>
        
        <!-- Richiesta informazioni (icona only) -->
        <button type="button" class="btn btn-outline-info btn-sm" 
                data-bs-toggle="modal" 
                data-bs-target="#infoRequestModal<?php echo $sponsor['id']; ?>"
                title="Richiesta informazioni">
            <i class="fas fa-info-circle"></i>
        </button>
    </div>
</td>
                                            </tr>

                                            <!-- Modal Richiesta Informazioni -->
                                            <div class="modal fade" id="infoRequestModal<?php echo $sponsor['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="post">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                            <input type="hidden" name="sponsor_id" value="<?php echo $sponsor['id']; ?>">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title text-info">
                                                                    <i class="fas fa-exclamation-circle me-2"></i>Richiedi informazioni
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Stai richiedendo informazioni per lo sponsor:</p>
                                                                <p class="fw-bold">"<?php echo htmlspecialchars($sponsor['title']); ?>"</p>
                                                                
                                                                <div class="mb-3">
                                                                    <label for="message_<?php echo $sponsor['id']; ?>" class="form-label">
                                                                        Messaggio per l'influencer <span class="text-danger">*</span>
                                                                    </label>
                                                                    <textarea class="form-control" 
                                                                              id="message_<?php echo $sponsor['id']; ?>" 
                                                                              name="message" 
                                                                              rows="5" 
                                                                              placeholder="Scrivi qui le informazioni che desideri richiedere all'influencer..."
                                                                              required></textarea>
                                                                    <div class="form-text">
                                                                        Il messaggio verrà inviato all'influencer proprietario dello sponsor.
                                                                    </div>
                                                                </div>
                                                                
                                                                <!-- Visualizza richieste esistenti -->
                                                                <?php
                                                                $existing_requests = getSponsorInfoRequests($sponsor['id']);
                                                                if (!empty($existing_requests)): 
                                                                ?>
                                                                    <div class="mt-3 pt-3 border-top">
                                                                        <h6 class="text-muted mb-2">
                                                                            <i class="fas fa-history me-1"></i>Richieste precedenti
                                                                        </h6>
                                                                        <?php foreach ($existing_requests as $req): ?>
                                                                            <div class="card mb-2">
                                                                                <div class="card-body p-2">
                                                                                    <small>
                                                                                        <div class="d-flex justify-content-between">
                                                                                            <span class="badge bg-<?php echo $req['status'] === 'pending' ? 'warning' : ($req['status'] === 'replied' ? 'success' : 'secondary'); ?>">
                                                                                                <?php echo $req['status'] === 'pending' ? 'In attesa' : ($req['status'] === 'replied' ? 'Risposta' : 'Chiusa'); ?>
                                                                                            </span>
                                                                                            <span class="text-muted">
                                                                                                <?php echo date('d/m/Y - H:i', strtotime($req['created_at'])); ?>
                                                                                            </span>
                                                                                        </div>
                                                                                        <div class="mt-1">
                                                                                            <strong>Messaggio:</strong> 
                                                                                            <span class="text-muted"><?php echo htmlspecialchars(substr($req['message'], 0, 100)) . (strlen($req['message']) > 100 ? '...' : ''); ?></span>
                                                                                        </div>
                                                                                        <?php if ($req['response']): ?>
                                                                                            <div class="mt-1">
                                                                                                <strong>Risposta:</strong> 
                                                                                                <span class="text-muted"><?php echo htmlspecialchars(substr($req['response'], 0, 100)) . (strlen($req['response']) > 100 ? '...' : ''); ?></span>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </small>
                                                                                </div>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                                <button type="submit" name="send_info_request" class="btn btn-info">
                                                                   Invia richiesta
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Modal Eliminazione Sponsor -->
                                            <div class="modal fade" id="deleteModal<?php echo $sponsor['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="post">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                            <input type="hidden" name="sponsor_id" value="<?php echo $sponsor['id']; ?>">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title text-danger">
                                                                    <i class="fas fa-exclamation-triangle me-2"></i>Conferma Eliminazione
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Sei sicuro di voler eliminare definitivamente lo sponsor <strong>"<?php echo htmlspecialchars($sponsor['title']); ?>"</strong>?</p>
                                                                <p class="text-danger">
                                                                    <i class="fas fa-exclamation-circle me-1"></i>
                                                                    Questa azione non può essere annullata. Lo sponsor e tutte le immagini correlate verranno rimossi permanentemente dal sistema.
                                                                </p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                                <button type="submit" name="delete_sponsor" class="btn btn-danger">
                                                                    <i class="fas fa-trash me-1"></i> Elimina
                                                                </button>
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
                            <nav aria-label="Paginazione sponsor">
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
                                    (Totale: <?php echo $total_count; ?> sponsor)
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
    // Pagina aggiungi/modifica sponsor
    $sponsor = null;
    if ($action === 'edit' && $id) {
        $sponsor = getSponsorById($id);
        if (!$sponsor) {
            header('Location: sponsors.php');
            exit;
        }
    }
    
    // --- INIZIO: Parsing target audience per edit/add ---
    if ($action === 'edit' && $sponsor) {
        // Inizializza i campi separati
        $target_age_range = '';
        $target_gender = '';
        
        // Parsing dei dati esistenti
        if (!empty($sponsor['target_audience'])) {
            // Prova prima a decodificare come JSON (nuovo formato)
            $target_data = json_decode($sponsor['target_audience'], true);
            
            if (is_array($target_data) && json_last_error() === JSON_ERROR_NONE) {
                // Nuovo formato JSON
                $target_age_range = $target_data['age_range'] ?? '';
                $target_gender = $target_data['gender'] ?? '';
            } else {
                // Vecchio formato stringa combinata - provare a parsare
                $target_string = $sponsor['target_audience'];
                
                // Cerca pattern di età (es: "18-35", "18-35 anni", "18-25 anni")
                if (preg_match('/(\d{1,3}\s*-\s*\d{1,3})/', $target_string, $matches)) {
                    $target_age_range = trim($matches[1]);
                }
                
                // Cerca pattern per genere
                if (preg_match('/(maschio|maschile|male)/i', $target_string, $matches)) {
                    $target_gender = 'male';
                } elseif (preg_match('/(femmina|femminile|female)/i', $target_string, $matches)) {
                    $target_gender = 'female';
                } elseif (preg_match('/(misto|entrambi|both)/i', $target_string, $matches)) {
                    $target_gender = 'both';
                }
            }
        }
    } else {
        // Per l'aggiunta di nuovi sponsor
        $target_age_range = '';
        $target_gender = '';
    }
    // --- FINE: Parsing target audience ---
    
    $influencers_list = getAllInfluencers();
    
    // MODIFICA: Recupera le piattaforme social dalle impostazioni generali
    require_once '../includes/general_settings_functions.php';
    $social_networks_settings = get_social_networks_settings();
    $platforms_list = $social_networks_settings['social_networks'] ?? [];
    
    // MODIFICA: Recupera le categorie dalla stessa fonte usata in general-settings.php
    $categories_list = get_active_categories_for_brands();
    ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3">
                        <i class="fas fa-handshake me-2"></i><?php echo $action === 'add' ? 'Nuovo Sponsor' : 'Modifica Sponsor'; ?>
                    </h1>
                    <a href="?action=list" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Torna alla lista
                    </a>
                </div>
                
                <?php echo $message; ?>
                
                <div class="card">
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Titolo Sponsor <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($sponsor['title'] ?? ''); ?>" 
                                               required>
                                        <div class="form-text">Inserisci un titolo descrittivo per lo sponsor</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="influencer_id" class="form-label">Influencer <span class="text-danger">*</span></label>
                                        <?php if ($action === 'edit' && $sponsor): ?>
                                            <!-- MODIFICA: Mostra il nome dell'influencer in sola lettura per la modifica -->
                                            <input type="hidden" id="influencer_id" name="influencer_id" value="<?php echo $sponsor['influencer_id']; ?>">
                                            <input type="text" class="form-control" 
                                                   value="<?php echo htmlspecialchars($sponsor['influencer_name'] ?? $sponsor['influencer_email'] ?? 'Influencer non trovato'); ?>" 
                                                   readonly
                                                   style="background-color: #f8f9fa;">
                                            <div class="form-text">L'influencer proprietario dello sponsor non può essere modificato</div>
                                        <?php else: ?>
                                            <!-- Mantieni il dropdown per l'aggiunta di nuovi sponsor -->
                                            <select class="form-select" id="influencer_id" name="influencer_id" required>
                                                <option value="">Seleziona un influencer</option>
                                                <?php foreach ($influencers_list as $influencer): ?>
                                                    <option value="<?php echo $influencer['id']; ?>" 
                                                            <?php echo ($sponsor['influencer_id'] ?? '') == $influencer['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($influencer['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descrizione</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="4"><?php echo htmlspecialchars($sponsor['description'] ?? ''); ?></textarea>
                                <div class="form-text">Descrivi i dettagli dello sponsor e l'offerta</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="budget" class="form-label">Budget (€)</label>
                                        <input type="number" class="form-control" id="budget" name="budget" 
                                               step="0.01" min="0"
                                               value="<?php echo htmlspecialchars($sponsor['budget'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Categoria</label>
                                        <select class="form-select" id="category" name="category">
                                            <option value="">Seleziona categoria</option>
                                            <?php foreach ($categories_list as $category): ?>
                                                <?php 
                                                // Genera lo slug per questa categoria
                                                $category_slug = $category['slug'] ?? normalizeCategoryForFilter($category['name']);
                                                ?>
                                                <option value="<?php echo htmlspecialchars($category_slug); ?>" 
                                                        <?php echo ($sponsor['category'] ?? '') === $category_slug ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                 <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Stato</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" <?php echo ($sponsor['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Attivo</option>
                                            <option value="pending" <?php echo ($sponsor['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>In revisione</option>
                                            <option value="rejected" <?php echo ($sponsor['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rifiutato</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Immagine</label>
                                        <input type="file" class="form-control" id="image" name="image" 
                                               accept="image/*">
                                        <?php if (isset($sponsor['image_url']) && $sponsor['image_url']): ?>
                                            <div class="mt-2">
                                                <?php
                                                // Correzione del percorso immagine
                                                $image_path = '/uploads/sponsor/' . basename($sponsor['image_url']);
                                                ?>
                                                <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                                     alt="Immagine sponsor" 
                                                     style="max-width: 100px; max-height: 100px; object-fit: cover;" 
                                                     class="rounded border"
                                                     onerror="this.style.display='none';">
                                                <small class="d-block text-muted">Immagine attuale</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Piattaforme Social</label>
                                <div class="row">
                                    <?php 
                                    $selected_platforms = [];
                                    if (isset($sponsor['platforms']) && $sponsor['platforms']) {
                                        $selected_platforms = json_decode($sponsor['platforms'], true) ?: [];
                                    }
                                    ?>
                                    <?php foreach ($platforms_list as $platform): ?>
                                        <?php if (!empty($platform['is_active']) || $platform['active'] ?? true): ?>
                                            <div class="col-md-2 col-sm-4 col-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="platforms[]" 
                                                           value="<?php echo htmlspecialchars($platform['slug']); ?>" 
                                                           id="platform_<?php echo htmlspecialchars($platform['slug']); ?>"
                                                           <?php echo in_array($platform['slug'], $selected_platforms) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="platform_<?php echo htmlspecialchars($platform['slug']); ?>">
                                                        <i class="<?php echo htmlspecialchars($platform['icon']); ?> me-1"></i>
                                                        <?php echo htmlspecialchars($platform['name']); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- MODIFICA: Target Audience Separato in due campi -->
                            <div class="mb-3">
                                <h5>Target Audience</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="target_age_range" class="form-label">Età del pubblico</label>
                                            <input type="text" class="form-control" id="target_age_range" name="target_age_range" 
                                                   value="<?php echo htmlspecialchars($target_age_range ?? ''); ?>" 
                                                   placeholder="es. 18-35">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="target_gender" class="form-label">Genere del pubblico</label>
                                            <select class="form-select" id="target_gender" name="target_gender">
                                                <option value="">Seleziona</option>
                                                <option value="male" <?php echo ($target_gender ?? '') === 'male' ? 'selected' : ''; ?>>Maschile</option>
                                                <option value="female" <?php echo ($target_gender ?? '') === 'female' ? 'selected' : ''; ?>>Femminile</option>
                                                <option value="both" <?php echo ($target_gender ?? '') === 'both' ? 'selected' : ''; ?>>Entrambi</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Campo nascosto per mantenere compatibilità con la funzione saveSponsor -->
                            <input type="hidden" id="target_audience" name="target_audience" value="">
                            
                            <div class="d-flex gap-2">
                                <button type="submit" name="save_sponsor" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salva Sponsor
                                </button>
                                <a href="?action=list" class="btn btn-secondary">Annulla</a>
                                
                                <?php if ($action === 'edit' && $sponsor): ?>
                                <div class="ms-auto">
                                    <small class="text-muted">
                                        Creata il: <?php echo date('d/m/Y H:i', strtotime($sponsor['created_at'])); ?>
                                        <?php if ($sponsor['updated_at'] && $sponsor['updated_at'] != $sponsor['created_at']): ?>
                                            <br>Modificata il: <?php echo date('d/m/Y H:i', strtotime($sponsor['updated_at'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const ageRangeInput = document.getElementById('target_age_range');
        const genderSelect = document.getElementById('target_gender');
        const hiddenTargetAudience = document.getElementById('target_audience');
        
        // Funzione per aggiornare il campo nascosto con il formato JSON
        function updateHiddenTargetAudience() {
            const age = ageRangeInput.value.trim();
            const gender = genderSelect.value;
            
            // Crea il formato JSON come nell'influencer edit-sponsor.php
            const targetData = {
                age_range: age,
                gender: gender
            };
            
            // Salva come JSON string
            hiddenTargetAudience.value = JSON.stringify(targetData);
        }
        
        // Aggiorna al caricamento iniziale
        updateHiddenTargetAudience();
        
        // Aggiorna quando i campi cambiano
        ageRangeInput.addEventListener('input', updateHiddenTargetAudience);
        genderSelect.addEventListener('change', updateHiddenTargetAudience);
        
        // Aggiorna anche prima dell'invio del form per sicurezza
        form.addEventListener('submit', function(e) {
            updateHiddenTargetAudience();
            
            // Per debug (puoi rimuovere in produzione)
            console.log('Target Audience JSON:', hiddenTargetAudience.value);
        });
    });
    </script>
    
    <?php
    
} else {
    // Azione non riconosciuta, redirect alla lista
    header('Location: sponsors.php');
    exit;
}

ob_end_flush();

require_once '../includes/admin_footer.php';
?>