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

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'influencer') {
    die("Accesso negato: Questa area è riservata agli influencer.");
}

// =============================================
// GESTIONE ALERT CAMPAGNA ELIMINATA
// =============================================
$show_campaign_deleted_alert = false;
if (isset($_GET['campaign_deleted']) && $_GET['campaign_deleted'] == 1) {
    $show_campaign_deleted_alert = true;
}

// =============================================
// INCLUSIONE FUNZIONI SOCIAL NETWORK
// =============================================
require_once dirname(dirname(dirname(__FILE__))) . '/includes/social_network_functions.php';

// =============================================
// INCLUSIONE FUNZIONI CATEGORIE
// =============================================
require_once dirname(dirname(dirname(__FILE__))) . '/includes/category_functions.php';

// =============================================
// RECUPERO DATI INFLUENCER
// =============================================
$influencer = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM influencers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        header("Location: ../create-profile.php");
        exit();
    }
} catch (PDOException $e) {
    die("Errore nel caricamento del profilo: " . $e->getMessage());
}

// =============================================
// PARAMETRI RICERCA E FILTRI
// =============================================
$search = $_GET['search'] ?? '';
$niche_filter = $_GET['niche'] ?? '';
$min_budget = $_GET['min_budget'] ?? '';
$max_budget = $_GET['max_budget'] ?? '';
$platform_filter = $_GET['platform'] ?? '';

// Paginazione
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$campaigns_per_page = 12;
$offset = ($current_page - 1) * $campaigns_per_page;

// =============================================
// RECUPERO CATEGORIE ATTIVE DAL DATABASE
// =============================================
$active_categories = get_active_categories($pdo);

// =============================================
// QUERY CAMPAIGNE CON FILTRI
// =============================================
$campaigns = [];
$total_campaigns = 0;
$total_pages = 0;

try {
    // Query base
    $query = "
        SELECT c.*, b.company_name, b.website as brand_website,
               COUNT(ca.id) as application_count,
               EXISTS(
                   SELECT 1 FROM campaign_applications ca2 
                   WHERE ca2.campaign_id = c.id AND ca2.influencer_id = ?
               ) as has_applied
        FROM campaigns c
        JOIN brands b ON c.brand_id = b.id
        LEFT JOIN campaign_applications ca ON c.id = ca.campaign_id
        WHERE c.status = 'active' 
          AND c.is_public = TRUE 
          AND c.allow_applications = TRUE
          AND c.deleted_at IS NULL
    ";
    
    $count_query = "
        SELECT COUNT(DISTINCT c.id)
        FROM campaigns c
        WHERE c.status = 'active' 
          AND c.is_public = TRUE 
          AND c.allow_applications = TRUE
          AND c.deleted_at IS NULL
    ";
    
    $params = [$influencer['id']];
    $count_params = [];
    
    // Applica filtri
    if (!empty($search)) {
        $query .= " AND (c.name LIKE ? OR c.description LIKE ?)";
        $count_query .= " AND (c.name LIKE ? OR c.description LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $count_params[] = $search_term;
        $count_params[] = $search_term;
    }
    
    if (!empty($niche_filter)) {
        $query .= " AND c.niche = ?";
        $count_query .= " AND c.niche = ?";
        $params[] = $niche_filter;
        $count_params[] = $niche_filter;
    }
    
    if (!empty($min_budget)) {
        $query .= " AND c.budget >= ?";
        $count_query .= " AND c.budget >= ?";
        $params[] = floatval($min_budget);
        $count_params[] = floatval($min_budget);
    }
    
    if (!empty($max_budget)) {
        $query .= " AND c.budget <= ?";
        $count_query .= " AND c.budget <= ?";
        $params[] = floatval($max_budget);
        $count_params[] = floatval($max_budget);
    }
    
    if (!empty($platform_filter)) {
        $query .= " AND JSON_CONTAINS(c.platforms, ?)";
        $count_query .= " AND JSON_CONTAINS(c.platforms, ?)";
        $params[] = json_encode($platform_filter);
        $count_params[] = json_encode($platform_filter);
    }
    
    // Conteggio totale
    $query .= " GROUP BY c.id ORDER BY c.created_at DESC";
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $total_campaigns = $stmt->fetchColumn();
    $total_pages = ceil($total_campaigns / $campaigns_per_page);
    
    // Query con paginazione
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $campaigns_per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Errore nel caricamento delle campagne: " . $e->getMessage());
}

