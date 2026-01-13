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
// INCLUSIONE FUNZIONI SOCIAL NETWORK
// =============================================
$social_functions_file = dirname(__DIR__) . '/includes/social_network_functions.php';
if (file_exists($social_functions_file)) {
    require_once $social_functions_file;
}

// =============================================
// INCLUSIONE FUNZIONI CATEGORIE
// =============================================
$category_functions_file = dirname(__DIR__) . '/includes/category_functions.php';
if (file_exists($category_functions_file)) {
    require_once $category_functions_file;
}

// =============================================
// GENERA TOKEN CSRF (se non esiste)
// =============================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =============================================
// VERIFICA AUTENTICAZIONE UTENTE
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    die("Accesso negato: Questa area è riservata ai brand.");
}

// =============================================
// RECUPERO DATI BRAND E CAMPAIGNA DA MODIFICARE
// =============================================
$brand = null;
$campaign = null;
$error = '';
$success = '';

try {
    // Recupera brand
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brand) {
        header("Location: create-profile.php");
        exit();
    }
    
    // Verifica se è stata passata l'ID della campagna da modificare
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception("ID campagna non specificato");
    }
    
    $campaign_id = intval($_GET['id']);
    
    // Recupera la campagna specifica
    $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ? AND brand_id = ?");
    $stmt->execute([$campaign_id, $brand['id']]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        throw new Exception("Campagna non trovata o accesso negato");
    }
    
    // Verifica che la campagna sia in stato bozza OPPURE attiva
    if (!in_array($campaign['status'], ['draft', 'active'])) {
        throw new Exception("Solo le campagne in bozza o attive possono essere modificate");
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// =============================================
// ELENCO CATEGORIE E PIATTAFORME DINAMICHE
// =============================================

// RECUPERO CATEGORIE DINAMICHE DAL DATABASE
$niches = [];
try {
    if (function_exists('get_active_categories') && file_exists($category_functions_file)) {
        $categories = get_active_categories($pdo);
        foreach ($categories as $category) {
            $niches[$category['id']] = $category['name'];
        }
    }
    
    // Se non ci sono categorie attive, usa valori di fallback
    if (empty($niches)) {
        // Prova a recuperare dalla tabella categories
        try {
            $stmt = $pdo->prepare("SELECT name FROM categories WHERE is_active = TRUE ORDER BY display_order ASC, name ASC");
            $stmt->execute();
            $category_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($category_results as $category) {
                $niches[] = $category['name'];
            }
        } catch (PDOException $e) {
            // Fallback a categorie predefinite
            $niches = [
                'Fashion', 'Lifestyle', 'Beauty & Makeup', 'Food', 'Travel',
                'Gaming', 'Fitness & Wellness', 'Entertainment', 'Tech',
                'Finance & Business', 'Pet', 'Education'
            ];
        }
    }
} catch (Exception $e) {
    // Fallback a categorie predefinite in caso di errore
    $niches = [
        'Fashion', 'Lifestyle', 'Beauty & Makeup', 'Food', 'Travel',
        'Gaming', 'Fitness & Wellness', 'Entertainment', 'Tech',
        'Finance & Business', 'Pet', 'Education'
    ];
}

// RECUPERO PIATTAFORME DINAMICHE DAL DATABASE
$platforms = [];
try {
    if (function_exists('get_active_social_networks') && file_exists($social_functions_file)) {
        $social_networks = get_active_social_networks();
        foreach ($social_networks as $social) {
            $platforms[$social['slug']] = $social['name'];
        }
    } else {
        // Fallback a piattaforme predefinite
        $platforms = [
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
            'facebook' => 'Facebook',
            'twitter' => 'Twitter/X'
        ];
    }
} catch (Exception $e) {
    // Fallback a piattaforme predefinite in caso di errore
    $platforms = [
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        'facebook' => 'Facebook',
        'twitter' => 'Twitter/X'
    ];
}

// =============================================
// FUNZIONE PER CALCOLARE E MOSTRARE BUDGET LIMIT
// =============================================
function calculate_and_display_budget_limit($budget) {
    // Definizione costanti se non esistenti
    if (!defined('BUDGET_TIER_LOW_MAX')) define('BUDGET_TIER_LOW_MAX', 200);
    if (!defined('BUDGET_TIER_MEDIUM_MAX')) define('BUDGET_TIER_MEDIUM_MAX', 1000);
    
    if (empty($budget)) return '';
    
    $budget_float = floatval($budget);
    
    // Calcolo manuale del budget limit
    if ($budget_float <= BUDGET_TIER_LOW_MAX) {
        $budget_limit = $budget_float * 0.5;
        $percentage = 50;
        $tier_info = " (Tier Basso - {$percentage}%)";
    } elseif ($budget_float <= BUDGET_TIER_MEDIUM_MAX) {
        $budget_limit = $budget_float * 0.3;
        $percentage = 30;
        $tier_info = " (Tier Medio - {$percentage}%)";
    } else {
        $budget_limit = $budget_float * 0.2;
        $percentage = 20;
        $tier_info = " (Tier Alto - {$percentage}%)";
    }
    
    return number_format($budget_limit, 2) . $tier_info;
}

// =============================================
// GESTIONE INVIO FORM (AGGIORNAMENTO)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verifica CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Token di sicurezza non valido. Riprova.");
        }
        
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $budget = floatval($_POST['budget']);
        $niche = $_POST['niche'];
        $platforms_selected = isset($_POST['platforms']) ? $_POST['platforms'] : [];
        $target_audience = [
            'age_range' => $_POST['age_range'] ?? '',
            'gender' => $_POST['gender'] ?? '',
            'location' => $_POST['location'] ?? '',
            'interests' => $_POST['interests'] ?? ''
        ];
        $requirements = trim($_POST['requirements']);
        $start_date = $_POST['start_date'] ?: null;
        $end_date = $_POST['end_date'] ?: null;
        
        // Determina lo stato - MANTIENE LO STATO ORIGINALE (non cambia da active a draft)
        $status = $campaign['status']; // Mantiene lo stato attuale

        // Validazione
        if (empty($name)) {
            throw new Exception("Il nome della campagna è obbligatorio");
        }

        if (empty($description)) {
            throw new Exception("La descrizione della campagna è obbligatoria");
        }

        if ($budget <= 0) {
            throw new Exception("Il budget deve essere maggiore di 0");
        }

        if (empty($niche)) {
            throw new Exception("Seleziona una categoria");
        }

        if (empty($platforms_selected)) {
            throw new Exception("Seleziona almeno una piattaforma");
        }

        // Validazione piattaforme selezionate rispetto a quelle disponibili
        $valid_platforms = array_keys($platforms);
        foreach ($platforms_selected as $platform) {
            if (!in_array($platform, $valid_platforms)) {
                throw new Exception("Piattaforma non valida selezionata: " . htmlspecialchars($platform));
            }
        }

        // Aggiornamento nel database
        $stmt = $pdo->prepare("
            UPDATE campaigns 
            SET name = ?, description = ?, budget = ?, niche = ?, platforms = ?, 
                target_audience = ?, requirements = ?, start_date = ?, end_date = ?, status = ?
            WHERE id = ? AND brand_id = ?
        ");

        $stmt->execute([
            $name,
            $description,
            $budget,
            $niche,
            json_encode($platforms_selected),
            json_encode($target_audience),
            $requirements,
            $start_date,
            $end_date,
            $status,
            $campaign['id'],
            $brand['id']
        ]);

        // Se la campagna era attiva, rimane attiva e aggiornata
        if ($status === 'active') {
            $success = "Campagna aggiornata con successo! La campagna rimane attiva.";
        } else {
            $success = "Campagna aggiornata come bozza. Puoi attivarla quando sei pronto.";
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// =============================================
// PREPARAZIONE DATI PER IL FORM
// =============================================
if ($campaign) {
    // Decodifica i campi JSON
    $campaign_platforms = json_decode($campaign['platforms'] ?? '[]', true) ?: [];
    $campaign_target_audience = json_decode($campaign['target_audience'] ?? '[]', true) ?: [];
    
    // Prepara i dati per il form (usando i dati POST se presenti, altrimenti i dati del DB)
    $form_data = [
        'name' => $_POST['name'] ?? $campaign['name'],
        'description' => $_POST['description'] ?? $campaign['description'],
        'budget' => $_POST['budget'] ?? $campaign['budget'],
        'niche' => $_POST['niche'] ?? $campaign['niche'],
        'platforms' => isset($_POST['platforms']) ? $_POST['platforms'] : $campaign_platforms,
        'age_range' => $_POST['age_range'] ?? ($campaign_target_audience['age_range'] ?? ''),
        'gender' => $_POST['gender'] ?? ($campaign_target_audience['gender'] ?? ''),
        'location' => $_POST['location'] ?? ($campaign_target_audience['location'] ?? ''),
        'interests' => $_POST['interests'] ?? ($campaign_target_audience['interests'] ?? ''),
        'requirements' => $_POST['requirements'] ?? $campaign['requirements'],
        'start_date' => $_POST['start_date'] ?? $campaign['start_date'],
        'end_date' => $_POST['end_date'] ?? $campaign['end_date']
    ];
} else {
    $form_data = [
        'name' => $_POST['name'] ?? '',
        'description' => $_POST['description'] ?? '',
        'budget' => $_POST['budget'] ?? '',
        'niche' => $_POST['niche'] ?? '',
        'platforms' => $_POST['platforms'] ?? [],
        'age_range' => $_POST['age_range'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'location' => $_POST['location'] ?? '',
        'interests' => $_POST['interests'] ?? '',
        'requirements' => $_POST['requirements'] ?? '',
        'start_date' => $_POST['start_date'] ?? '',
        'end_date' => $_POST['end_date'] ?? ''
    ];
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

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Modifica campagna: <?php echo htmlspecialchars($campaign['name'] ?? ''); ?></h2>
            <div>
                <?php if ($campaign['status'] === 'draft'): ?>
                    <span class="badge bg-secondary me-2">Bozza</span>
                <?php endif; ?>
                <a href="campaigns.php" class="btn btn-outline-secondary">
                    ← Torna alle Campagne
                </a>
            </div>
        </div>

        <!-- Messaggi di stato -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$campaign): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Impossibile caricare i dati della campagna. <a href="campaigns.php" class="alert-link">Torna alla lista campagne</a>
            </div>
        <?php else: ?>
            <!-- Form Modifica Campagna -->
            <div class="card">
                <div class="card-body">
                    <form method="POST" id="campaignForm">
                        <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row">
                            <!-- Informazioni Base -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nome campagna *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($form_data['name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="budget" class="form-label">Budget (€) *</label>
                                    <input type="number" class="form-control" id="budget" name="budget" 
                                           min="0" step="0.01" value="<?php echo htmlspecialchars($form_data['budget']); ?>" required
                                           oninput="updateBudgetLimit()">
                                </div>

                                <div class="mb-3">
                                    <label for="niche" class="form-label">Categoria *</label>
                                    <select class="form-select" id="niche" name="niche" required>
                                        <option value="">Seleziona una categoria</option>
                                        <?php foreach ($niches as $id => $name): ?>
                                            <?php 
                                            // Gestione compatibilità: se $niches è array associativo (nuovo) usa $id, 
                                            // se è array indicizzato (vecchio) usa $name come valore
                                            $value = is_string($id) ? $id : $name;
                                            $display_name = $name;
                                            ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>" 
                                                    <?php echo ($form_data['niche'] === $value) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($display_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="platforms" class="form-label">Piattaforme social *</label>
                                    <div class="border p-3 rounded">
                                        <?php if (empty($platforms)): ?>
                                            <div class="alert alert-warning">
                                                <small>Nessuna piattaforma social configurata. Contatta l'amministratore.</small>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($platforms as $key => $platform): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="platforms[]" value="<?php echo $key; ?>" 
                                                           id="platform_<?php echo $key; ?>"
                                                           <?php echo (in_array($key, $form_data['platforms'])) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="platform_<?php echo $key; ?>">
                                                        <?php echo htmlspecialchars($platform); ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-text">Seleziona tutte le piattaforme su cui vuoi promuovere la campagna</div>
                                </div>
                            </div>
                        </div>

                        <!-- Descrizione -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Descrizione campagna *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                        </div>

                        <!-- Target Audience -->
                        <div class="mb-4">
                            <h5>Target Audience</h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="age_range" class="form-label">Fascia d'Età</label>
                                    <input type="text" class="form-control" id="age_range" name="age_range" 
                                           value="<?php echo htmlspecialchars($form_data['age_range']); ?>" 
                                           placeholder="es. 18-35">
                                </div>
                                <div class="col-md-3">
                                    <label for="gender" class="form-label">Genere</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">Tutti</option>
                                        <option value="male" <?php echo ($form_data['gender'] === 'male') ? 'selected' : ''; ?>>Maschile</option>
                                        <option value="female" <?php echo ($form_data['gender'] === 'female') ? 'selected' : ''; ?>>Femminile</option>
                                        <option value="both" <?php echo ($form_data['gender'] === 'both') ? 'selected' : ''; ?>>Entrambi</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="location" class="form-label">Localizzazione</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($form_data['location']); ?>" 
                                           placeholder="es. Italia">
                                </div>
                                <div class="col-md-3">
                                    <label for="interests" class="form-label">Interessi</label>
                                    <input type="text" class="form-control" id="interests" name="interests" 
                                           value="<?php echo htmlspecialchars($form_data['interests']); ?>" 
                                           placeholder="es. tecnologia, gaming">
                                </div>
                            </div>
                        </div>

                        <!-- Date e Requisiti -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Data Inizio</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo htmlspecialchars($form_data['start_date']); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">Data Fine</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo htmlspecialchars($form_data['end_date']); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Requisiti Specifici -->
                        <div class="mb-3">
                            <label for="requirements" class="form-label">Requisiti Specifici</label>
                            <textarea class="form-control" id="requirements" name="requirements" rows="3" 
                                      placeholder="Requisiti specifici per gli influencer..."><?php echo htmlspecialchars($form_data['requirements']); ?></textarea>
                        </div>

                        <!-- Pulsanti -->
                        <div class="d-flex gap-2">
                            <?php if ($campaign['status'] === 'draft'): ?>
                                <button type="submit" class="btn btn-outline-primary">
                                    💾 Aggiorna Bozza
                                </button>
                                <button type="submit" name="action" value="activate" class="btn btn-primary">
                                    🚀 Attiva Campagna
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-primary">
                                    Aggiorna campagna
                                </button>
                            <?php endif; ?>
                            <a href="campaigns.php" class="btn btn-secondary">Annulla</a>
                        </div>

                        <!-- Informazioni aggiuntive -->
                        <div class="mt-3">
                            <small class="text-muted">
                                <?php if ($campaign['status'] === 'active'): ?>
                                    <strong>Aggiorna campagna:</strong> Aggiorna le informazioni mantenendo la campagna attiva. Tutti i dati esistenti (influencer collegati, richieste, etc.) vengono mantenuti.
                                <?php else: ?>
                                    <strong>Aggiorna Bozza:</strong> Salva le modifiche senza attivare la campagna<br>
                                    <strong>Attiva Campagna:</strong> Salva le modifiche ed attiva la campagna per il matching con gli influencer
                                <?php endif; ?>
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('campaignForm');
    
    // Validazione piattaforme
    form.addEventListener('submit', function(e) {
        const platformCheckboxes = document.querySelectorAll('input[name="platforms[]"]');
        const checkedPlatforms = Array.from(platformCheckboxes).filter(cb => cb.checked);
        
        if (checkedPlatforms.length === 0) {
            e.preventDefault();
            alert('Seleziona almeno una piattaforma social');
            return false;
        }
    });
    
    // Validazione date
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    if (startDate && endDate) {
        startDate.addEventListener('change', function() {
            if (this.value && endDate.value && this.value > endDate.value) {
                endDate.value = '';
            }
        });
        
        endDate.addEventListener('change', function() {
            if (this.value && startDate.value && this.value < startDate.value) {
                alert('La data di fine non può essere precedente alla data di inizio');
                this.value = '';
            }
        });
    }
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