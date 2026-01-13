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
// INCLUSIONE FUNZIONI SOCIAL NETWORK
// =============================================
require_once dirname(dirname(dirname(__FILE__))) . '/includes/social_network_functions.php';

// =============================================
// PAGINAZIONE
// =============================================
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$campaigns_per_page = 12;
$offset = ($current_page - 1) * $campaigns_per_page;

// =============================================
// QUERY CAMPAIGNE PREFERITE
// =============================================
$campaigns = [];
$total_campaigns = 0;
$total_pages = 0;

try {
    // Query base per le campagne preferite
    $query = "
        SELECT c.*, b.company_name, b.website as brand_website,
               COUNT(ca.id) as application_count,
               EXISTS(
                   SELECT 1 FROM campaign_applications ca2 
                   WHERE ca2.campaign_id = c.id AND ca2.influencer_id = ?
               ) as has_applied,
               fc.created_at as saved_at
        FROM favorite_campaigns fc
        JOIN campaigns c ON fc.campaign_id = c.id
        JOIN brands b ON c.brand_id = b.id
        LEFT JOIN campaign_applications ca ON c.id = ca.campaign_id
        WHERE fc.influencer_id = ?
          AND c.deleted_at IS NULL
    ";
    
    $count_query = "
        SELECT COUNT(*)
        FROM favorite_campaigns fc
        JOIN campaigns c ON fc.campaign_id = c.id
        WHERE fc.influencer_id = ?
          AND c.deleted_at IS NULL
    ";
    
    $params = [$influencer['id'], $influencer['id']];
    $count_params = [$influencer['id']];
    
    // Conteggio totale
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $total_campaigns = $stmt->fetchColumn();
    $total_pages = ceil($total_campaigns / $campaigns_per_page);
    
    // Query con ordinamento e paginazione
    $query .= " GROUP BY c.id ORDER BY fc.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $campaigns_per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Errore nel caricamento delle campagne salvate: " . $e->getMessage());
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
            <h2>Campagne Salvate</h2>
            <a href="../dashboard.php" class="btn btn-outline-secondary">
                ← Torna alla Dashboard
            </a>
        </div>

        <!-- Statistiche -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $total_campaigns; ?></h5>
                        <p class="card-text">Campagne Salvate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo count(array_filter($campaigns, function($c) { return !$c['has_applied']; })); ?>
                        </h5>
                        <p class="card-text">Da Candidarsi</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo count(array_filter($campaigns, function($c) { return $c['has_applied']; })); ?>
                        </h5>
                        <p class="card-text">Già Candidate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Intestazione Lista Campagne Salvate -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Lista campagne salvate
                </h5>
            </div>
        </div>

        <!-- Lista Campagne Salvate -->
        <?php if (empty($campaigns)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <h4>Nessuna campagna salvata</h4>
                    <p class="text-muted">
                        Salva le campagne che ti interessano cliccando sull'icona ❤️ nelle liste campagne
                    </p>
                    <a href="list.php" class="btn btn-primary">Esplora Campagne</a>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php 
                foreach ($campaigns as $campaign) {
                ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 <?php echo $campaign['has_applied'] ? 'border-success' : ''; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($campaign['name']); ?></h6>
                                <div>
                                    <?php if ($campaign['has_applied']): ?>
                                        <span class="badge bg-success">Già candidato</span>
                                    <?php endif; ?>
                                </div>
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
                                    if ($platforms) { 
                                        foreach ($platforms as $platform) { 
                                            $social_network = get_social_network_by_slug($platform);
                                            if ($social_network) {
                                    ?>
                                        <span class="badge bg-light text-dark me-1 mb-1">
                                            <i class="<?php echo $social_network['icon']; ?> me-1"></i>
                                            <?php echo htmlspecialchars($social_network['name']); ?>
                                        </span>
                                    <?php 
                                            }
                                        } 
                                    } 
                                    ?>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        Salvata il: <?php echo date('d/m/Y', strtotime($campaign['saved_at'])); ?>
                                    </small>
                                </div>
                                
                                <small class="text-muted">
                                    <?php echo $campaign['application_count']; ?> candidature
                                </small>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex flex-column gap-2">
                                    <!-- RIGA SUPERIORE: Pulsanti Dettagli campagna e Rimuovi preferiti -->
                                    <div class="d-flex gap-1">
                                        <!-- Pulsante Dettagli campagna -->
                                        <a href="view.php?id=<?php echo $campaign['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm flex-grow-1">
                                            Dettagli campagna
                                        </a>
                                        
                                        <!-- Pulsante Rimuovi dai preferiti -->
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-sm remove-favorite-btn"
                                                data-campaign-id="<?php echo $campaign['id']; ?>"
                                                title="Rimuovi dai preferiti">
                                            <i class="fas fa-heart text-danger"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- RIGA INFERIORE: Pulsante Candidati/Già Candidato -->
                                    <?php if (!$campaign['has_applied']): ?>
                                        <a href="view.php?id=<?php echo $campaign['id']; ?>&apply=1" 
                                           class="btn btn-success btn-sm w-100">
                                            Candidati Ora
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-success btn-sm w-100" disabled>
                                            Già Candidato
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                }
                ?>
            </div>

            <!-- Paginazione -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Paginazione campagne salvate">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Page -->
                        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>">
                                ← Precedente
                            </a>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php 
                        for ($i = 1; $i <= $total_pages; $i++) {
                        ?>
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php 
                        }
                        ?>
                        
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
// Gestione Rimozione dai preferiti nella pagina salvati
document.addEventListener('DOMContentLoaded', function() {
    // Trova tutti i pulsanti rimuovi preferiti
    const removeFavoriteButtons = document.querySelectorAll('.remove-favorite-btn');
    
    removeFavoriteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const campaignId = this.getAttribute('data-campaign-id');
            const campaignCard = this.closest('.col-md-6.col-lg-4.mb-4');
            
            if (!confirm('Sei sicuro di voler rimuovere questa campagna dai preferiti?')) {
                return;
            }
            
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
                body: `campaign_id=${campaignId}&action=remove`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Rimuovi la card dalla vista con animazione
                    campaignCard.style.transition = 'opacity 0.3s, transform 0.3s';
                    campaignCard.style.opacity = '0';
                    campaignCard.style.transform = 'translateY(-20px)';
                    
                    setTimeout(() => {
                        campaignCard.remove();
                        showToast('Campagna rimossa dai preferiti', 'success');
                        
                        // Aggiorna il contatore
                        const totalCards = document.querySelectorAll('.col-md-6.col-lg-4.mb-4').length;
                        const savedCountElement = document.querySelector('.card.text-white.bg-danger .card-title');
                        if (savedCountElement && totalCards === 0) {
                            // Se non ci sono più campagne, ricarica la pagina per mostrare il messaggio "nessuna campagna"
                            location.reload();
                        } else if (savedCountElement) {
                            savedCountElement.textContent = totalCards;
                        }
                    }, 300);
                    
                } else {
                    showToast('Errore: ' + data.message, 'error');
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Errore di connessione', 'error');
                this.innerHTML = originalHTML;
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
});
</script>

<style>
/* Stili per i pulsanti rimuovi preferiti */
.remove-favorite-btn.btn-outline-danger {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
}

.remove-favorite-btn:hover {
    transform: scale(1.05);
    transition: transform 0.2s ease-in-out;
}

/* Toast notifications */
.toast {
    min-width: 250px;
}

/* Stili per pulsanti uniformi */
.btn-outline-danger.btn-sm {
    width: 40px;
    padding-left: 0.25rem;
    padding-right: 0.25rem;
}

/* RIMUOVI OVERLAY HOVER PER PULSANTI PREFERITI (come in search-influencers.php) */
.remove-favorite-btn.btn-outline-danger:hover {
    background-color: transparent !important;
    color: #dc3545 !important;
    border-color: #dc3545 !important;
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