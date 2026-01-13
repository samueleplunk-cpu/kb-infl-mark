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

// Verifica che l'utente sia un brand
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    die("Accesso negato: Questa area è riservata ai brand.");
}

// =============================================
// INCLUSIONE FUNZIONI SOCIAL NETWORK E CATEGORIE
// =============================================
require_once dirname(__DIR__) . '/includes/social_network_functions.php';
require_once dirname(__DIR__) . '/includes/category_functions.php';

// =============================================
// INCLUSIONE HEADER CON PERCORSO ASSOLUTO
// =============================================
$header_file = dirname(__DIR__) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;

// =============================================
// RECUPERO BRAND_ID PER MESSAGGI
// =============================================
$brand_id = null;
$stmt_brand = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
$stmt_brand->execute([$_SESSION['user_id']]);
$brand_data = $stmt_brand->fetch(PDO::FETCH_ASSOC);
if ($brand_data) {
    $brand_id = $brand_data['id'];
}

// =============================================
// RECUPERO CATEGORIE ATTIVE PER FILTRO
// =============================================
$active_categories = get_active_categories($pdo);

// =============================================
// RECUPERO TUTTI I SOCIAL NETWORK CONFIGURATI
// =============================================
$all_social_networks = get_active_social_networks();

// =============================================
// RECUPERO BUDGET MASSIMO DINAMICO
// =============================================
$max_budget = 1000; // Valore di default
try {
    $stmt_max = $pdo->query("SELECT MAX(rate) as max_rate FROM influencers WHERE rate IS NOT NULL AND rate > 0");
    $result = $stmt_max->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['max_rate'] > 0) {
        // Arrotonda al multiplo di 10 superiore per un migliore utilizzo dello slider
        $max_budget = ceil($result['max_rate'] / 10) * 10;
        
        // Assicuriamoci che il minimo sia 1000 per mantenere una buona esperienza utente
        if ($max_budget < 1000) {
            $max_budget = 1000;
        }
    }
} catch (PDOException $e) {
    error_log("Errore recupero budget massimo: " . $e->getMessage());
    // Utilizza il valore di default in caso di errore
}