// =============================================
// RECUPERO CAMPAIGNE PREFERITE PER L'INFLUENCER
// =============================================
$favorite_campaigns = [];
if ($influencer && !empty($campaigns)) {
    // Estrai tutti gli ID campagna dai risultati
    $campaign_ids = array_column($campaigns, 'id');
    
    // Recupera tutte le campagne preferite in una sola query
    try {
        if (!empty($campaign_ids)) {
            // Crea i placeholder per la query
            $placeholders = implode(',', array_fill(0, count($campaign_ids), '?'));
            
            $stmt = $pdo->prepare("
                SELECT campaign_id 
                FROM favorite_campaigns 
                WHERE influencer_id = ? 
                AND campaign_id IN ($placeholders)
            ");
            
            // Parametri: influencer_id + tutti i campaign_ids
            $params_fav = array_merge([$influencer['id']], $campaign_ids);
            $stmt->execute($params_fav);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Trasforma in array associativo campaign_id => true
            foreach ($results as $row) {
                $favorite_campaigns[$row['campaign_id']] = true;
            }
        }
    } catch (PDOException $e) {
        error_log("Errore recupero campagne preferite: " . $e->getMessage());
        // Continua senza preferiti
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
            <h2>Campagne Pubbliche</h2>
            <a href="../dashboard.php" class="btn btn-outline-secondary">
                ← Torna alla Dashboard
            </a>
        </div>

        <!-- Alert campagna eliminata -->
        <?php if ($show_campaign_deleted_alert): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Attenzione:</strong> Questa campagna non è più disponibile.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
        </div>
        <?php endif; ?>

        <!-- Statistiche (SPOSTATE PRIMA DEI FILTRI) -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $total_campaigns; ?></h5>
                        <p class="card-text">Campagne trovate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo count(array_filter($campaigns, function($c) { return !$c['has_applied']; })); ?>
                        </h5>
                        <p class="card-text">Nuove opportunità</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo count(array_filter($campaigns, function($c) { return $c['has_applied']; })); ?>
                        </h5>
                        <p class="card-text">Candidature inviate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo array_sum(array_column($campaigns, 'application_count')); ?>
                        </h5>
                        <p class="card-text">Candidature totali</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtri -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Filtri</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Cerca</label>
                        <input type="text" name="search" class="form-control" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nome campagna...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Categoria</label>
                        <select name="niche" class="form-select">
                            <option value="">Tutte</option>
                            <?php
                            // CATEGORIE DINAMICHE DAL DATABASE
                            foreach ($active_categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>" 
                                    <?php echo $niche_filter === $category['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Social Network</label>
                        <select name="platform" class="form-select">
                            <option value="">Tutte</option>
                            <?php
                            $social_networks = get_active_social_networks();
                            foreach ($social_networks as $social): ?>
                                <option value="<?php echo $social['slug']; ?>" 
                                    <?php echo $platform_filter === $social['slug'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($social['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Budget Min</label>
                        <input type="number" name="min_budget" class="form-control" 
                               value="<?php echo htmlspecialchars($min_budget); ?>" 
                               placeholder="€ Min">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Budget Max</label>
                        <input type="number" name="max_budget" class="form-control" 
                               value="<?php echo htmlspecialchars($max_budget); ?>" 
                               placeholder="€ Max">
                    </div>
                    <div class="col-md-12 mt-3">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" class="btn btn-primary">Cerca</button>
                            <a href="list.php" class="btn btn-outline-secondary">
                                Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista Campagne -->
        <?php if (empty($campaigns)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <h4>Nessuna campagna trovata</h4>
                    <p class="text-muted">
                        <?php echo $total_campaigns > 0 ? 'Prova a modificare i filtri di ricerca.' : 'Al momento non ci sono campagne pubbliche disponibili.'; ?>
                    </p>
                    <?php if (!empty($search) || !empty($niche_filter)): ?>
                        <a href="list.php" class="btn btn-primary">Rimuovi Filtri</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($campaigns as $campaign): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 <?php echo $campaign['has_applied'] ? 'border-success' : ''; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($campaign['name']); ?></h6>
                                <?php if ($campaign['has_applied']): ?>
                                    <span class="badge bg-success">Già candidato</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <p class="card-text text-muted small">
                                    <?php echo strlen($campaign['description']) > 100 ? 
                                        substr(htmlspecialchars($campaign['description']), 0, 100) . '...' : 
                                        htmlspecialchars($campaign['description']); ?>
                                </p>
                                
                                <div class="mb-2">
                                    <strong>Budget:</strong> 
                                    <span class="badge bg-success">€<?php echo number_format($campaign['budget'], 2); ?></span>
                                </div>
                                
                                <div class="mb-2">
                                    <strong>Niche:</strong>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($campaign['niche']); ?></span>
                                </div>
                                
                                <div class="mb-2">
                                    <strong>Brand:</strong>
                                    <?php echo htmlspecialchars($campaign['company_name']); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Piattaforme:</strong><br>
                                    <?php 
                                    $platforms = json_decode($campaign['platforms'], true);
                                    if ($platforms): 
                                        foreach ($platforms as $platform): 
                                            $social_network = get_social_network_by_slug($platform);
                                            if ($social_network):
                                    ?>
                                        <span class="badge bg-light text-dark me-1 mb-1">
                                            <i class="<?php echo $social_network['icon']; ?> me-1"></i>
                                            <?php echo htmlspecialchars($social_network['name']); ?>
                                        </span>
                                    <?php 
                                            endif;
                                        endforeach; 
                                    endif; 
                                    ?>
                                </div>
                                
                                <small class="text-muted">
                                    <?php echo $campaign['application_count']; ?> candidature
                                </small>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex flex-column gap-2">
                                    <!-- RIGA SUPERIORE: Pulsanti Dettagli campagna e Preferiti -->
                                    <div class="d-flex gap-1">
                                        <!-- Pulsante Dettagli campagna -->
                                        <a href="view.php?id=<?php echo $campaign['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm flex-grow-1">
                                            Dettagli campagna
                                        </a>
                                        
                                        <!-- Pulsante Preferiti (solo icona) -->
                                        <button type="button" 
                                                class="btn <?php echo isset($favorite_campaigns[$campaign['id']]) ? 'btn-outline-danger' : 'btn-outline-secondary'; ?> btn-sm favorite-campaign-btn"
                                                data-campaign-id="<?php echo $campaign['id']; ?>"
                                                data-is-favorite="<?php echo isset($favorite_campaigns[$campaign['id']]) ? '1' : '0'; ?>"
                                                title="<?php echo isset($favorite_campaigns[$campaign['id']]) ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'; ?>">
                                            <i class="<?php echo isset($favorite_campaigns[$campaign['id']]) ? 'fas fa-heart text-danger' : 'far fa-heart text-secondary'; ?>"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- RIGA INFERIORE: Pulsante Candidati/Già Candidato -->
                                    <?php 
                                    // Verifica se l'influencer ha rifiutato l'invito per questa campagna
                                    $has_rejected_invitation = false;
                                    try {
                                        $stmt_reject = $pdo->prepare("
                                            SELECT status 
                                            FROM campaign_influencers 
                                            WHERE campaign_id = ? AND influencer_id = ? AND status = 'rejected'
                                        ");
                                        $stmt_reject->execute([$campaign['id'], $influencer['id']]);
                                        $has_rejected_invitation = $stmt_reject->rowCount() > 0;
                                    } catch (PDOException $e) {
                                        error_log("Errore verifica rifiuto invito campagna: " . $e->getMessage());
                                        $has_rejected_invitation = false;
                                    }

                                    if (!$campaign['has_applied'] && !$has_rejected_invitation): ?>
                                        <a href="view.php?id=<?php echo $campaign['id']; ?>&apply=1" 
                                           class="btn btn-success btn-sm w-100">
                                            Candidati Ora
                                        </a>
                                    <?php elseif ($campaign['has_applied']): ?>
                                        <button class="btn btn-success btn-sm w-100" disabled>
                                            Già Candidato
                                        </button>
                                    <?php elseif ($has_rejected_invitation): ?>
                                        <span class="text-muted small w-100 d-block text-center">
                                            Hai rifiutato l'invito a collaborare
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Paginazione -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Paginazione campagne">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Page -->
                        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>">
                                ← Precedente
                            </a>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <!-- Next Page -->
                        <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>">
                                Successiva →
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Gestione Preferiti Campagne con AJAX
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
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
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
                        // Aggiorna stile per list.php
                        this.classList.remove('btn-outline-secondary');
                        this.classList.add('btn-outline-danger');
                        this.title = 'Rimuovi dai preferiti';
                        const icon = this.querySelector('i');
                        if (icon) icon.className = 'fas fa-heart text-danger';
                    } else {
                        // Aggiorna stile per list.php
                        this.classList.remove('btn-outline-danger');
                        this.classList.add('btn-outline-secondary');
                        this.title = 'Aggiungi ai preferiti';
                        const icon = this.querySelector('i');
                        if (icon) icon.className = 'far fa-heart text-secondary';
                    }
                    
                    // Mostra notifica
                    showToast(isNowFavorite ? 'Campagna aggiunta ai preferiti!' : 'Campagna rimossa dai preferiti!', 'success');
                } else {
                    showToast('Errore: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Errore di connessione', 'error');
            })
            .finally(() => {
                this.disabled = false;
                // Ripristina HTML originale se l'aggiornamento fallisce
                if (!this.hasAttribute('data-updated')) {
                    this.innerHTML = originalHTML;
                }
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
});
</script>

<style>
/* Stili per i pulsanti preferiti campagne */
.favorite-campaign-btn.btn-outline-danger {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
}

.favorite-campaign-btn.btn-outline-secondary {
    color: #6c757d !important;
    border-color: #6c757d !important;
}

.favorite-campaign-btn:hover {
    transform: scale(1.05);
    transition: transform 0.2s ease-in-out;
}

/* Toast notifications */
.toast {
    min-width: 250px;
}

/* Stili per pulsanti uniformi */
.btn-outline-danger.btn-sm,
.btn-outline-secondary.btn-sm {
    width: 40px;
    padding-left: 0.25rem;
    padding-right: 0.25rem;
}

/* RIMUOVI OVERLAY HOVER PER PULSANTI PREFERITI (come in search-influencers.php) */
.favorite-campaign-btn.btn-outline-danger:hover,
.favorite-campaign-btn.btn-outline-secondary:hover {
    background-color: transparent !important;
}

/* Colori specifici per hover (come in search-influencers.php) */
.favorite-campaign-btn.btn-outline-danger:hover {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
}

.favorite-campaign-btn.btn-outline-secondary:hover {
    color: #6c757d !important;
    border-color: #6c757d !important;
}

/* Stili per layout pulsanti (come in search-influencers.php) */
.btn-outline-primary.btn-sm.flex-grow-1 {
    flex: 1 1 auto;
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