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
if (!file_exists($social_functions_file)) {
    die("Errore: File funzioni social network non trovato in: " . $social_functions_file);
}
require_once $social_functions_file;

// =============================================
// INCLUSIONE FUNZIONI CATEGORIE
// =============================================
$category_functions_file = dirname(__DIR__) . '/includes/category_functions.php';
if (!file_exists($category_functions_file)) {
    die("Errore: File funzioni categorie non trovato in: " . $category_functions_file);
}
require_once $category_functions_file;

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
// RECUPERO DATI BRAND
// =============================================
$brand = null;
$error = '';
$success = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$brand) {
        header("Location: create-profile.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo brand: " . $e->getMessage();
}

// =============================================
// ELENCO CATEGORIE E PIATTAFORME (MODIFICATO - CATEGORIE DINAMICHE)
// =============================================

// RECUPERO CATEGORIE DINAMICHE DAL DATABASE
$niches = [];
try {
    $categories = get_active_categories($pdo);
    foreach ($categories as $category) {
        $niches[$category['id']] = $category['name'];
    }
    
    // Se non ci sono categorie attive, usa valori di fallback
    if (empty($niches)) {
        $niches = [
            'Fashion',
            'Lifestyle', 
            'Beauty & Makeup',
            'Food',
            'Travel',
            'Gaming',
            'Fitness & Wellness',
            'Entertainment',
            'Tech',
            'Finance & Business',
            'Pet',
            'Education'
        ];
        error_log("Nessuna categoria attiva trovata nel database, usando categorie predefinite");
    }
} catch (Exception $e) {
    $error = "Errore nel caricamento delle categorie: " . $e->getMessage();
    // Fallback a categorie predefinite in caso di errore
    $niches = [
        'Fashion',
        'Lifestyle',
        'Beauty & Makeup', 
        'Food',
        'Travel',
        'Gaming',
        'Fitness & Wellness',
        'Entertainment',
        'Tech',
        'Finance & Business',
        'Pet',
        'Education'
    ];
}

// RECUPERO PIATTAFORME DINAMICHE DAL DATABASE
$platforms = [];
try {
    $social_networks = get_active_social_networks();
    foreach ($social_networks as $social) {
        $platforms[$social['slug']] = $social['name'];
    }
} catch (Exception $e) {
    $error = "Errore nel caricamento delle piattaforme social: " . $e->getMessage();
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
// GESTIONE INVIO FORM
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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
        
        // Determina lo stato in base al pulsante cliccato
        $status = 'draft';
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'save_active') {
                $status = 'active';
            }
        }

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

        // Inserimento nel database
        $stmt = $pdo->prepare("
            INSERT INTO campaigns 
            (brand_id, name, description, budget, niche, platforms, target_audience, requirements, start_date, end_date, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $brand['id'],
            $name,
            $description,
            $budget,
            $niche,
            json_encode($platforms_selected),
            json_encode($target_audience),
            $requirements,
            $start_date,
            $end_date,
            $status
        ]);

        $campaign_id = $pdo->lastInsertId();
        
        // Esegui il matching con gli influencer SOLO se la campagna è attiva
        if ($status === 'active') {
            $matching_results = perform_advanced_influencer_matching($pdo, $campaign_id, $niche, $platforms_selected, $budget);
            $success = "Campagna creata con successo! Trovati " . count($matching_results) . " influencer matching.";
            
            // Reindirizza alla dashboard campagne
            header("Location: campaigns.php?success=campaign_created&matches=" . count($matching_results));
            exit();
        } else {
            $success = "Campagna salvata come bozza. Il matching verrà eseguito quando la attiverai.";
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
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
            <h2>Crea Nuova Campagna</h2>
            <a href="campaigns.php" class="btn btn-outline-secondary">
                ← Torna alle Campagne
            </a>
        </div>

        <!-- Messaggi di stato -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Creazione Campagna -->
        <div class="card">
            <div class="card-body">
                <form method="POST" id="campaignForm">
                    <div class="row">
                        <!-- Informazioni Base -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nome Campagna *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="budget" class="form-label">Budget (€) *</label>
                                <input type="number" class="form-control" id="budget" name="budget" 
                                       min="0" step="0.01" value="<?php echo htmlspecialchars($_POST['budget'] ?? ''); ?>" required>
                                <div class="form-text">
                                    <small class="text-muted">
                                        Inserisci il budget totale della campagna
                                    </small>
                                </div>
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
                                                <?php echo (($_POST['niche'] ?? '') === $value) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="platforms" class="form-label">Piattaforme Social *</label>
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
                                                       <?php echo (isset($_POST['platforms']) && in_array($key, $_POST['platforms'])) ? 'checked' : ''; ?>>
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
                        <label for="description" class="form-label">Descrizione Campagna *</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <!-- Target Audience -->
                    <div class="mb-4">
                        <h5>Target Audience</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <label for="age_range" class="form-label">Fascia d'Età</label>
                                <input type="text" class="form-control" id="age_range" name="age_range" 
                                       value="<?php echo htmlspecialchars($_POST['age_range'] ?? ''); ?>" 
                                       placeholder="es. 18-35">
                            </div>
                            <div class="col-md-3">
                                <label for="gender" class="form-label">Genere</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Tutti</option>
                                    <option value="male" <?php echo (($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Maschile</option>
                                    <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Femminile</option>
                                    <option value="both" <?php echo (($_POST['gender'] ?? '') === 'both') ? 'selected' : ''; ?>>Entrambi</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="location" class="form-label">Localizzazione</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                                       placeholder="es. Italia">
                            </div>
                            <div class="col-md-3">
                                <label for="interests" class="form-label">Interessi</label>
                                <input type="text" class="form-control" id="interests" name="interests" 
                                       value="<?php echo htmlspecialchars($_POST['interests'] ?? ''); ?>" 
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
                                       value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">Data Fine</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                       value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Requisiti Specifici -->
                    <div class="mb-3">
                        <label for="requirements" class="form-label">Requisiti Specifici</label>
                        <textarea class="form-control" id="requirements" name="requirements" rows="3" 
                                  placeholder="Requisiti specifici per gli influencer..."><?php echo htmlspecialchars($_POST['requirements'] ?? ''); ?></textarea>
                    </div>

                    <!-- Pulsanti -->
                    <div class="d-flex gap-2">
    <button type="submit" name="action" value="save_active" class="btn btn-primary">
        Pubblica campagna
    </button>
    <a href="campaigns.php" class="btn btn-secondary">Annulla</a>
</div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('campaignForm');
    
    // Cambia stato in base al pulsante cliccato
    form.addEventListener('submit', function(e) {
        const submitButton = document.activeElement;
        const platformCheckboxes = document.querySelectorAll('input[name="platforms[]"]');
        const checkedPlatforms = Array.from(platformCheckboxes).filter(cb => cb.checked);
        
        // Validazione piattaforme
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

// Tooltip per informazioni aggiuntive
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<style>
.progress {
    height: 20px;
}
.progress-bar {
    font-weight: bold;
}
.alert-info {
    border-left: 4px solid #0dcaf0;
}
.card-header.bg-light {
    background-color: #f8f9fa !important;
    border-bottom: 1px solid #dee2e6;
}
.form-text small {
    font-size: 0.875em;
}
</style>

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