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

// Verifica che l'utente sia un influencer
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'influencer') {
    die("Accesso negato: Questa area è riservata agli influencer.");
}

// =============================================
// INCLUSIONE FUNZIONI CATEGORIE
// =============================================
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
// RECUPERO INFLUENCER_ID PER PREFERITI (se necessario in futuro)
// =============================================
$influencer_id = null;
$stmt_influencer = $pdo->prepare("SELECT id FROM influencers WHERE user_id = ?");
$stmt_influencer->execute([$_SESSION['user_id']]);
$influencer_data = $stmt_influencer->fetch(PDO::FETCH_ASSOC);
if ($influencer_data) {
    $influencer_id = $influencer_data['id'];
}

// =============================================
// RECUPERO CATEGORIE ATTIVE PER FILTRO
// =============================================
$active_categories = get_active_categories($pdo);

// =============================================
// PARAMETRI DI RICERCA E FILTRI
// =============================================
$search_query = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; // Risultati per pagina
$offset = ($page - 1) * $limit;

// =============================================
// COSTRUZIONE QUERY DINAMICA
// =============================================
$where_conditions = ["b.id IS NOT NULL"];
$params = [];

// Filtro ricerca per nome/nome azienda
if (!empty($search_query)) {
    $where_conditions[] = "(b.company_name LIKE ? OR b.description LIKE ? OR b.website LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Filtro per categoria/settore
if (!empty($category_filter)) {
    $slug_to_name_mapping = [];
    $category_exists = false;
    $category_name = '';
    
    foreach ($active_categories as $category) {
        $slug_to_name_mapping[$category['slug']] = $category['name'];
        if ($category['slug'] === $category_filter) {
            $category_exists = true;
            $category_name = $category['name'];
        }
    }
    
    if ($category_exists) {
        $where_conditions[] = "b.industry = ?";
        $params[] = $category_name;
    }
}

// Query base con JOIN per dati utente
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Query per il conteggio totale (per paginazione)
$count_sql = "
    SELECT COUNT(*) as total 
    FROM brands b 
    JOIN users u ON b.user_id = u.id 
    $where_sql
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_results = $count_stmt->fetchColumn();
$total_pages = ceil($total_results / $limit);

// Query per i risultati con ordinamento casuale
$results_sql = "
    SELECT 
        b.*,
        u.email,
        u.created_at as user_created_at
    FROM brands b 
    JOIN users u ON b.user_id = u.id 
    $where_sql 
    ORDER BY RAND() 
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($results_sql);
$stmt->execute($params);
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// RECUPERO PREFERITI DELL'INFLUENCER PER TUTTI I BRAND
// =============================================
$favorite_brands = [];
if ($influencer_id && !empty($brands)) {
    // Estrai tutti gli ID brand dai risultati
    $brand_ids = array_column($brands, 'id');
    
    // Recupera tutti i brand preferiti in una sola query
    try {
        if (!empty($brand_ids)) {
            // Crea i placeholder per la query
            $placeholders = implode(',', array_fill(0, count($brand_ids), '?'));
            
            $stmt = $pdo->prepare("
                SELECT brand_id 
                FROM favorite_brands 
                WHERE influencer_id = ? 
                AND brand_id IN ($placeholders)
            ");
            
            // Parametri: influencer_id + tutti i brand_ids
            $params_fav = array_merge([$influencer_id], $brand_ids);
            $stmt->execute($params_fav);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Trasforma in array associativo brand_id => true
            foreach ($results as $row) {
                $favorite_brands[$row['brand_id']] = true;
            }
        }
    } catch (PDOException $e) {
        error_log("Errore recupero brand preferiti: " . $e->getMessage());
        // Continua senza preferiti
    }
}

// =============================================
// RECUPERO CONVERSAZIONI ESISTENTI PER TUTTI I BRAND
// =============================================
$existing_conversations = [];
if ($influencer_id && !empty($brands)) {
    // Estrai tutti gli ID brand dai risultati
    $brand_ids = array_column($brands, 'id');
    
    // Recupera tutte le conversazioni esistenti in una sola query
    try {
        if (!empty($brand_ids)) {
            // Crea i placeholder per la query
            $placeholders = implode(',', array_fill(0, count($brand_ids), '?'));
            
            $stmt = $pdo->prepare("
                SELECT brand_id, id as conversation_id 
                FROM conversations 
                WHERE influencer_id = ? 
                AND brand_id IN ($placeholders)
                AND campaign_id IS NULL
            ");
            
            // Parametri: influencer_id + tutti i brand_ids
            $params_conv = array_merge([$influencer_id], $brand_ids);
            $stmt->execute($params_conv);
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Trasforma in array associativo brand_id => conversation_id
            foreach ($results as $row) {
                $existing_conversations[$row['brand_id']] = $row['conversation_id'];
            }
        }
    } catch (PDOException $e) {
        error_log("Errore recupero conversazioni esistenti: " . $e->getMessage());
        // Continua senza conversazioni esistenti
    }
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Cerca Brand</h2>
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
                        <div class="col-md-6">
                            <label for="search" class="form-label">Cerca per nome</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search_query); ?>" 
                                   placeholder="Inserisci il nome del brand...">
                        </div>

                        <div class="col-md-6">
                            <label for="category" class="form-label">Categoria</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">Tutte le categorie</option>
                                <?php foreach ($active_categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['slug']); ?>" 
                                        <?php echo $category_filter === $category['slug'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex gap-2 justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    Cerca
                                </button>
                                <a href="search-brands.php" class="btn btn-outline-secondary">
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
                    Risultati (<?php echo $total_results; ?> brand trovati)
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($brands)): ?>
                    <div class="text-center py-5">
                        <h4 class="text-muted">Nessun brand trovato</h4>
                        <p class="text-muted">Prova a modificare i filtri di ricerca</p>
                    </div>
                <?php else: ?>
                    <!-- GRIGLIA BRAND -->
                    <div class="row">
                        <?php foreach ($brands as $brand): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 brand-card">
                                    <!-- Logo/Immagine -->
                                    <div class="position-relative">
                                        <?php 
                                        // Costruisci il percorso dell'immagine
                                        $logo_path = '';
                                        if (!empty($brand['logo'])) {
                                            // Se il brand ha un logo personalizzato
                                            // Il logo nel database potrebbe già contenere il percorso completo o parziale
                                            $logo_filename = basename($brand['logo']); // Estrae solo il nome del file
                                            
                                            // Verifica se il logo contiene già il percorso
                                            if (strpos($brand['logo'], 'uploads/brands/') !== false) {
                                                // Se contiene già il percorso completo, usa così com'è
                                                $logo_path = '/' . $brand['logo'];
                                            } else {
                                                // Altrimenti costruisci il percorso corretto
                                                $logo_path = '/uploads/brands/' . htmlspecialchars($logo_filename);
                                            }
                                        } else {
                                            // Se il brand NON ha un logo, usa il placeholder
                                            $logo_path = '/uploads/placeholder/sponsor_influencer_dashboard.png';
                                        }
                                        ?>
                                        <img src="<?php echo $logo_path; ?>" 
                                             class="card-img-top" 
                                             alt="<?php echo htmlspecialchars($brand['company_name']); ?>"
                                             style="height: 200px; object-fit: contain; background-color: #f8f9fa;"
                                             onerror="this.onerror=null; this.src='https://via.placeholder.com/300x200/cccccc/969696?text=<?php echo urlencode(htmlspecialchars($brand['company_name'])); ?>';">
                                    </div>

                                    <div class="card-body">
                                        <!-- Nome Azienda -->
                                        <h5 class="card-title"><?php echo htmlspecialchars($brand['company_name']); ?></h5>
                                        
                                        <!-- Badge Settore (SPOSTATO SOTTO IL NOME AZIENDA) -->
                                        <?php if (!empty($brand['industry'])): ?>
                                            <?php
                                            // Crea una mappatura slug -> nome per il display
                                            $slug_to_name_mapping = [];
                                            foreach ($active_categories as $category) {
                                                $slug_to_name_mapping[$category['slug']] = $category['name'];
                                            }
                                            
                                            $original_industry = $brand['industry'];
                                            $display_industry = $slug_to_name_mapping[$original_industry] ?? $original_industry;
                                            ?>
                                            <div class="mb-2">
                                                <span class="badge bg-info"><?php echo htmlspecialchars($display_industry); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Descrizione breve -->
                                        <?php if (!empty($brand['description'])): ?>
                                            <p class="card-text text-muted small">
                                                <?php 
                                                $description = htmlspecialchars($brand['description']);
                                                echo strlen($description) > 120 ? substr($description, 0, 120) . '...' : $description;
                                                ?>
                                            </p>
                                        <?php endif; ?>

                                        <!-- Contatti e Info -->
                                        <div class="mb-2">
                                            <?php if (!empty($brand['contact_person'])): ?>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-user me-1"></i>
                                                    <strong>Contatto:</strong> <?php echo htmlspecialchars($brand['contact_person']); ?>
                                                </small>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($brand['phone'])): ?>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-phone me-1"></i>
                                                    <strong>Telefono:</strong> <?php echo htmlspecialchars($brand['phone']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- PULSANTI AZIONE -->
                                    <div class="card-footer bg-transparent">
    <div class="d-flex flex-column gap-2">
        <?php if ($influencer_id): ?>
            <?php 
            // Controlla se esiste già una conversazione con questo brand
            $conversation_id = $existing_conversations[$brand['id']] ?? false;
            
            // Controlla se il brand è nei preferiti dell'influencer
            $is_favorite = isset($favorite_brands[$brand['id']]);
            ?>
            
            <!-- RIGA SUPERIORE: Pulsanti Dettagli Profilo e Preferiti -->
            <div class="d-flex gap-1">
                <!-- Pulsante Dettagli Profilo -->
                <a href="/brands/profile.php?id=<?php echo $brand['id']; ?>" 
                   class="btn btn-outline-primary btn-sm flex-grow-1">
                    Dettagli profilo
                </a>
                
                <!-- Pulsante Preferiti (solo icona) -->
                <button type="button" 
                        class="btn <?php echo $is_favorite ? 'btn-outline-danger' : 'btn-outline-secondary'; ?> btn-sm favorite-btn-brand"
                        data-brand-id="<?php echo $brand['id']; ?>"
                        data-is-favorite="<?php echo $is_favorite ? '1' : '0'; ?>"
                        title="<?php echo $is_favorite ? 'Rimuovi dai preferiti' : 'Aggiungi ai preferiti'; ?>">
                    <i class="<?php echo $is_favorite ? 'fas fa-heart text-danger' : 'far fa-heart text-secondary'; ?>"></i>
                </button>
            </div>
            
            <!-- RIGA INFERIORE: Pulsanti Messaggistica -->
            <?php if (!$conversation_id): ?>
                <!-- Se NON esiste conversazione: mostra pulsante per inviare messaggio -->
                <button type="button" 
                        class="btn btn-primary btn-sm w-100 send-message-btn"
                        data-brand-id="<?php echo $brand['id']; ?>"
                        data-brand-name="<?php echo htmlspecialchars($brand['company_name']); ?>">
                    <i class="fas fa-envelope"></i> Invia Messaggio
                </button>
                
                <!-- Form fallback per no-JavaScript (nascosto) -->
                <form method="POST" action="start-conversation.php" class="d-none no-js-form">
                    <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                    <input type="hidden" name="initial_message" value="Ciao <?php echo htmlspecialchars($brand['company_name']); ?>, sono interessato a collaborare con voi!">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-envelope"></i> Invia Messaggio
                    </button>
                </form>
            <?php else: ?>
                <!-- Se ESISTE conversazione: mostra pulsanti per andare alla conversazione o nuovo messaggio -->
                <div class="d-flex gap-1">
                    <a href="/influencers/messages/conversation.php?id=<?php echo $conversation_id; ?>" 
                       class="btn btn-primary btn-sm flex-grow-1">
                        <i class="fas fa-comments"></i> Vai alla Conversazione
                    </a>
                    <button type="button" 
                            class="btn btn-outline-primary btn-sm send-message-btn"
                            data-brand-id="<?php echo $brand['id']; ?>"
                            data-brand-name="<?php echo htmlspecialchars($brand['company_name']); ?>"
                            data-conversation-id="<?php echo $conversation_id; ?>"
                            title="Aggiungi nuovo messaggio">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                
                <!-- Form fallback per no-JavaScript (nascosto) -->
                <form method="POST" action="start-conversation.php" class="d-none no-js-form">
                    <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                    <input type="hidden" name="initial_message" value="Ciao <?php echo htmlspecialchars($brand['company_name']); ?>, sono interessato a collaborare con voi!">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-envelope"></i> Invia Messaggio
                    </button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <!-- Se non c'è influencer_id (profilo incompleto) -->
            <!-- RIGA SUPERIORE: Pulsanti Dettagli Profilo e Preferiti disabilitati -->
            <div class="d-flex gap-1">
                <!-- Pulsante Dettagli Profilo -->
                <a href="/brands/profile.php?id=<?php echo $brand['id']; ?>" 
                   class="btn btn-outline-primary btn-sm flex-grow-1">
                    Dettagli profilo
                </a>
                
                <!-- Pulsante Preferiti disabilitato -->
                <button class="btn btn-outline-secondary btn-sm" disabled title="Completa il profilo influencer per aggiungere ai preferiti">
                    <i class="far fa-heart text-secondary"></i>
                </button>
            </div>
            
            <!-- RIGA INFERIORE: Pulsante Messaggistica disabilitato -->
            <button class="btn btn-secondary btn-sm w-100" disabled title="Completa il profilo influencer per inviare messaggi">
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

<!-- MODAL PER MESSAGGIO PERSONALIZZATO (versione influencer -> brand) -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="messageForm" method="POST" action="start-conversation.php">
                <input type="hidden" name="brand_id" id="modalBrandId">
                <input type="hidden" name="initial_message" id="modalInitialMessage">
                <input type="hidden" name="existing_conversation_id" id="existingConversationId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="messageModalLabel">Invia Messaggio al Brand</h5>
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
                                  placeholder="Es: Ciao, sono [Nome Influencer]. Ho visto il vostro profilo e mi piacerebbe collaborare per una campagna su [tema]..."
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
.brand-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.brand-card:hover {
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

/* Riorganizzazione layout pulsanti */
.card-footer .btn {
    min-height: 38px;
}

.btn-primary.flex-grow-1 {
    text-align: center;
}

/* Stile per pulsanti outline (come nella pagina brand) */
.btn-outline-primary {
    border-color: #0d6efd;
    color: #0d6efd;
}

.btn-outline-primary:hover {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: white;
}

/* Stili per i pulsanti preferiti */
.favorite-btn-brand.btn-outline-danger {
    color: #dc3545 !important;
    border-color: #dc3545 !important;
}

.favorite-btn-brand.btn-outline-secondary {
    color: #6c757d !important;
    border-color: #6c757d !important;
}

.favorite-btn-brand:hover {
    transform: scale(1.05);
    transition: transform 0.2s ease-in-out;
}

/* RIMUOVI OVERLAY HOVER PER PULSANTI PREFERITI */
.favorite-btn-brand.btn-outline-danger:hover,
.favorite-btn-brand.btn-outline-secondary:hover {
    background-color: transparent !important;
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
</style>

<script>
// JavaScript per gestione modal messaggi
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
            const brandId = this.getAttribute('data-brand-id');
            const brandName = this.getAttribute('data-brand-name');
            const conversationId = this.getAttribute('data-conversation-id');
            
            // Imposta i valori nel modal
            document.getElementById('modalBrandId').value = brandId;
            
            // Imposta messaggio predefinito personalizzato
            const defaultMessage = conversationId 
                ? `Ciao ${brandName}, vorrei aggiungere qualcosa alla nostra conversazione: `
                : `Ciao ${brandName}, sono interessato a collaborare con voi!`;
            
            document.getElementById('customMessage').value = defaultMessage;
            document.getElementById('modalInitialMessage').value = defaultMessage;
            
            // Se esiste conversazione, aggiorna il titolo del modal
            if (conversationId) {
                document.getElementById('messageModalLabel').textContent = 'Aggiungi Nuovo Messaggio';
                // Imposta campo nascosto per conversation_id
                document.getElementById('existingConversationId').value = conversationId;
            } else {
                document.getElementById('messageModalLabel').textContent = 'Invia Messaggio al Brand';
                // Pulisci campo hidden
                document.getElementById('existingConversationId').value = '';
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
        document.getElementById('messageModalLabel').textContent = 'Invia Messaggio al Brand';
        
        // Pulisci campo hidden
        document.getElementById('existingConversationId').value = '';
        
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
    document.querySelectorAll('.favorite-btn-brand').forEach(button => {
        button.addEventListener('click', function() {
            const brandId = this.getAttribute('data-brand-id');
            const isFavorite = this.getAttribute('data-is-favorite') === '1';
            
            // Disabilita il pulsante durante la richiesta
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            // Invia richiesta AJAX
            fetch('/influencers/toggle-favorite-brand.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `brand_id=${brandId}&action=${isFavorite ? 'remove' : 'add'}`
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