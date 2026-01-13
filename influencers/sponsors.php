<?php
// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG CON PERCORSO ASSOLUTO
// =============================================
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

// =============================================
// VERIFICA AUTENTICAZIONE UTENTE
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
$error = '';
$success = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM influencers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        header("Location: create-profile.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo influencer: " . $e->getMessage();
}

// =============================================
// FUNZIONI PER RICHIESTE INFORMAZIONI SPONSOR
// =============================================

/**
 * Ottiene le richieste di informazioni per un influencer
 */
function getInfluencerInfoRequests($influencer_id, $sponsor_id = null) {
    global $pdo;
    
    try {
        $sql = "SELECT sir.*, a.username as admin_name, s.title as sponsor_title,
                       s.budget, s.category, s.status as sponsor_status
                FROM sponsor_info_requests sir
                LEFT JOIN admins a ON sir.admin_id = a.id
                JOIN sponsors s ON sir.sponsor_id = s.id
                WHERE sir.influencer_id = ? 
                AND sir.status = 'pending'";
        
        $params = [$influencer_id];
        
        if ($sponsor_id) {
            $sql .= " AND sir.sponsor_id = ?";
            $params[] = $sponsor_id;
        }
        
        $sql .= " ORDER BY sir.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Errore recupero richieste informazioni influencer: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica se uno sponsor ha richieste pendenti
 */
function sponsorHasPendingRequests($sponsor_id) {
    global $pdo;
    
    try {
        $sql = "SELECT COUNT(*) as count 
                FROM sponsor_info_requests 
                WHERE sponsor_id = ? AND status = 'pending'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sponsor_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['count'] > 0 : false;
        
    } catch (PDOException $e) {
        error_log("Errore verifica richieste pendenti: " . $e->getMessage());
        return false;
    }
}

// =============================================
// ELIMINAZIONE SPONSOR
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sponsor'])) {
    $sponsor_id = $_POST['sponsor_id'] ?? 0;
    
    try {
        // Verifica che lo sponsor appartenga all'influencer corrente
        $stmt = $pdo->prepare("SELECT id FROM sponsors WHERE id = ? AND influencer_id = ?");
        $stmt->execute([$sponsor_id, $influencer['id']]);
        $sponsor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sponsor) {
            // Hard delete - elimina permanentemente lo sponsor
            $stmt = $pdo->prepare("DELETE FROM sponsors WHERE id = ? AND influencer_id = ?");
            $stmt->execute([$sponsor_id, $influencer['id']]);
            
            $success = "Sponsor eliminato con successo!";
            
            // Reindirizza per aggiornare la lista
            header("Location: sponsors.php?success=sponsor_deleted");
            exit();
        } else {
            $error = "Sponsor non trovato o non autorizzato";
        }
    } catch (PDOException $e) {
        $error = "Errore nell'eliminazione dello sponsor: " . $e->getMessage();
    }
}

// =============================================
// GESTIONE RISPOSTA RICHIESTA INFORMAZIONI
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_info_request'])) {
    $request_id = $_POST['request_id'] ?? 0;
    $response = $_POST['response'] ?? '';
    
    if (empty($response)) {
        $error = "La risposta è obbligatoria";
    } else {
        try {
            // Verifica che la richiesta appartenga all'influencer corrente
            $sql = "SELECT sir.* FROM sponsor_info_requests sir
                    JOIN sponsors s ON sir.sponsor_id = s.id
                    WHERE sir.id = ? AND s.influencer_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$request_id, $influencer['id']]);
            $info_request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($info_request) {
                $update_sql = "UPDATE sponsor_info_requests 
                              SET response = ?, status = 'replied', 
                                  replied_at = NOW(), updated_at = NOW() 
                              WHERE id = ?";
                
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$response, $request_id]);
                
                $success = "Risposta inviata con successo!";
                
                // Reindirizza per aggiornare la lista
                header("Location: sponsors.php?success=info_request_replied");
                exit();
            } else {
                $error = "Richiesta non trovata o non autorizzata";
            }
        } catch (PDOException $e) {
            $error = "Errore nell'invio della risposta: " . $e->getMessage();
        }
    }
}