// =============================================
// PARAMETRI DI RICERCA E FILTRI
// =============================================
$search_query = $_GET['search'] ?? '';
$niche_filter = $_GET['niche'] ?? '';
$platform_filter = $_GET['platform'] ?? '';
$min_rate = $_GET['min_rate'] ?? '';
$max_rate = $_GET['max_rate'] ?? '';
$budget_filter = $_GET['budget'] ?? '';
$min_rating = $_GET['min_rating'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; // Risultati per pagina
$offset = ($page - 1) * $limit;

// =============================================
// COSTRUZIONE QUERY DINAMICA
// =============================================
$where_conditions = [];
$params = [];

// Filtro ricerca per nome/handle - MODIFICATO PER INCLUIRE TUTTE LE PIATTAFORME
if (!empty($search_query)) {
    // Inizia con il campo full_name
    $search_conditions = ["full_name LIKE ?"];
    $search_param = "%$search_query%";
    $params[] = $search_param;
    
    // Aggiungi condizioni per tutti gli handle dei social network configurati
    foreach ($all_social_networks as $social) {
        $column_name = $social['slug'] . '_handle';
        $search_conditions[] = "$column_name LIKE ?";
        $params[] = $search_param;
    }
    
    // Combina tutte le condizioni con OR
    $where_conditions[] = "(" . implode(" OR ", $search_conditions) . ")";
}

// Filtro per categorie - DINAMICO DAL DATABASE
if (!empty($niche_filter)) {
    // Crea una mappatura nome categoria -> slug per compatibilità
    $category_mapping = [];
    foreach ($active_categories as $category) {
        $category_mapping[$category['name']] = $category['slug'];
    }
    
    // Se la categoria selezionata esiste, usa lo slug corrispondente
    if (isset($category_mapping[$niche_filter])) {
        $where_conditions[] = "niche = ?";
        $params[] = $category_mapping[$niche_filter];
    }
}

// Filtro per piattaforma
if (!empty($platform_filter)) {
    $platform_exists = false;
    
    // Verifica che la piattaforma selezionata esista tra quelle attive
    foreach ($all_social_networks as $social) {
        if ($social['slug'] === $platform_filter) {
            $platform_exists = true;
            break;
        }
    }
    
    if ($platform_exists) {
        $where_conditions[] = "{$platform_filter}_handle IS NOT NULL AND {$platform_filter}_handle != ''";
    }
}

// GESTIONE RETROCOMPATIBILITÀ: supporto sia min_rate/max_rate che budget
if (!empty($budget_filter) && is_numeric($budget_filter)) {
    // Nuovo filtro: budget massimo (rate ≤ budget)
    $where_conditions[] = "rate <= ?";
    $params[] = $budget_filter;
} else {
    // Mantenimento compatibilità con i vecchi filtri
    if (!empty($min_rate) && is_numeric($min_rate)) {
        $where_conditions[] = "rate >= ?";
        $params[] = $min_rate;
    }
    
    if (!empty($max_rate) && is_numeric($max_rate)) {
        $where_conditions[] = "rate <= ?";
        $params[] = $max_rate;
    }
}

// Filtro per rating minimo - MODIFICATO PER INTERVALLI PRECISI
if (!empty($min_rating) && is_numeric($min_rating)) {
    // Calcola i limiti dell'intervallo in base al valore selezionato
    $rating_min = (float)$min_rating;
    
    if ($rating_min == 5) {
        // Per 5 stelle: rating ≥ 5.0
        $where_conditions[] = "rating >= ?";
        $params[] = $rating_min;
    } else {
        // Per altri valori: rating ≥ X AND rating < X+1
        $rating_max = $rating_min + 1;
        $where_conditions[] = "rating >= ? AND rating < ?";
        $params[] = $rating_min;
        $params[] = $rating_max;
    }
}

// Query base
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Query per il conteggio totale (per paginazione)
$count_sql = "SELECT COUNT(*) as total FROM influencers $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_results = $count_stmt->fetchColumn();
$total_pages = ceil($total_results / $limit);

// Query per i risultati con ordinamento casuale
$results_sql = "SELECT * FROM influencers $where_sql ORDER BY RAND() LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($results_sql);
$stmt->execute($params);
$influencers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// RECUPERO CONVERSAZIONI ESISTENTI PER TUTTI GLI INFLUENCER
// =============================================
$existing_conversations = [];
if ($brand_id && !empty($influencers)) {
    // Estrai tutti gli ID influencer dai risultati
    $influencer_ids = array_column($influencers, 'id');
    
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
            $params_conv = array_merge([$brand_id], $influencer_ids);
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
// RECUPERO INFLUENCER PREFERITI PER IL BRAND
// =============================================
$favorite_influencers = [];
if ($brand_id && !empty($influencers)) {
    // Estrai tutti gli ID influencer dai risultati
    $influencer_ids = array_column($influencers, 'id');
    
    // Recupera tutti gli influencer preferiti in una sola query
    try {
        if (!empty($influencer_ids)) {
            // Crea i placeholder per la query
            $placeholders = implode(',', array_fill(0, count($influencer_ids), '?'));
            
            $stmt = $pdo->prepare("
                SELECT influencer_id 
                FROM favorite_influencers 
                WHERE brand_id = ? 
                AND influencer_id IN ($placeholders)
            ");
            
            // Parametri: brand_id + tutti gli influencer_ids
            $params_fav = array_merge([$brand_id], $influencer_ids);
            $stmt->execute($params_fav);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Trasforma in array associativo influencer_id => true
            foreach ($results as $row) {
                $favorite_influencers[$row['influencer_id']] = true;
            }
        }
    } catch (PDOException $e) {
        error_log("Errore recupero influencer preferiti: " . $e->getMessage());
        // Continua senza preferiti
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Cerca Influencer</h2>
            <a href="dashboard.php" class="btn btn-outline-primary">
                ← Torna alla Dashboard
            </a>
        </div>

        <!-- FILTRI DI RICERCA -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="card-title mb-0">Filtri di ricerca</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="mb-3"></div>
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Cerca per nome / Account social</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search_query); ?>" 
                           placeholder="Nome o username social...">
                </div>

                <div class="col-md-3">
                    <label for="niche" class="form-label">Categoria</label>
                    <select class="form-select" id="niche" name="niche">
                        <option value="">Tutte le categorie</option>
                        <?php foreach ($active_categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['name']); ?>" 
                                <?php echo $niche_filter === $category['name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="platform" class="form-label">Social Network</label>
                    <select class="form-select" id="platform" name="platform">
                        <option value="">Tutti i social</option>
                        <?php foreach ($all_social_networks as $social): ?>
                            <option value="<?php echo $social['slug']; ?>" 
                                <?php echo $platform_filter === $social['slug'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($social['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="min_rating" class="form-label">Rating</label>
                    <select class="form-select" id="min_rating" name="min_rating">
                        <option value="">Qualsiasi</option>
                        <option value="5" <?php echo $min_rating === '5' ? 'selected' : ''; ?>>5 stelle</option>
                        <option value="4" <?php echo $min_rating === '4' ? 'selected' : ''; ?>>4 stelle</option>
                        <option value="3" <?php echo $min_rating === '3' ? 'selected' : ''; ?>>3 stelle</option>
                        <option value="2" <?php echo $min_rating === '2' ? 'selected' : ''; ?>>2 stelle</option>
                        <option value="1" <?php echo $min_rating === '1' ? 'selected' : ''; ?>>1 stella</option>
                        <option value="0" <?php echo $min_rating === '0' ? 'selected' : ''; ?>>0 stelle</option>
                    </select>
                </div>
            </div>

            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="budget" class="form-label d-flex justify-content-between">
                            <span>Budget massimo:</span>
                            <span id="budget-value" class="text-primary fw-bold">
                                €<?php echo !empty($budget_filter) && is_numeric($budget_filter) ? $budget_filter : '0'; ?>
                            </span>
                        </label>
                        <input type="range" class="form-range" id="budget" name="budget" 
                               min="0" max="<?php echo $max_budget; ?>" step="10"
                               value="<?php echo !empty($budget_filter) && is_numeric($budget_filter) ? $budget_filter : '0'; ?>"
                               style="width: 100%;">
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">€0</small>
                            <small class="text-muted">€<?php echo $max_budget; ?></small>
                        </div>
                        <input type="hidden" name="budget" value="<?php echo !empty($budget_filter) && is_numeric($budget_filter) ? $budget_filter : '0'; ?>">
                    </div>
                </div>

                <div class="col-md-9">
                    <div class="d-flex gap-2 justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Cerca
                        </button>
                        <a href="search-influencers.php" class="btn btn-outline-secondary">
                            Reset Filtri
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="mt-3"></div>
        </form>
    </div>
</div>

        <!-- RISULTATI RICERCA -->
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    Risultati (<?php echo $total_results; ?> influencer trovati)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($influencers)): ?>
                    <div class="text-center py-5">
                        <h4 class="text-muted">Nessun influencer trovato</h4>
                        <p class="text-muted">Prova a modificare i filtri di ricerca</p>
                    </div>
                <?php else: ?>
                    <!-- GRIGLIA INFLUENCER -->
                    <div class="row">
                        <?php foreach ($influencers as $influencer): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 influencer-card">
                                    <!-- Immagine Profilo -->
                                    <div class="position-relative">
                                        <?php 
                                        // Costruisci il percorso dell'immagine
                                        $profile_image_path = '';
                                        if (!empty($influencer['profile_image'])) {
                                            // Se l'influencer ha un'immagine personalizzata
                                            $profile_image_path = '/uploads/' . htmlspecialchars($influencer['profile_image']);
                                        } else {
                                            // Se l'influencer NON ha un'immagine personalizzata, usa il placeholder
                                            $profile_image_path = '/uploads/placeholder/sponsor_influencer_dashboard.png';
                                        }
                                        ?>
                                        <img src="<?php echo $profile_image_path; ?>" 
                                             class="card-img-top" 
                                             alt="<?php echo htmlspecialchars($influencer['full_name']); ?>"
                                             style="height: 200px; object-fit: cover;">
                                        
                                        <!-- Badge Rating -->
                                        <?php if (!empty($influencer['rating'])): ?>
                                            <div class="position-absolute top-0 end-0 m-2">
                                                <span class="badge bg-warning text-dark">
                                                    ★ <?php echo number_format($influencer['rating'], 1); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="card-body">
                                        <!-- Nome e Categoria -->
                                        <h5 class="card-title"><?php echo htmlspecialchars($influencer['full_name']); ?></h5>
                                        <?php if (!empty($influencer['niche'])): ?>
                                            <?php
                                            // Crea una mappatura slug -> nome per il display
                                            $slug_to_name_mapping = [];
                                            foreach ($active_categories as $category) {
                                                $slug_to_name_mapping[$category['slug']] = $category['name'];
                                            }
                                            
                                            $original_niche = $influencer['niche'];
                                            $display_niche = $slug_to_name_mapping[$original_niche] ?? $original_niche;
                                            ?>
                                            <span class="badge bg-info mb-2"><?php echo htmlspecialchars($display_niche); ?></span>
                                        <?php endif; ?>

                                        <!-- Bio -->
                                        <?php if (!empty($influencer['bio'])): ?>
                                            <p class="card-text text-muted small">
                                                <?php 
                                                $bio = htmlspecialchars($influencer['bio']);
                                                echo strlen($bio) > 100 ? substr($bio, 0, 100) . '...' : $bio;
                                                ?>
                                            </p>
                                        <?php endif; ?>

                                        <!-- Aggiungi Nazionalità -->
                                        <?php if (!empty($influencer['nationality'])): ?>
                                            <div class="mt-2">
                                                <small class="text-muted d-block">
                                                    <strong>Nazionalità:</strong> 
                                                    <?php echo htmlspecialchars($influencer['nationality']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Handles Social (rimosso simbolo @) -->
                                        <div class="mb-2">
                                            <?php
                                            // Usa la variabile globale per i social network
                                            foreach ($all_social_networks as $social): 
                                                $column_name = $social['slug'] . '_handle';
                                                $handle_value = $influencer[$column_name] ?? '';
                                                if (!empty($handle_value)): 
                                                    
                                                    // Applica formato abbreviato (K, M) se numerico
                                                    $display_value = htmlspecialchars($handle_value);
                                                    
                                                    // Verifica se l'handle è numerico (follower count)
                                                    if (is_numeric($handle_value) && $handle_value > 0) {
                                                        // Usa la funzione format_number da includes/functions.php
                                                        if (function_exists('format_number')) {
                                                            $display_value = format_number($handle_value);
                                                        } else {
                                                            // Fallback se la funzione non esiste
                                                            $num = (int)$handle_value;
                                                            if ($num >= 1000000) {
                                                                $display_value = round($num / 1000000, 1) . 'M';
                                                            } elseif ($num >= 1000) {
                                                                $display_value = round($num / 1000, 1) . 'K';
                                                            }
                                                        }
                                                    }
                                            ?>
                                                <small class="text-muted d-block">
                                                    <i class="<?php echo $social['icon']; ?> me-1"></i>
                                                    <strong><?php echo $social['name']; ?>:</strong> 
                                                    <?php echo $display_value; // Rimosso simbolo @ ?>
                                                </small>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>

                                        <!-- Tariffa (visualizzazioni rimosse) -->
                                        <?php if (!empty($influencer['rate'])): ?>
                                            <div class="mt-2">
                                                <strong class="text-success">
                                                    €<?php echo number_format($influencer['rate'], 2); ?>
                                                </strong>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                     <!-- PULSANTI AZIONE -->
                                    <div class="card-footer bg-transparent">
                                        <div class="d-flex flex-column gap-2">
                                            <?php if ($brand_id): ?>
                                                <!-- RIGA SUPERIORE: Pulsanti Dettagli Profilo e Preferiti -->
                                                <div class="d-flex gap-1">
                                                    <!-- Pulsante Dettagli Profilo -->
                                                    <a href="/influencers/profile.php?id=<?php echo $influencer['id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm flex-grow-1">
                                                        <i class="fas fa-eye"></i> Dettagli profilo
                                                    </a>
                                                    
                                                    <!-- Pulsante Preferiti (solo icona) -->
                                                    <button type="button" 
                                                            class="btn <?php echo isset($favorite_influencers[$influencer['id']]) ? 'btn-outline-danger' : 'btn-outline-secondary'; ?> btn-sm favorite-btn"
                                                            data-influencer-id="<?php echo $influencer['id']; ?>"
                                                            data-is-favorite="<?php echo isset($favorite_influencers[$influencer['id']]) ? '1' : '0'; ?>"
                                                            title="<?php echo isset($favorite_influencers[$influencer['id']]) ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'; ?>">
                                                        <i class="<?php echo isset($favorite_influencers[$influencer['id']]) ? 'fas fa-heart text-danger' : 'far fa-heart text-secondary'; ?>"></i>
                                                    </button>
                                                </div>
                                                
                                                <?php 
                                                // Controlla se esiste già una conversazione con questo influencer
                                                $conversation_id = $existing_conversations[$influencer['id']] ?? false;
                                                ?>
                                                
                                                <!-- RIGA INFERIORE: Pulsanti Conversazione -->
                                                <?php if (!$conversation_id): ?>
                                                    <!-- Se NON esiste conversazione: mostra pulsante per inviare messaggio -->
                                                    <button type="button" 
                                                            class="btn btn-primary btn-sm w-100 send-message-btn"
                                                            data-influencer-id="<?php echo $influencer['id']; ?>"
                                                            data-influencer-name="<?php echo htmlspecialchars($influencer['full_name']); ?>">
                                                        <i class="fas fa-envelope"></i> Invia Messaggio
                                                    </button>
                                                    
                                                    <!-- Form fallback per no-JavaScript (nascosto) -->
                                                    <form method="POST" action="start-conversation.php" class="d-none no-js-form">
                                                        <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                        <input type="hidden" name="initial_message" value="Ciao <?php echo htmlspecialchars($influencer['full_name']); ?>, sono interessato a collaborare con te!">
                                                        <button type="submit" class="btn btn-primary btn-sm w-100">
                                                            <i class="fas fa-envelope"></i> Invia Messaggio
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <!-- Se ESISTE conversazione: mostra pulsanti per andare alla conversazione o nuovo messaggio -->
                                                    <div class="d-flex gap-1">
                                                        <a href="messages/conversation.php?id=<?php echo $conversation_id; ?>" 
                                                           class="btn btn-primary btn-sm flex-grow-1">
                                                            <i class="fas fa-comments"></i> Vai alla Conversazione
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-outline-primary btn-sm send-message-btn"
                                                                data-influencer-id="<?php echo $influencer['id']; ?>"
                                                                data-influencer-name="<?php echo htmlspecialchars($influencer['full_name']); ?>"
                                                                data-conversation-id="<?php echo $conversation_id; ?>"
                                                                title="Aggiungi nuovo messaggio">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Form fallback per no-JavaScript (nascosto) -->
                                                    <form method="POST" action="start-conversation.php" class="d-none no-js-form">
                                                        <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                        <input type="hidden" name="initial_message" value="Ciao <?php echo htmlspecialchars($influencer['full_name']); ?>, sono interessato a collaborare con te!">
                                                        <button type="submit" class="btn btn-primary btn-sm w-100">
                                                            <i class="fas fa-envelope"></i> Invia Messaggio
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- Se non c'è brand_id -->
                                                <div class="d-flex gap-1">
                                                    <!-- Pulsante Dettagli Profilo -->
                                                    <a href="/influencers/profile.php?id=<?php echo $influencer['id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm flex-grow-1">
                                                        <i class="fas fa-eye"></i> Dettagli profilo
                                                    </a>
                                                    
                                                    <!-- Pulsante Preferiti disabilitato -->
                                                    <button class="btn btn-outline-secondary btn-sm" disabled title="Completa il profilo brand per aggiungere ai preferiti">
                                                        <i class="far fa-heart text-secondary"></i>
                                                    </button>
                                                </div>
                                                
                                                <button class="btn btn-secondary btn-sm w-100" disabled title="Completa il profilo brand per inviare messaggi">
                                                    <i class="fas fa-exclamation-circle"></i> Completa Profilo
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- PAGINAZIONE -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Paginazione risultati">
                            <ul class="pagination justify-content-center mt-4">
                                <!-- Pagina Precedente -->
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i> Precedente
                                    </a>
                                </li>

                                <!-- Pagine -->
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" 
                                           href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Pagina Successiva -->
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        Successiva <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PER MESSAGGIO PERSONALIZZATO -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="messageForm" method="POST" action="start-conversation.php">
                <input type="hidden" name="influencer_id" id="modalInfluencerId">
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
                                  placeholder="Es: Ciao, sono [Nome Brand]. Ho visto il tuo profilo e mi piacerebbe collaborare per una campagna su [tema]..."
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

<style>
.influencer-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.influencer-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.card-img-top {
    border-bottom: 1px solid #dee2e6;
}

.btn-sm {
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
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
}

/* Badge per indicare conversazione esistente */
.conversation-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    font-size: 0.7rem;
}

/* Tooltip personalizzato */
[title] {
    cursor: help;
}

/* Stili per i pulsanti preferiti */
.favorite-btn.btn-danger {
    color: white !important;
}

.favorite-btn.btn-outline-danger {
    color: #dc3545 !important;
}

.favorite-btn:hover {
    transform: scale(1.05);
    transition: transform 0.2s ease-in-out;
}

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

/* RIMUOVI OVERLAY HOVER PER PULSANTI PREFERITI */
.favorite-btn.btn-outline-danger:hover,
.favorite-btn.btn-outline-secondary:hover {
    background-color: transparent !important;
}

/* Colori specifici per hover */
.favorite-btn.btn-outline-danger:hover {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
}

.favorite-btn.btn-outline-secondary:hover {
    color: #6c757d !important;
    border-color: #6c757d !important;
}
</style>

<script>
// JavaScript per aggiornare il valore dello slider in tempo reale
document.addEventListener('DOMContentLoaded', function() {
    const budgetSlider = document.getElementById('budget');
    const budgetValue = document.getElementById('budget-value');
    const budgetHiddenInput = document.querySelector('input[name="budget"][type="hidden"]');
    
    if (budgetSlider && budgetValue) {
        // Aggiorna il valore visualizzato quando lo slider viene spostato
        budgetSlider.addEventListener('input', function() {
            const value = this.value;
            budgetValue.textContent = '€' + value;
            budgetHiddenInput.value = value;
        });
        
        // Aggiorna anche quando cambia (per compatibilità)
        budgetSlider.addEventListener('change', function() {
            const value = this.value;
            budgetValue.textContent = '€' + value;
            budgetHiddenInput.value = value;
        });
        
        // Inizializza il valore visualizzato
        budgetValue.textContent = '€' + budgetSlider.value;
        budgetHiddenInput.value = budgetSlider.value;
    }

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
            const conversationId = this.getAttribute('data-conversation-id');
            
            // Imposta i valori nel modal
            document.getElementById('modalInfluencerId').value = influencerId;
            
            // Imposta messaggio predefinito personalizzato
            const defaultMessage = conversationId 
                ? `Ciao ${influencerName}, vorrei aggiungere qualcosa alla nostra conversazione: `
                : `Ciao ${influencerName}, sono interessato a collaborare con te!`;
            
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
    
     // Gestione Preferiti con AJAX
    document.querySelectorAll('.favorite-btn').forEach(button => {
        button.addEventListener('click', function() {
            const influencerId = this.getAttribute('data-influencer-id');
            const isFavorite = this.getAttribute('data-is-favorite') === '1';
            
            // Disabilita il pulsante durante la richiesta
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            // Invia richiesta AJAX
            fetch('toggle-favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `influencer_id=${influencerId}&action=${isFavorite ? 'remove' : 'add'}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Aggiorna lo stato del pulsante
                    const isNowFavorite = data.is_favorite;
                    
                    this.setAttribute('data-is-favorite', isNowFavorite ? '1' : '0');
                    
                    if (isNowFavorite) {
                        this.innerHTML = '<i class="fas fa-heart text-danger"></i>';
                        this.classList.remove('btn-outline-secondary');
                        this.classList.add('btn-outline-danger');
                        this.title = 'Rimuovi dai preferiti';
                    } else {
                        this.innerHTML = '<i class="far fa-heart text-secondary"></i>';
                        this.classList.remove('btn-outline-danger');
                        this.classList.add('btn-outline-secondary');
                        this.title = 'Aggiungi ai preferiti';
                    }
                    
                    // Mostra notifica
                    showToast(isNowFavorite ? 'Aggiunto ai preferiti!' : 'Rimosso dai preferiti!', 'success');
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

<?php
// =============================================
// INCLUSIONE FOOTER CON PERCORSO ASSOLUTO
// =============================================
$footer_file = dirname(__DIR__) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>