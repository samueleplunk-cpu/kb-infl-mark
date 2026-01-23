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
    // Query base - CORRETTA: Aggiunto LEFT JOIN con categories
    $query = "
        SELECT s.*, 
               i.full_name as influencer_name,
               i.profile_image,
               i.niche,
               i.rating,
               c.name as category_display_name
        FROM sponsors s
        JOIN influencers i ON s.influencer_id = i.id
        JOIN users u ON i.user_id = u.id
        LEFT JOIN categories c ON s.category = c.slug
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
        LEFT JOIN categories c ON s.category = c.slug
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
        // MODIFICA: Cerca solo nel titolo dello sponsor
        $query .= " AND s.title LIKE ?";
        $count_query .= " AND s.title LIKE ?";
        $search_term = "%$search%";
        $params[] = $search_term;
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
// RECUPERO CONVERSAZIONI ESISTENTI PER GLI INFLUENCER DEGLI SPONSOR
// =============================================
$existing_conversations = [];
if ($brand && !empty($sponsors)) {
    // Estrai tutti gli ID influencer dagli sponsor
    $influencer_ids = array_column($sponsors, 'influencer_id');
    
    // Recupera tutte le conversazioni esistenti in una sola query
    try {
        if (!empty($influencer_ids)) {
            // Crea i placeholder per la query
            $placeholders = implode(',', array_fill(0, count($influencer_ids), '?'));
            
            $stmt = $pdo->prepare("
                SELECT influencer_id, id as conversation_id 
                FROM conversations 
                WHERE brand_id = ? 
                AND influencer_id IN ($placeholders)
                AND campaign_id IS NULL
            ");
            
            // Parametri: brand_id + tutti gli influencer_ids
            $params_conv = array_merge([$brand['id']], $influencer_ids);
            $stmt->execute($params_conv);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Trasforma in array associativo influencer_id => conversation_id
            foreach ($results as $row) {
                $existing_conversations[$row['influencer_id']] = $row['conversation_id'];
            }
        }
    } catch (PDOException $e) {
        error_log("Errore recupero conversazioni esistenti: " . $e->getMessage());
        // Continua senza conversazioni esistenti
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
                               placeholder="Titolo sponsor...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Categoria</label>
                        <select name="category" class="form-select">
                            <option value="">Tutte</option>
                            <?php
                            // CATEGORIE DINAMICHE DAL DATABASE
                            foreach ($active_categories as $category): 
                                // Calcola lo slug se non presente
                                $category_slug = $category['slug'] ?? '';
                                if (!$category_slug && isset($category['name'])) {
                                    // Crea slug dal nome (come fa il database)
                                    $category_slug = strtolower($category['name']);
                                    $category_slug = str_replace(' & ', '-', $category_slug);
                                    $category_slug = preg_replace('/[^a-z0-9-]/', '-', $category_slug);
                                    $category_slug = preg_replace('/-+/', '-', $category_slug);
                                }
                            ?>
                                <option value="<?php echo htmlspecialchars($category_slug); ?>" 
                                    <?php echo $category_filter === $category_slug ? 'selected' : ''; ?>>
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
                    <div class="col-md-2 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-50">Cerca</button>
                        <a href="list.php" class="btn btn-outline-secondary w-50">Reset</a>
                    </div>
                </form>
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
                                        <div class="d-flex align-items-center">
                                            <strong class="me-2"><?php echo htmlspecialchars($sponsor['influencer_name']); ?></strong>
                                            <?php if ($sponsor['rating']): ?>
                                                <small class="text-muted">
                                                    • <i class="fas fa-star text-warning"></i> <?php echo number_format($sponsor['rating'], 1); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
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
                                
                                <!-- MODIFICA: Visualizzazione corretta della categoria -->
                                <div class="mb-2">
                                    <strong>Categoria:</strong>
                                    <?php 
                                    // SOLUZIONE DEFINITIVA:
                                    // 1. Usa category_display_name se disponibile dal join
                                    // 2. Altrimenti trasforma lo slug in nome leggibile
                                    // 3. Usa htmlspecialchars per sicurezza
                                    
                                    if (!empty($sponsor['category_display_name'])) {
                                        // Usa il nome dalla tabella categories (es: "Beauty & Makeup")
                                        echo htmlspecialchars($sponsor['category_display_name']);
                                    } else {
                                        // Fallback: trasforma lo slug in nome
                                        $category_name = str_replace('-', ' & ', $sponsor['category']);
                                        echo htmlspecialchars(ucwords(strtolower($category_name)));
                                    }
                                    ?>
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
                                    
                                    <!-- RIGA INFERIORE: Pulsanti Conversazione -->
                                    <?php
                                    // Controlla se esiste già una conversazione con questo influencer
                                    $conversation_id = $existing_conversations[$sponsor['influencer_id']] ?? false;
                                    ?>
                                    
                                    <?php if (!$conversation_id): ?>
                                        <!-- Se NON esiste conversazione: mostra pulsante per inviare messaggio -->
                                        <button type="button" 
                                                class="btn btn-primary btn-sm w-100 send-message-btn"
                                                data-influencer-id="<?php echo $sponsor['influencer_id']; ?>"
                                                data-influencer-name="<?php echo htmlspecialchars($sponsor['influencer_name']); ?>"
                                                data-sponsor-id="<?php echo $sponsor['id']; ?>"
                                                data-sponsor-title="<?php echo htmlspecialchars($sponsor['title']); ?>">
                                            <i class="fas fa-envelope"></i> Invia Messaggio
                                        </button>
                                        
                                        <!-- Form fallback per no-JavaScript (nascosto) -->
                                        <form method="POST" action="../start-conversation.php" class="d-none no-js-form">
                                            <input type="hidden" name="influencer_id" value="<?php echo $sponsor['influencer_id']; ?>">
                                            <input type="hidden" name="sponsor_id" value="<?php echo $sponsor['id']; ?>">
                                            <input type="hidden" name="initial_message" value="Ciao <?php echo htmlspecialchars($sponsor['influencer_name']); ?>, sono interessato al tuo sponsor '<?php echo htmlspecialchars($sponsor['title']); ?>'!">
                                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                                <i class="fas fa-envelope"></i> Invia Messaggio
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <!-- Se ESISTE conversazione: mostra pulsanti per andare alla conversazione o nuovo messaggio -->
                                        <div class="d-flex gap-1">
                                            <a href="../messages/conversation.php?id=<?php echo $conversation_id; ?>" 
                                               class="btn btn-primary btn-sm flex-grow-1">
                                                <i class="fas fa-comments"></i> Vai alla Conversazione
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-outline-primary btn-sm send-message-btn"
                                                    data-influencer-id="<?php echo $sponsor['influencer_id']; ?>"
                                                    data-influencer-name="<?php echo htmlspecialchars($sponsor['influencer_name']); ?>"
                                                    data-sponsor-id="<?php echo $sponsor['id']; ?>"
                                                    data-sponsor-title="<?php echo htmlspecialchars($sponsor['title']); ?>"
                                                    data-conversation-id="<?php echo $conversation_id; ?>"
                                                    title="Aggiungi nuovo messaggio">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Form fallback per no-JavaScript (nascosto) -->
                                        <form method="POST" action="../start-conversation.php" class="d-none no-js-form">
                                            <input type="hidden" name="influencer_id" value="<?php echo $sponsor['influencer_id']; ?>">
                                            <input type="hidden" name="sponsor_id" value="<?php echo $sponsor['id']; ?>">
                                            <input type="hidden" name="initial_message" value="Ciao <?php echo htmlspecialchars($sponsor['influencer_name']); ?>, vorrei aggiungere qualcosa alla nostra conversazione sullo sponsor '<?php echo htmlspecialchars($sponsor['title']); ?>'">
                                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                                <i class="fas fa-envelope"></i> Invia Messaggio
                                            </button>
                                        </form>
                                    <?php endif; ?>
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

<!-- MODAL PER MESSAGGIO PERSONALIZZATO -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="messageForm" method="POST" action="../start-conversation.php">
                <input type="hidden" name="influencer_id" id="modalInfluencerId">
                <input type="hidden" name="sponsor_id" id="modalSponsorId">
                <input type="hidden" name="initial_message" id="modalInitialMessage">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="messageModalLabel">Invia Messaggio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="customMessage" class="form-label">
                            Scrivi il tuo messaggio:
                        </label>
                        <textarea class="form-control" 
                                  id="customMessage" 
                                  name="custom_message" 
                                  rows="6" 
                                  maxlength="1000" 
                                  placeholder="Es: Ciao, sono interessato al tuo sponsor '[Titolo Sponsor]'. Vorrei discutere una possibile collaborazione..."
                                  required></textarea>
                        <div class="d-flex justify-content-end mt-2">
                            <span class="text-muted small">
                                <span id="charCount">0</span>/1000 caratteri
                            </span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Annulla
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Invia Messaggio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Nascondi form fallback se JavaScript è abilitato
document.addEventListener('DOMContentLoaded', function() {
    // Nascondi form fallback se JavaScript è abilitato
    document.querySelectorAll('.no-js-form').forEach(form => {
        form.style.display = 'none';
    });
    
    // Mostra i pulsanti per il modal
    document.querySelectorAll('.send-message-btn').forEach(btn => {
        btn.style.display = btn.classList.contains('send-message-btn') ? '' : 'none';
    });
    
    // Gestione click sui pulsanti "Invia Messaggio" e "Nuovo Messaggio"
    document.querySelectorAll('.send-message-btn').forEach(button => {
        button.addEventListener('click', function() {
            const influencerId = this.getAttribute('data-influencer-id');
            const influencerName = this.getAttribute('data-influencer-name');
            const sponsorId = this.getAttribute('data-sponsor-id');
            const sponsorTitle = this.getAttribute('data-sponsor-title');
            const conversationId = this.getAttribute('data-conversation-id');
            
            // Imposta i valori nel modal
            document.getElementById('modalInfluencerId').value = influencerId;
            document.getElementById('modalSponsorId').value = sponsorId;
            
            // Imposta messaggio predefinito personalizzato
            const defaultMessage = conversationId 
                ? `Ciao ${influencerName}, vorrei aggiungere qualcosa alla nostra conversazione sullo sponsor '${sponsorTitle}': `
                : `Ciao ${influencerName}, sono interessato al tuo sponsor '${sponsorTitle}' e vorrei discutere una possibile collaborazione!`;
            
            document.getElementById('customMessage').value = defaultMessage;
            document.getElementById('modalInitialMessage').value = defaultMessage;
            
            // Se esiste conversazione, aggiorna il titolo del modal
            if (conversationId) {
                document.getElementById('messageModalLabel').textContent = 'Aggiungi Nuovo Messaggio';
                // Aggiungi campo nascosto per conversation_id (se necessario per il backend)
                if (!document.getElementById('existingConversationId')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.id = 'existingConversationId';
                    input.name = 'existing_conversation_id';
                    input.value = conversationId;
                    document.getElementById('messageForm').appendChild(input);
                } else {
                    document.getElementById('existingConversationId').value = conversationId;
                }
            } else {
                document.getElementById('messageModalLabel').textContent = 'Invia Messaggio';
                // Rimuovi campo hidden se presente
                const existingInput = document.getElementById('existingConversationId');
                if (existingInput) {
                    existingInput.remove();
                }
            }
            
            // Resetta e aggiorna contatore caratteri
            updateCharCount();
            
            // Mostra il modal
            const messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
            messageModal.show();
            
            // Focus sul textarea
            setTimeout(() => {
                document.getElementById('customMessage').focus();
            }, 500);
        });
    });
    
    // Gestione contatore caratteri
    const textarea = document.getElementById('customMessage');
    const charCount = document.getElementById('charCount');
    
    function updateCharCount() {
        if (!textarea || !charCount) return;
        
        const length = textarea.value.length;
        charCount.textContent = length;
        
        // Cambia colore se supera 900 caratteri
        if (length > 900) {
            charCount.className = 'text-warning';
        } else if (length > 990) {
            charCount.className = 'text-danger';
        } else {
            charCount.className = '';
        }
    }
    
    if (textarea) {
        textarea.addEventListener('input', updateCharCount);
        
        // Aggiorna il messaggio nascosto quando l'utente modifica il textarea
        textarea.addEventListener('input', function() {
            document.getElementById('modalInitialMessage').value = this.value;
        });
        
        // Inizializza contatore
        updateCharCount();
    }
    
    // Validazione del form nel modal
    const messageForm = document.getElementById('messageForm');
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            const message = document.getElementById('customMessage')?.value.trim();
            
            if (!message) {
                e.preventDefault();
                alert('Per favore, scrivi un messaggio prima di inviare.');
                document.getElementById('customMessage')?.focus();
                return false;
            }
            
            if (message.length > 1000) {
                e.preventDefault();
                alert('Il messaggio è troppo lungo (max 1000 caratteri).');
                return false;
            }
            
            // Mostra indicatore di caricamento
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Invio in corso...';
                submitBtn.disabled = true;
            }
        });
    }
    
    // Reset modal quando viene nascosto
    document.getElementById('messageModal')?.addEventListener('hidden.bs.modal', function () {
        // Ripristina titolo default
        document.getElementById('messageModalLabel').textContent = 'Invia Messaggio';
        
        // Rimuovi campo hidden se presente
        const existingInput = document.getElementById('existingConversationId');
        if (existingInput) {
            existingInput.remove();
        }
        
        // Resetta form
        const form = document.getElementById('messageForm');
        if (form) {
            form.reset();
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Invia Messaggio';
                submitBtn.disabled = false;
            }
        }
    });
    
    // Gestione Preferiti Sponsor con AJAX
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

/* Stili per il modal e textarea */
#customMessage {
    resize: vertical;
    min-height: 120px;
}

#charCount.text-warning {
    font-weight: bold;
}

#charCount.text-danger {
    font-weight: bold;
}

/* Pulsante nel modal */
.modal-footer .btn {
    min-width: 100px;
}

/* Adatta form no-JS */
.no-js-form {
    margin-top: 5px;
}

/* Stili per pulsanti conversazione esistente */
.btn-primary.flex-grow-1 {
    flex: 1 1 auto;
}

.btn-outline-primary.btn-sm {
    width: 40px;
    padding-left: 0.25rem;
    padding-right: 0.25rem;
    transition: all 0.2s ease-in-out;
}

/* Effetto hover che corrisponde esattamente a quello della pagina search-influencers.php */
.btn-outline-primary.btn-sm:hover {
    background-color: #0d6efd !important;
    color: white !important;
    border-color: #0d6efd !important;
    transform: scale(1.05);
}

/* Tooltip personalizzato */
[title] {
    cursor: help;
}

.toast {
    min-width: 250px;
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