// =============================================
// MAPPA CATEGORIE COMPLETA
// =============================================
$category_mapping = [
    'fashion' => 'Fashion',
    'lifestyle' => 'Lifestyle',
    'beauty-makeup' => 'Beauty & Makeup',
    'food' => 'Food',
    'travel' => 'Travel',
    'gaming' => 'Gaming',
    'fitness-wellness' => 'Fitness & Wellness',
    'entertainment' => 'Entertainment',
    'tech' => 'Tech',
    'finance-business' => 'Finance & Business',
    'pet' => 'Pet',
    'education' => 'Education'
];

// =============================================
// RECUPERO SPONSOR DELL'INFLUENCER
// =============================================
$sponsors = [];
$sponsor_stats = [
    'total' => 0,
    'active' => 0,
    'pending' => 0,
    'rejected' => 0
];

// Parametri di filtro
$search_title = $_GET['search_title'] ?? '';
$filter_category = $_GET['filter_category'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

try {
    // Query base per sponsor
    $query = "
        SELECT id, title, image_url, budget, category, status, created_at
        FROM sponsors 
        WHERE influencer_id = ? AND deleted_at IS NULL
    ";
    
    $params = [$influencer['id']];
    
    // Applica filtri
    if (!empty($search_title)) {
        $query .= " AND title LIKE ?";
        $params[] = "%$search_title%";
    }
    
    if (!empty($filter_category)) {
        $query .= " AND category = ?";
        $params[] = $filter_category;
    }
    
    if (!empty($filter_status)) {
        $query .= " AND status = ?";
        $params[] = $filter_status;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sponsors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistiche (aggiornate per non contare le bozze)
    $stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected
    FROM sponsors 
    WHERE influencer_id = ? AND deleted_at IS NULL
");
    $stmt->execute([$influencer['id']]);
    $sponsor_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento degli sponsor: " . $e->getMessage();
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

<style>
.info-request-btn {
    position: relative;
}

.info-request-btn::after {
    content: '';
    position: absolute;
    top: 2px;
    right: 2px;
    width: 8px;
    height: 8px;
    background-color: #dc3545;
    border-radius: 50%;
    border: 1px solid #fff;
}

.info-request-btn i {
    color: #0dcaf0;
}

.info-request-btn:hover i {
    color: #0a58ca;
}
</style>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>I Miei Sponsor</h2>
            <a href="create-sponsor.php" class="btn btn-primary">
                ➕ Nuovo Sponsor
            </a>
        </div>

        <!-- Messaggi di stato -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == 'sponsor_created'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Sponsor creato con successo!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == 'sponsor_deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Sponsor eliminato con successo!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == 'info_request_replied'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Risposta inviata con successo!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistiche Sponsor (modificata per 3 contatori) -->
        <div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body text-center py-3">
                <h5 class="card-title"><?php echo $sponsor_stats['total']; ?></h5>
                <p class="card-text small">Sponsor Totali</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body text-center py-3">
                <h5 class="card-title"><?php echo $sponsor_stats['active']; ?></h5>
                <p class="card-text small">Attivi</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body text-center py-3">
                <h5 class="card-title"><?php echo $sponsor_stats['pending']; ?></h5>
                <p class="card-text small">In revisione</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-danger">
            <div class="card-body text-center py-3">
                <h5 class="card-title"><?php echo $sponsor_stats['rejected']; ?></h5>
                <p class="card-text small">Rifiutati</p>
            </div>
        </div>
    </div>
</div>

        <!-- Barra Filtri -->
        <div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-filter me-2"></i>Filtri Sponsor
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end">
            <!-- Filtro Titolo -->
            <div class="col-md-3">
                <label for="search_title" class="form-label">Cerca per titolo</label>
                <input type="text" 
                       class="form-control" 
                       id="search_title" 
                       name="search_title" 
                       placeholder="Cerca per titolo sponsor..."
                       value="<?php echo htmlspecialchars($search_title); ?>">
            </div>
            
            <!-- Filtro Categoria -->
            <div class="col-md-3">
                <label for="filter_category" class="form-label">Categoria</label>
                <select class="form-select" id="filter_category" name="filter_category">
                    <option value="">Tutte le categorie</option>
                    <?php foreach ($category_mapping as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" 
                            <?php echo $filter_category === $value ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Filtro Stato (modificato: rimossi Completato e Cancellato) -->
            <div class="col-md-3">
                <label for="filter_status" class="form-label">Stato</label>
                <select class="form-select" id="filter_status" name="filter_status">
                    <option value="">Tutti gli stati</option>
                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Attivo</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>In revisione</option>
                    <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rifiutato</option>
                </select>
            </div>
            
            <!-- Pulsanti -->
            <div class="col-md-3">
                <div class="d-grid gap-2 d-md-flex">
                    <button type="submit" class="btn btn-primary flex-fill me-md-2">
                        Applica filtri
                    </button>
                    <a href="sponsors.php" class="btn btn-outline-secondary flex-fill">
                        Reset
                    </a>
                </div>
            </div>
        </form>
        
        <!-- Indicatore filtri attivi -->
        <?php if (!empty($search_title) || !empty($filter_category) || !empty($filter_status)): ?>
            <div class="mt-3">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Filtri attivi: 
                    <?php 
                    $active_filters = [];
                    if (!empty($search_title)) {
                        $active_filters[] = "Titolo: \"$search_title\"";
                    }
                    if (!empty($filter_category)) {
                        $display_category = $category_mapping[$filter_category] ?? ucwords(str_replace(['_', '-'], ' ', $filter_category));
                        $active_filters[] = "Categoria: \"$display_category\"";
                    }
                    if (!empty($filter_status)) {
                        $status_labels = [
                            'active' => 'Attivo',
                            'completed' => 'Completato',
                            'cancelled' => 'Cancellato',
                            'pending' => 'In revisione',
                            'rejected' => 'Rifiutato'
                        ];
                        $status_display = $status_labels[$filter_status] ?? ucfirst($filter_status);
                        $active_filters[] = "Stato: \"$status_display\"";
                    }
                    echo implode(', ', $active_filters);
                    ?>
                    - <strong><?php echo count($sponsors); ?></strong> sponsor trovati
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

        <!-- Lista Sponsor -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">I miei Sponsor</h5>
            </div>
            <div class="card-body">
                <?php if (empty($sponsors)): ?>
                    <div class="text-center py-5">
                        <h5>
                            <?php if (!empty($search_title) || !empty($filter_category) || !empty($filter_status)): ?>
                                Nessuno sponsor trovato con i filtri applicati
                            <?php else: ?>
                                Nessuno sponsor creato
                            <?php endif; ?>
                        </h5>
                        <p class="text-muted mb-4">
                            <?php if (!empty($search_title) || !empty($filter_category) || !empty($filter_status)): ?>
                                Prova a modificare i filtri di ricerca
                            <?php else: ?>
                                Crea il tuo primo sponsor per iniziare a collaborare con i brand
                            <?php endif; ?>
                        </p>
                        <?php if (empty($search_title) && empty($filter_category) && empty($filter_status)): ?>
                            <a href="create-sponsor.php" class="btn btn-primary">
                                Crea il Primo Sponsor
                            </a>
                        <?php else: ?>
                            <a href="sponsors.php" class="btn btn-outline-primary">
                                Azzera Filtri
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Copertina</th>
                                    <th>Titolo</th>
                                    <th>Prezzo</th>
                                    <th>Categoria</th>
                                    <th>Stato</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sponsors as $sponsor): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            // Definisci il percorso del placeholder
                                            $placeholder_path = '/uploads/placeholder/sponsor_influencer_preview.png';
                                            
                                            if (!empty($sponsor['image_url'])) {
                                                // Se esiste un'immagine caricata dall'influencer, mostra quella
                                                $image_path = '/uploads/sponsor/' . htmlspecialchars($sponsor['image_url']);
                                                ?>
                                                <img src="<?php echo $image_path; ?>" 
                                                     alt="<?php echo htmlspecialchars($sponsor['title']); ?>" 
                                                     style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;"
                                                     onerror="this.onerror=null; this.src='<?php echo $placeholder_path; ?>';">
                                            <?php } else { 
                                                // Se NON esiste un'immagine caricata, mostra il placeholder
                                                ?>
                                                <img src="<?php echo $placeholder_path; ?>" 
                                                     alt="Placeholder - <?php echo htmlspecialchars($sponsor['title']); ?>" 
                                                     style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;"
                                                     title="Immagine di copertina non caricata">
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($sponsor['title']); ?>
                                        </td>
                                        <td>
                                            €<?php echo number_format($sponsor['budget'], 2); ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $display_category = $sponsor['category'];
                                            
                                            // Usa il mapping se esiste
                                            if (isset($category_mapping[$sponsor['category']])) {
                                                $display_category = $category_mapping[$sponsor['category']];
                                            } else {
                                                // Se non esiste nel mapping, trasforma lo slug in testo normale
                                                // Rimuove underscore e trattini, sostituisce con spazi
                                                $display_category = str_replace(['_', '-'], ' ', $display_category);
                                                // Mette l'iniziale maiuscola e ogni parola con iniziale maiuscola
                                                $display_category = ucwords($display_category);
                                            }
                                            ?>
                                            <?php echo htmlspecialchars($display_category); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'active' => 'success',
                                                'completed' => 'info',
                                                'cancelled' => 'danger',
                                                'pending' => 'warning',
                                                'rejected' => 'danger'
                                            ];
                                            $badge_class = $status_badges[$sponsor['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php 
                                                $status_labels = [
                                                    'active' => 'Attivo',
                                                    'completed' => 'Completato',
                                                    'cancelled' => 'Cancellato',
                                                    'pending' => 'In revisione',
                                                    'rejected' => 'Rifiutato'
                                                ];
                                                echo $status_labels[$sponsor['status']] ?? ucfirst($sponsor['status']);
                                                ?>
                                            </span>
                                        </td>
                                        <td>
    <div class="d-flex gap-1" style="min-width: 175px;">
        <a href="view-sponsor.php?id=<?php echo $sponsor['id']; ?>" 
           class="btn btn-outline-primary btn-sm d-flex align-items-center justify-content-center" 
           title="Visualizza"
           style="width: 36px; height: 36px; padding: 0;">
            👁️
        </a>
        <a href="edit-sponsor.php?id=<?php echo $sponsor['id']; ?>" 
           class="btn btn-outline-secondary btn-sm d-flex align-items-center justify-content-center" 
           title="Modifica"
           style="width: 36px; height: 36px; padding: 0;">
            ✏️
        </a>
        
        <!-- Pulsante Elimina -->
        <button type="button" 
                class="btn btn-outline-danger btn-sm delete-sponsor-btn d-flex align-items-center justify-content-center" 
                title="Elimina"
                data-sponsor-id="<?php echo $sponsor['id']; ?>"
                style="width: 36px; height: 36px; padding: 0;">
            <i class="fas fa-trash"></i>
        </button>
        
        <!-- Pulsante Richiesta informazioni (sempre presente ma visibile solo se necessario) -->
        <?php if (sponsorHasPendingRequests($sponsor['id'])): ?>
            <?php
            // Ottieni la prima richiesta pendente per questo sponsor
            $pending_requests = getInfluencerInfoRequests($influencer['id'], $sponsor['id']);
            $first_request = $pending_requests[0] ?? null;
            ?>
            <button type="button" 
                    class="btn btn-outline-info btn-sm d-flex align-items-center justify-content-center info-request-btn" 
                    title="Richiesta informazioni"
                    data-request-id="<?php echo $first_request['id']; ?>"
                    data-sponsor-title="<?php echo htmlspecialchars($sponsor['title']); ?>"
                    data-admin-message="<?php echo htmlspecialchars($first_request['message']); ?>"
                    data-request-date="<?php echo date('d/m/Y H:i', strtotime($first_request['created_at'])); ?>"
                    style="width: 36px; height: 36px; padding: 0;">
                <i class="fas fa-exclamation-circle"></i>
            </button>
        <?php else: ?>
            <!-- Spazio riservato per mantenere la larghezza uniforme -->
            <div style="width: 36px; height: 36px; visibility: hidden;"></div>
        <?php endif; ?>
    </div>
</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal di conferma eliminazione -->
<div class="modal fade" id="deleteSponsorModal" tabindex="-1" aria-labelledby="deleteSponsorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteSponsorModalLabel">Conferma Eliminazione</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body">
                <p>Sei sicuro di voler eliminare questo sponsor? Questa azione è irreversibile.</p>
                <p class="text-danger"><strong>Tutti i dati relativi a questo sponsor verranno eliminati permanentemente.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <form id="deleteSponsorForm" method="POST" style="display: inline;">
                    <input type="hidden" name="delete_sponsor" value="1">
                    <input type="hidden" name="sponsor_id" id="deleteSponsorId" value="">
                    <button type="submit" class="btn btn-danger">Elimina</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Risposta Richiesta Informazioni -->
<div class="modal fade" id="infoRequestReplyModal" tabindex="-1" aria-labelledby="infoRequestReplyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="infoRequestReplyForm">
                <input type="hidden" name="reply_info_request" value="1">
                <input type="hidden" name="request_id" id="replyRequestId" value="">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="infoRequestReplyModalLabel">
                        Richiesta informazioni
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <h6 id="replySponsorTitle" class="text-primary"></h6>
                        <small class="text-muted" id="replyRequestDate"></small>
                    </div>
                    
                    <div class="card bg-light mb-3">
                        <div class="card-header bg-transparent">
                            <strong>Supporto Kibbiz</strong>
                        </div>
                        <div class="card-body">
                            <p id="replyAdminMessage" class="mb-0"></p>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="replyResponse" class="form-label">
                            <strong>La tua risposta</strong> <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" 
                                  id="replyResponse" 
                                  name="response" 
                                  rows="4" 
                                  placeholder="Scrivi qui la tua risposta..."
                                  required></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-info">
                        Invia risposta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-sponsor-btn');
    const deleteSponsorModal = new bootstrap.Modal(document.getElementById('deleteSponsorModal'));
    const deleteSponsorIdInput = document.getElementById('deleteSponsorId');
    const deleteSponsorForm = document.getElementById('deleteSponsorForm');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const sponsorId = this.getAttribute('data-sponsor-id');
            deleteSponsorIdInput.value = sponsorId;
            deleteSponsorModal.show();
        });
    });
    
    // Gestione richieste informazioni
    const infoRequestButtons = document.querySelectorAll('.info-request-btn');
    const infoRequestModal = new bootstrap.Modal(document.getElementById('infoRequestReplyModal'));
    const replyRequestIdInput = document.getElementById('replyRequestId');
    const replySponsorTitle = document.getElementById('replySponsorTitle');
    const replyAdminMessage = document.getElementById('replyAdminMessage');
    const replyRequestDate = document.getElementById('replyRequestDate');
    
    infoRequestButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const requestId = this.getAttribute('data-request-id');
            const sponsorTitle = this.getAttribute('data-sponsor-title');
            const adminMessage = this.getAttribute('data-admin-message');
            const requestDate = this.getAttribute('data-request-date');
            
            replyRequestIdInput.value = requestId;
            replySponsorTitle.textContent = sponsorTitle;
            replyAdminMessage.textContent = adminMessage;
            replyRequestDate.textContent = 'Richiesta del: ' + requestDate.replace(' ', ' - ');
            
            infoRequestModal.show();
        });
    });
    
    // Reset form quando il modal viene chiuso
    document.getElementById('infoRequestReplyModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('infoRequestReplyForm').reset();
    });
});
</script>

<?php
// =============================================
// INCLUSIONE FOOTER
// =============================================
$footer_file = dirname(__DIR__) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>