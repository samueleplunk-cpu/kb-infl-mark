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
// GESTIONE ALERT SPONSOR ELIMINATO
// =============================================
$show_sponsor_deleted_alert = false;
if (isset($_GET['sponsor_deleted']) && $_GET['sponsor_deleted'] == 1) {
    $show_sponsor_deleted_alert = true;
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
// PARAMETRI RICERCA E FILTRI
// =============================================
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$min_budget = $_GET['min_budget'] ?? '';
$max_budget = $_GET['max_budget'] ?? '';
$platform_filter = $_GET['platform'] ?? '';

// Paginazione
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$sponsors_per_page = 12;
$offset = ($current_page - 1) * $sponsors_per_page;

// =============================================
// RECUPERO CATEGORIE ATTIVE DAL DATABASE
// =============================================
$active_categories = get_active_categories($pdo);

// =============================================
// QUERY SPONSOR CON FILTRI
// =============================================
$sponsors = [];
$total_sponsors = 0;
$total_pages = 0;

try {
    // Query base - usando le colonne corrette dalla tabella influencers
    $query = "
        SELECT s.*, 
               i.full_name as influencer_name,
               i.profile_image,
               i.niche,
               i.rating
        FROM sponsors s
        JOIN influencers i ON s.influencer_id = i.id
        JOIN users u ON i.user_id = u.id
        WHERE s.status = 'active' 
          AND u.is_active = 1 
          AND u.is_suspended = 0 
          AND u.is_blocked = 0 
          AND u.deleted_at IS NULL
          AND s.deleted_at IS NULL
    ";
    
    $count_query = "
        SELECT COUNT(s.id)
        FROM sponsors s
        JOIN influencers i ON s.influencer_id = i.id
        JOIN users u ON i.user_id = u.id
        WHERE s.status = 'active' 
          AND u.is_active = 1 
          AND u.is_suspended = 0 
          AND u.is_blocked = 0 
          AND u.deleted_at IS NULL
          AND s.deleted_at IS NULL
    ";
    
    $params = [];
    $count_params = [];
    
    // Applica filtri
    if (!empty($search)) {
        $query .= " AND (s.title LIKE ? OR s.description LIKE ? OR i.full_name LIKE ?)";
        $count_query .= " AND (s.title LIKE ? OR s.description LIKE ? OR i.full_name LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $count_params[] = $search_term;
        $count_params[] = $search_term;
        $count_params[] = $search_term;
    }
    
    if (!empty($category_filter)) {
        $query .= " AND s.category = ?";
        $count_query .= " AND s.category = ?";
        $params[] = $category_filter;
        $count_params[] = $category_filter;
    }
    
    if (!empty($min_budget)) {
        $query .= " AND s.budget >= ?";
        $count_query .= " AND s.budget >= ?";
        $params[] = floatval($min_budget);
        $count_params[] = floatval($min_budget);
    }
    
    if (!empty($max_budget)) {
        $query .= " AND s.budget <= ?";
        $count_query .= " AND s.budget <= ?";
        $params[] = floatval($max_budget);
        $count_params[] = floatval($max_budget);
    }
    
    if (!empty($platform_filter)) {
        $query .= " AND JSON_CONTAINS(s.platforms, ?)";
        $count_query .= " AND JSON_CONTAINS(s.platforms, ?)";
        $params[] = json_encode($platform_filter);
        $count_params[] = json_encode($platform_filter);
    }
    
    // Conteggio totale
    $query .= " ORDER BY s.created_at DESC";
    
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $total_sponsors = $stmt->fetchColumn();
    $total_pages = ceil($total_sponsors / $sponsors_per_page);
    
    // Query con paginazione
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $sponsors_per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sponsors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Errore nel caricamento degli sponsor: " . $e->getMessage());
}

// =============================================
// RECUPERO SPONSOR PREFERITI PER IL BRAND
// =============================================
$favorite_sponsors = [];
if ($brand && !empty($sponsors)) {
    // Estrai tutti gli ID sponsor dai risultati
    $sponsor_ids = array_column($sponsors, 'id');
    
    // Recupera tutti gli sponsor preferiti in una sola query
    try {
        if (!empty($sponsor_ids)) {
            // Crea i placeholder per la query
            $placeholders = implode(',', array_fill(0, count($sponsor_ids), '?'));
            
            $stmt = $pdo->prepare("
                SELECT sponsor_id 
                FROM favorite_sponsors 
                WHERE brand_id = ? 
                AND sponsor_id IN ($placeholders)
            ");
            
            // Parametri: brand_id + tutti i sponsor_ids
            $params_fav = array_merge([$brand['id']], $sponsor_ids);
            $stmt->execute($params_fav);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Trasforma in array associativo sponsor_id => true
            foreach ($results as $row) {
                $favorite_sponsors[$row['sponsor_id']] = true;
            }
        }
    } catch (PDOException $e) {
        error_log("Errore recupero sponsor preferiti: " . $e->getMessage());
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
            <h2>Sponsor Disponibili</h2>
            <a href="../dashboard.php" class="btn btn-outline-secondary">
                ← Torna alla Dashboard
            </a>
        </div>

        <!-- Alert sponsor eliminato -->
        <?php if ($show_sponsor_deleted_alert): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Attenzione:</strong> Questo sponsor non è più disponibile.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
        </div>
        <?php endif; ?>

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
                               placeholder="Titolo, descrizione o influencer...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Categoria</label>
                        <select name="category" class="form-select">
                            <option value="">Tutte</option>
                            <?php
                            // CATEGORIE DINAMICHE DAL DATABASE
                            foreach ($active_categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>" 
                                    <?php echo $category_filter === $category['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
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
                    <div class="col-md-2">
                        <label class="form-label">Piattaforma</label>
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
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtra</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistiche -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $total_sponsors; ?></h5>
                        <p class="card-text">Sponsor Trovati</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo $total_sponsors; ?>
                        </h5>
                        <p class="card-text">Nuove Opportunità</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">0</h5>
                        <p class="card-text">In Negoziazione</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">0</h5>
                        <p class="card-text">Contatti Attivi</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista Sponsor -->
        <?php if (empty($sponsors)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <h4>Nessuno sponsor trovato</h4>
                    <p class="text-muted">
                        <?php echo $total_sponsors > 0 ? 'Prova a modificare i filtri di ricerca.' : 'Al momento non ci sono sponsor disponibili.'; ?>
                    </p>
                    <?php if (!empty($search) || !empty($category_filter)): ?>
                        <a href="list.php" class="btn btn-primary">Rimuovi Filtri</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($sponsors as $sponsor): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <!-- Immagine sponsor se disponibile -->
                            <?php if ($sponsor['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($sponsor['image_url']); ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($sponsor['title']); ?>"
                                     style="height: 180px; object-fit: cover;">
                            <?php endif; ?>
                            
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($sponsor['title']); ?></h6>
                            </div>
                            <div class="card-body">
                                <!-- Informazioni influencer -->
                                 <div class="d-flex align-items-center mb-3">
    <?php 
    // Determina se c'è un'immagine profilo
    $has_profile_image = !empty($sponsor['profile_image']);
    $profile_image_path = '';
    
    if ($has_profile_image) {
        $profile_image_path = $sponsor['profile_image'];
        
        // Correzione percorso immagine profilo
        if (strpos($profile_image_path, 'profiles/') === 0) {
            $profile_image_path = '/uploads/' . $profile_image_path;
        } elseif (strpos($profile_image_path, '/profiles/') === 0) {
            $profile_image_path = '/uploads' . $profile_image_path;
        } elseif (strpos($profile_image_path, '/') !== 0 && strpos($profile_image_path, 'http') !== 0) {
            $profile_image_path = '/uploads/profiles/' . $profile_image_path;
        }
    }
    ?>
    
    <!-- Contenitore immagine profilo - UN SOLO ELEMENTO -->
    <div class="me-2" style="width: 40px; height: 40px;">
        <?php if ($has_profile_image): ?>
            <!-- SOLO immagine profilo personalizzata -->
            <img src="<?php echo htmlspecialchars($profile_image_path); ?>" 
                 class="rounded-circle" 
                 width="40" 
                 height="40" 
                 alt="<?php echo htmlspecialchars($sponsor['influencer_name']); ?>"
                 style="object-fit: cover; width: 100%; height: 100%;"
                 onerror="this.style.display='none'; this.parentNode.innerHTML='<div class=\'rounded-circle bg-secondary d-flex align-items-center justify-content-center\' style=\'width: 40px; height: 40px;\'><i class=\'fas fa-user text-white\'></i></div>';">
        <?php else: ?>
            <!-- SOLO placeholder se non c'è immagine profilo -->
            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" 
                 style="width: 40px; height: 40px;">
                <i class="fas fa-user text-white"></i>
            </div>
        <?php endif; ?>
    </div>
    
    <div>
        <strong><?php echo htmlspecialchars($sponsor['influencer_name']); ?></strong><br>
        <small class="text-muted">
            <?php echo htmlspecialchars($sponsor['niche'] ?? 'N/A'); ?>
            <?php if ($sponsor['rating']): ?>
                • <i class="fas fa-star text-warning"></i> <?php echo number_format($sponsor['rating'], 1); ?>
            <?php endif; ?>
        </small>
    </div>
</div>
                                
                                <p class="card-text text-muted small mb-3">
                                    <?php echo strlen($sponsor['description']) > 100 ? 
                                        substr(htmlspecialchars($sponsor['description']), 0, 100) . '...' : 
                                        htmlspecialchars($sponsor['description']); ?>
                                </p>
                                
                                <div class="mb-2">
                                    <strong>Budget:</strong> 
                                    <?php echo number_format($sponsor['budget'], 0); ?> €
                                </div>
                                
                                <div class="mb-2">
                                    <strong>Categoria:</strong>
                                    <?php echo ucfirst(htmlspecialchars($sponsor['category'])); ?>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Social:</strong>
                                    <?php 
                                    $platforms = json_decode($sponsor['platforms'], true);
                                    if ($platforms): 
                                        foreach ($platforms as $platform): 
                                            // Mappatura slug -> icona Font Awesome
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
                                        <i class="<?php echo $icon; ?> me-1" title="<?php echo htmlspecialchars(ucfirst($platform)); ?>"></i>
                                    <?php 
                                        endforeach; 
                                    endif; 
                                    ?>
                                </div>
                                
                            </div>
                            <div class="card-footer">
                                <div class="d-flex flex-column gap-2">
                                    <!-- RIGA SUPERIORE: Pulsanti Dettagli sponsor e Preferiti -->
                                    <div class="d-flex gap-1">
                                        <!-- Pulsante Dettagli sponsor -->
                                        <a href="view.php?id=<?php echo $sponsor['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm flex-grow-1">
                                            Dettagli sponsor
                                        </a>
                                        
                                        <!-- NUOVO PULSANTE Preferiti (solo icona) -->
                                        <button type="button" 
                                                class="btn <?php echo isset($favorite_sponsors[$sponsor['id']]) ? 'btn-outline-danger' : 'btn-outline-secondary'; ?> btn-sm favorite-sponsor-btn"
                                                data-sponsor-id="<?php echo $sponsor['id']; ?>"
                                                data-is-favorite="<?php echo isset($favorite_sponsors[$sponsor['id']]) ? '1' : '0'; ?>"
                                                title="<?php echo isset($favorite_sponsors[$sponsor['id']]) ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'; ?>">
                                            <i class="<?php echo isset($favorite_sponsors[$sponsor['id']]) ? 'fas fa-heart text-danger' : 'far fa-heart text-secondary'; ?>"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- RIGA INFERIORE: Pulsante Contatta -->
                                    <a href="#" 
                                       class="btn btn-success btn-sm w-100"
                                       onclick="contactInfluencer(<?php echo $sponsor['id']; ?>, '<?php echo htmlspecialchars(addslashes($sponsor['influencer_name'])); ?>')">
                                        Contatta Influencer
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Paginazione -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Paginazione sponsor">
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
function contactInfluencer(sponsorId, influencerName) {
    // Qui puoi implementare la logica per contattare l'influencer
    // Per ora mostriamo un alert
    alert('Funzionalità "Contatta Influencer" per lo sponsor ID: ' + sponsorId + '\nInfluencer: ' + influencerName + '\n\nQuesta funzionalità sarà implementata in futuro per inviare messaggi agli influencer.');
    
    // In futuro, questa funzione potrebbe:
    // 1. Aprire un modal con un form per inviare un messaggio
    // 2. Reindirizzare a una pagina di messaggistica
    // 3. Inviare una richiesta AJAX per creare una conversazione
}

// Gestione Preferiti Sponsor con AJAX
document.addEventListener('DOMContentLoaded', function() {
    // Trova tutti i pulsanti preferiti sponsor
    const favoriteSponsorButtons = document.querySelectorAll('.favorite-sponsor-btn');
    
    favoriteSponsorButtons.forEach(button => {
        button.addEventListener('click', function() {
            const sponsorId = this.getAttribute('data-sponsor-id');
            const isFavorite = this.getAttribute('data-is-favorite') === '1';
            
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
                body: `sponsor_id=${sponsorId}&action=${isFavorite ? 'remove' : 'add'}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Aggiorna lo stato del pulsante
                    const isNowFavorite = data.is_favorite;
                    
                    this.setAttribute('data-is-favorite', isNowFavorite ? '1' : '0');
                    
                    if (isNowFavorite) {
                        // Aggiorna stile
                        this.classList.remove('btn-outline-secondary');
                        this.classList.add('btn-outline-danger');
                        this.title = 'Rimuovi dai preferiti';
                        const icon = this.querySelector('i');
                        if (icon) icon.className = 'fas fa-heart text-danger';
                    } else {
                        // Aggiorna stile
                        this.classList.remove('btn-outline-danger');
                        this.classList.add('btn-outline-secondary');
                        this.title = 'Aggiungi ai preferiti';
                        const icon = this.querySelector('i');
                        if (icon) icon.className = 'far fa-heart text-secondary';
                    }
                    
                    // Mostra notifica
                    showToast(isNowFavorite ? 'Sponsor aggiunto ai preferiti!' : 'Sponsor rimosso dai preferiti!', 'success');
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
/* Stili per i pulsanti preferiti sponsor */
.favorite-sponsor-btn.btn-outline-danger {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
}

.favorite-sponsor-btn.btn-outline-secondary {
    color: #6c757d !important;
    border-color: #6c757d !important;
}

.favorite-sponsor-btn:hover {
    transform: scale(1.05);
    transition: transform 0.2s ease-in-out;
}

/* RIMUOVI OVERLAY HOVER PER PULSANTI PREFERITI */
.favorite-sponsor-btn.btn-outline-danger:hover,
.favorite-sponsor-btn.btn-outline-secondary:hover {
    background-color: transparent !important;
}

/* Colori specifici per hover */
.favorite-sponsor-btn.btn-outline-danger:hover {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
}

.favorite-sponsor-btn.btn-outline-secondary:hover {
    color: #6c757d !important;
    border-color: #6c757d !important;
}

/* Stili per layout pulsanti */
.btn-outline-primary.btn-sm.flex-grow-1 {
    flex: 1 1 auto;
}

.btn-outline-danger.btn-sm,
.btn-outline-secondary.btn-sm {
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