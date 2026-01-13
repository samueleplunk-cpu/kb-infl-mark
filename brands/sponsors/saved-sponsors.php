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
// INCLUSIONE FUNZIONI SOCIAL NETWORK
// =============================================
require_once dirname(dirname(dirname(__FILE__))) . '/includes/social_network_functions.php';

// =============================================
// INCLUSIONE FUNZIONI CATEGORIE
// =============================================
require_once dirname(dirname(dirname(__FILE__))) . '/includes/category_functions.php';

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
// PAGINAZIONE
// =============================================
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$sponsors_per_page = 12;
$offset = ($current_page - 1) * $sponsors_per_page;

// =============================================
// QUERY SPONSOR PREFERITI
// =============================================
$sponsors = [];
$total_sponsors = 0;
$total_pages = 0;

try {
    // Query base per gli sponsor preferiti
    $query = "
        SELECT s.*, i.full_name as influencer_name,
               i.profile_image, i.niche, i.rating,
               fs.created_at as saved_at
        FROM favorite_sponsors fs
        JOIN sponsors s ON fs.sponsor_id = s.id
        JOIN influencers i ON s.influencer_id = i.id
        JOIN users u ON i.user_id = u.id
        WHERE fs.brand_id = ?
          AND s.status = 'active'
          AND s.deleted_at IS NULL
          AND u.is_active = 1 
          AND u.is_suspended = 0 
          AND u.is_blocked = 0 
          AND u.deleted_at IS NULL
    ";
    
    $count_query = "
        SELECT COUNT(*)
        FROM favorite_sponsors fs
        JOIN sponsors s ON fs.sponsor_id = s.id
        JOIN influencers i ON s.influencer_id = i.id
        JOIN users u ON i.user_id = u.id
        WHERE fs.brand_id = ?
          AND s.status = 'active'
          AND s.deleted_at IS NULL
          AND u.is_active = 1 
          AND u.is_suspended = 0 
          AND u.is_blocked = 0 
          AND u.deleted_at IS NULL
    ";
    
    $params = [$brand['id']];
    $count_params = [$brand['id']];
    
    // Conteggio totale
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $total_sponsors = $stmt->fetchColumn();
    $total_pages = ceil($total_sponsors / $sponsors_per_page);
    
    // Query con ordinamento e paginazione
    $query .= " ORDER BY fs.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $sponsors_per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sponsors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Errore nel caricamento degli sponsor salvati: " . $e->getMessage());
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
            <h2>Sponsor Salvati</h2>
            <a href="../dashboard.php" class="btn btn-outline-secondary">
                ← Torna alla Dashboard
            </a>
        </div>

        <!-- Statistiche -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $total_sponsors; ?></h5>
                        <p class="card-text">Sponsor Salvati</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo $total_sponsors; ?>
                        </h5>
                        <p class="card-text">Opportunità Attive</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">0</h5>
                        <p class="card-text">In Negoziazione</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Intestazione Lista Sponsor Salvati -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Lista sponsor salvati
                </h5>
            </div>
        </div>

        <!-- Lista Sponsor Salvati -->
        <?php if (empty($sponsors)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <h4>Nessuno sponsor salvato</h4>
                    <p class="text-muted">
                        Salva gli sponsor che ti interessano cliccando sull'icona ❤️ nelle liste sponsor
                    </p>
                    <a href="list.php" class="btn btn-primary">Esplora Sponsor</a>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php 
                foreach ($sponsors as $sponsor) {
                ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <!-- Immagine sponsor se disponibile -->
                            <?php if ($sponsor['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($sponsor['image_url'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($sponsor['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                     style="height: 180px; object-fit: cover;">
                            <?php endif; ?>
                            
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($sponsor['title'], ENT_QUOTES, 'UTF-8'); ?></h6>
                            </div>
                            <div class="card-body">
                                <!-- Informazioni influencer -->
                                <div class="d-flex align-items-center mb-3">
                                    <?php 
                                    $has_profile_image = !empty($sponsor['profile_image']);
                                    $profile_image_path = '';
                                    
                                    if ($has_profile_image) {
                                        $profile_image_path = $sponsor['profile_image'];
                                        
                                        if (strpos($profile_image_path, 'profiles/') === 0) {
                                            $profile_image_path = '/uploads/' . $profile_image_path;
                                        } elseif (strpos($profile_image_path, '/profiles/') === 0) {
                                            $profile_image_path = '/uploads' . $profile_image_path;
                                        } elseif (strpos($profile_image_path, '/') !== 0 && strpos($profile_image_path, 'http') !== 0) {
                                            $profile_image_path = '/uploads/profiles/' . $profile_image_path;
                                        }
                                    }
                                    ?>
                                    
                                    <div class="me-2" style="width: 40px; height: 40px;">
                                        <?php if ($has_profile_image): ?>
                                            <img src="<?php echo htmlspecialchars($profile_image_path, ENT_QUOTES, 'UTF-8'); ?>" 
                                                 class="rounded-circle" 
                                                 width="40" 
                                                 height="40" 
                                                 alt="<?php echo htmlspecialchars($sponsor['influencer_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                 style="object-fit: cover; width: 100%; height: 100%;"
                                                 onerror="this.onerror=null; this.style.display='none'; this.parentNode.innerHTML='<div class=&quot;rounded-circle bg-secondary d-flex align-items-center justify-content-center&quot; style=&quot;width: 40px; height: 40px;&quot;><i class=&quot;fas fa-user text-white&quot;></i></div>';">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div>
                                        <strong><?php echo htmlspecialchars($sponsor['influencer_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($sponsor['niche'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                            <?php if ($sponsor['rating']): ?>
                                                • <i class="fas fa-star text-warning"></i> <?php echo number_format($sponsor['rating'], 1); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <p class="card-text text-muted small mb-3">
                                    <?php 
                                    $description = $sponsor['description'];
                                    if (strlen($description) > 100) {
                                        echo htmlspecialchars(substr($description, 0, 100) . '...', ENT_QUOTES, 'UTF-8');
                                    } else {
                                        echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
                                    }
                                    ?>
                                </p>
                                
                                <div class="mb-2">
                                    <strong>Budget:</strong> 
                                    <?php echo number_format($sponsor['budget'], 0); ?> €
                                </div>
                                
                                <div class="mb-2">
                                    <strong>Categoria:</strong>
                                    <?php echo htmlspecialchars_decode(ucfirst($sponsor['category']), ENT_QUOTES); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Social:</strong>
                                    <?php 
                                    $platforms = json_decode($sponsor['platforms'], true);
                                    if ($platforms && is_array($platforms)): 
                                        foreach ($platforms as $platform): 
                                            $platform = htmlspecialchars_decode($platform, ENT_QUOTES);
                                            switch(strtolower($platform)) {
                                                case 'instagram':
                                                    $icon = 'fa-brands fa-instagram';
                                                    break;
                                                case 'facebook':
                                                    $icon = 'fa-brands fa-facebook';
                                                    break;
                                                case 'tiktok':
                                                    $icon = 'fa-brands fa-tiktok';
                                                    break;
                                                case 'pinterest':
                                                    $icon = 'fa-brands fa-pinterest';
                                                    break;
                                                case 'youtube':
                                                    $icon = 'fa-brands fa-youtube';
                                                    break;
                                                case 'twitch':
                                                    $icon = 'fa-brands fa-twitch';
                                                    break;
                                                case 'telegram':
                                                    $icon = 'fa-brands fa-telegram';
                                                    break;
                                                case 'threads':
                                                    $icon = 'fa-brands fa-threads';
                                                    break;
                                                default:
                                                    $icon = 'fa-solid fa-share-nodes';
                                                    break;
                                            }
                                    ?>
                                        <i class="<?php echo $icon; ?> me-1" title="<?php echo htmlspecialchars(ucfirst($platform), ENT_QUOTES, 'UTF-8'); ?>"></i>
                                    <?php 
                                        endforeach; 
                                    endif; 
                                    ?>
                                </div>
                                
                            </div>
                            <div class="card-footer">
                                <div class="d-flex flex-column gap-2">
                                    <!-- RIGA SUPERIORE: Pulsanti Dettagli sponsor e Rimuovi preferiti -->
                                    <div class="d-flex gap-1">
                                        <!-- Pulsante Dettagli sponsor -->
                                        <a href="view.php?id=<?php echo $sponsor['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm flex-grow-1">
                                            Dettagli sponsor
                                        </a>
                                        
                                        <!-- Pulsante Rimuovi dai preferiti (STILE COERENTE CON list.php) -->
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-sm remove-favorite-btn"
                                                data-sponsor-id="<?php echo $sponsor['id']; ?>"
                                                title="Rimuovi dai preferiti">
                                            <i class="fas fa-heart text-danger"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- RIGA INFERIORE: Pulsante Contatta -->
                                    <a href="#" 
                                       class="btn btn-success btn-sm w-100"
                                       onclick="contactInfluencer(<?php echo $sponsor['id']; ?>, '<?php echo htmlspecialchars(addslashes($sponsor['influencer_name']), ENT_QUOTES, 'UTF-8'); ?>')">
                                        Contatta Influencer
                                    </a>
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
                <nav aria-label="Paginazione sponsor salvati">
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
            const sponsorId = this.getAttribute('data-sponsor-id');
            const sponsorCard = this.closest('.col-md-6.col-lg-4.mb-4');
            
            if (!confirm('Sei sicuro di voler rimuovere questo sponsor dai preferiti?')) {
                return;
            }
            
            // Disabilita il pulsante durante la richiesta
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            // Invia richiesta AJAX
            fetch('toggle-sponsor-favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `sponsor_id=${sponsorId}&action=remove`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Rimuovi la card dalla vista con animazione
                    sponsorCard.style.transition = 'opacity 0.3s, transform 0.3s';
                    sponsorCard.style.opacity = '0';
                    sponsorCard.style.transform = 'translateY(-20px)';
                    
                    setTimeout(() => {
                        sponsorCard.remove();
                        showToast('Sponsor rimosso dai preferiti', 'success');
                        
                        // Aggiorna il contatore
                        const totalCards = document.querySelectorAll('.col-md-6.col-lg-4.mb-4').length;
                        const savedCountElement = document.querySelector('.card.text-white.bg-warning .card-title');
                        if (savedCountElement && totalCards === 0) {
                            // Se non ci sono più sponsor, ricarica la pagina per mostrare il messaggio "nessuno sponsor"
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
    
    // Funzione per contattare l'influencer
    window.contactInfluencer = function(sponsorId, influencerName) {
        alert('Funzionalità "Contatta Influencer" per lo sponsor ID: ' + sponsorId + '\nInfluencer: ' + influencerName + '\n\nQuesta funzionalità sarà implementata in futuro per inviare messaggi agli influencer.');
    };
});
</script>

<style>
/* Stili per i pulsanti rimuovi preferiti (COERENTI CON list.php) */
.remove-favorite-btn.btn-outline-danger {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
}

.remove-favorite-btn:hover {
    transform: scale(1.05);
    transition: transform 0.2s ease-in-out;
}

/* RIMUOVI OVERLAY HOVER PER PULSANTI PREFERITI */
.remove-favorite-btn.btn-outline-danger:hover {
    background-color: transparent !important;
    color: #dc3545 !important;
    border-color: #dc3545 !important;
}

/* Stili per layout pulsanti */
.btn-outline-primary.btn-sm.flex-grow-1 {
    flex: 1 1 auto;
}

.btn-outline-danger.btn-sm {
    width: 40px;
    padding-left: 0.25rem;
    padding-right: 0.25rem;
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