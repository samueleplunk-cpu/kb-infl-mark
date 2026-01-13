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
// INCLUSIONE FUNZIONI SOCIAL NETWORK
// =============================================
require_once dirname(__DIR__) . '/includes/social_network_functions.php';

// =============================================
// RECUPERO DATI INFLUENCER
// =============================================
$influencer = null;
$error = '';
$success = '';

try {
    $stmt = $pdo->prepare("
        SELECT i.*, u.email 
        FROM influencers i 
        INNER JOIN users u ON i.user_id = u.id 
        WHERE i.user_id = ? 
        AND u.id = ?
        AND u.user_type = 'influencer'
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        // Reindirizza alla creazione profilo
        $_SESSION['profile_error'] = "Devi completare il tuo profilo influencer prima di creare sponsor.";
        header("Location: create-profile.php");
        exit();
    }
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo influencer: " . $e->getMessage();
}

// =============================================
// ELENCO CATEGORIE DINAMICHE E PIATTAFORME
// =============================================

// Carica le categorie dal database invece dell'array statico
$categories = [];
try {
    // Includi le funzioni per le categorie
    require_once dirname(__DIR__) . '/includes/category_functions.php';
    
    // Recupera le categorie attive dal database
    $active_categories = get_active_categories($pdo);
    
    // Converti nel formato richiesto dal form (slug => name)
    foreach ($active_categories as $category) {
        $categories[$category['slug']] = $category['name'];
    }
    
    // Se non ci sono categorie nel database, usa quelle di default come fallback
    if (empty($categories)) {
        $categories = [
            'fashion' => 'Fashion',
            'lifestyle' => 'Lifestyle',
            'beauty' => 'Beauty & Makeup',
            'food' => 'Food',
            'travel' => 'Travel',
            'gaming' => 'Gaming',
            'fitness' => 'Fitness & Wellness',
            'entertainment' => 'Entertainment',
            'tech' => 'Tech',
            'finance' => 'Finance & Business',
            'pet' => 'Pet',
            'education' => 'Education'
        ];
    }
} catch (Exception $e) {
    // Fallback alle categorie statiche in caso di errore
    $categories = [
        'fashion' => 'Fashion',
        'lifestyle' => 'Lifestyle',
        'beauty' => 'Beauty & Makeup',
        'food' => 'Food',
        'travel' => 'Travel',
        'gaming' => 'Gaming',
        'fitness' => 'Fitness & Wellness',
        'entertainment' => 'Entertainment',
        'tech' => 'Tech',
        'finance' => 'Finance & Business',
        'pet' => 'Pet',
        'education' => 'Education'
    ];
}

// Piattaforme dinamiche dai social network
$platforms = [];
$social_networks = get_active_social_networks();
foreach ($social_networks as $social) {
    $platforms[$social['slug']] = $social['name'];
}

// =============================================
// GESTIONE INVIO FORM
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title']);
        $budget = floatval($_POST['budget']);
        $category = $_POST['category'];
        $description = trim($_POST['description']);
        $platforms_selected = isset($_POST['platforms']) ? $_POST['platforms'] : [];
        $target_audience = [
            'age_range' => $_POST['age_range'] ?? '',
            'gender' => $_POST['gender'] ?? ''
        ];
        
        // Ora lo sponsor è sempre attivo (non più bozza)
        $status = 'active';

        // Validazione
        if (empty($title)) {
            throw new Exception("Il titolo dello sponsor è obbligatorio");
        }

        if ($budget <= 0) {
            throw new Exception("Il budget deve essere maggiore di 0");
        }

        if (empty($category)) {
            throw new Exception("Seleziona una categoria valida");
        }

        // Verifica che la categoria esista nel database
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM categories WHERE slug = ? AND is_active = TRUE");
            $stmt->execute([$category]);
            $category_exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
            
            if (!$category_exists) {
                throw new Exception("Categoria non valida o non più disponibile");
            }
        } catch (Exception $e) {
            throw new Exception("Errore nella validazione della categoria: " . $e->getMessage());
        }

        if (empty($description)) {
            throw new Exception("La descrizione dello sponsor è obbligatoria");
        }

        if (empty($platforms_selected)) {
            throw new Exception("Seleziona almeno una piattaforma");
        }

        // =============================================
        // VERIFICA E RIPARAZIONE INFLUENCER_ID
        // =============================================
        
        // Verifica che l'influencer_id esista in influencers
        $verify_stmt = $pdo->prepare("SELECT id FROM influencers WHERE id = ?");
        $verify_stmt->execute([$influencer['id'] ?? 0]);
        $influencer_exists = $verify_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$influencer_exists) {
            // Cerca tutti gli influencer per questo utente
            $find_all_stmt = $pdo->prepare("SELECT id FROM influencers WHERE user_id = ?");
            $find_all_stmt->execute([$_SESSION['user_id']]);
            $all_influencers = $find_all_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($all_influencers)) {
                // Usa il primo influencer trovato
                $influencer['id'] = $all_influencers[0]['id'];
            } else {
                // Nessun influencer trovato - creane uno
                // Recupera dati utente
                $user_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
                $user_stmt->execute([$_SESSION['user_id']]);
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                $create_stmt = $pdo->prepare("
                    INSERT INTO influencers (user_id, full_name, niche, created_at) 
                    VALUES (?, ?, 'lifestyle', NOW())
                ");
                $create_stmt->execute([
                    $_SESSION['user_id'], 
                    $user['email'] ?? 'Utente ' . $_SESSION['user_id']
                ]);
                
                $influencer['id'] = $pdo->lastInsertId();
            }
        }

        // =============================================
        // GESTIONE UPLOAD IMMAGINE
        // =============================================
        $image_url = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = dirname(__DIR__) . '/uploads/sponsor/';
            
            // Crea directory se non esiste
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception("Formato file non supportato. Usa JPG, PNG o GIF.");
            }
            
            if ($_FILES['image']['size'] > 2 * 1024 * 1024) { // 2MB
                throw new Exception("L'immagine non può superare i 2MB");
            }
            
            $filename = 'sponsor_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                $image_url = $filename;
            } else {
                throw new Exception("Errore nel caricamento dell'immagine");
            }
        }

        // =============================================
        // INSERIMENTO NEL DATABASE
        // =============================================
        $stmt = $pdo->prepare("
            INSERT INTO sponsors 
            (influencer_id, title, image_url, budget, category, description, platforms, target_audience, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $influencer['id'],
            $title,
            $image_url,
            $budget,
            $category,
            $description,
            json_encode($platforms_selected),
            json_encode($target_audience),
            $status
        ]);

        $sponsor_id = $pdo->lastInsertId();
        
        // Reindirizza alla lista sponsor
        header("Location: sponsors.php?success=sponsor_created");
        exit();

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
            <h2>Crea Nuovo Sponsor</h2>
            <a href="sponsors.php" class="btn btn-outline-secondary">
                ← Torna agli Sponsor
            </a>
        </div>

        <!-- Messaggi di stato -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Creazione Sponsor -->
        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="sponsorForm">
                    <div class="row">
                        <!-- Informazioni Base -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="title" class="form-label">Titolo sponsor *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required
                                       placeholder="Es: Collaborazione Instagram Fashion">
                            </div>

                            <div class="mb-3">
                                <label for="budget" class="form-label">Budget (€) *</label>
                                <input type="number" class="form-control" id="budget" name="budget" 
                                       min="0" step="0.01" value="<?php echo htmlspecialchars($_POST['budget'] ?? ''); ?>" required>
                                <div class="form-text">
                                    <small class="text-muted">
                                        Inserisci il budget che richiedi per questa collaborazione
                                    </small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="category" class="form-label">Categoria *</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Seleziona una categoria</option>
                                    <?php foreach ($categories as $key => $category): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>" 
                                                <?php echo (($_POST['category'] ?? '') === $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="platforms" class="form-label">Piattaforme social *</label>
                                <div class="border p-3 rounded">
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
                                </div>
                                <div class="form-text">Seleziona le piattaforme su cui sei disponibile</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="image" class="form-label">Immagine di Anteprima</label>
                                <input type="file" class="form-control" id="image" name="image" 
                                       accept="image/jpeg,image/png,image/gif">
                                <div class="form-text">
                                    <small class="text-muted">
                                        Formati supportati: JPG, PNG, GIF (max 2MB)
                                    </small>
                                </div>
                                <div id="imagePreview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Descrizione -->
                    <div class="mb-3">
                        <label for="description" class="form-label">Descrizione sponsor *</label>
                        <textarea class="form-control" id="description" name="description" rows="4" required
                                  placeholder="Descrivi la tua proposta di collaborazione, cosa offri ai brand..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <!-- Target Audience -->
                    <div class="mb-4">
                        <h5>Target Audience</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="age_range" class="form-label">Fascia d'Età del Tuo Pubblico</label>
                                <input type="text" class="form-control" id="age_range" name="age_range" 
                                       value="<?php echo htmlspecialchars($_POST['age_range'] ?? ''); ?>" 
                                       placeholder="es. 18-35">
                            </div>
                            <div class="col-md-6">
                                <label for="gender" class="form-label">Genere del Tuo Pubblico</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Tutti</option>
                                    <option value="male" <?php echo (($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Maschile</option>
                                    <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Femminile</option>
                                    <option value="both" <?php echo (($_POST['gender'] ?? '') === 'both') ? 'selected' : ''; ?>>Entrambi</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Pulsanti -->
                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="save_active" class="btn btn-primary">
                            Pubblica sponsor
                        </button>
                        <a href="sponsors.php" class="btn btn-secondary">Annulla</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('sponsorForm');
    const imageInput = document.getElementById('image');
    const imagePreview = document.getElementById('imagePreview');
    
    // Variabile per memorizzare l'immagine corrente
    let currentImageData = null;
    
    // Anteprima immagine con validazione dimensione
    imageInput.addEventListener('change', function() {
        const file = this.files[0];
        
        // Se l'utente ha selezionato un file valido
        if (file) {
            // Controllo dimensione file (2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert('L\'immagine non può superare i 2MB');
                this.value = ''; // Reset del campo file
                // NON cancellare l'anteprima esistente se c'era già un'immagine
                if (!currentImageData) {
                    imagePreview.innerHTML = '';
                }
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                // Memorizza i dati della nuova immagine
                currentImageData = e.target.result;
                updateImagePreview(currentImageData);
            };
            reader.readAsDataURL(file);
        } 
        // Si l'utente ha annullato la selezione (file è null/undefined)
        // NON fare nulla per mantenere l'anteprima esistente
    });
    
    // Funzione per aggiornare l'anteprima con il pulsante di eliminazione
    function updateImagePreview(imageData) {
        imagePreview.innerHTML = `
            <div class="position-relative d-inline-block">
                <img src="${imageData}" 
                     class="img-thumbnail" 
                     style="max-width: 200px; max-height: 200px;"
                     alt="Anteprima immagine">
                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                        onclick="removeImage()" title="Rimuovi immagine"
                        style="width: 25px; height: 25px; padding: 0; border-radius: 50%;">
                    ×
                </button>
            </div>
        `;
    }
    
    // Funzione per rimuovere l'immagine (disponibile globalmente)
    window.removeImage = function() {
        // Reset del campo file
        imageInput.value = '';
        // Cancella l'anteprima
        imagePreview.innerHTML = '';
        // Reset della variabile dell'immagine corrente
        currentImageData = null;
    };
    
    // Validazione form
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
        
        // Validazione budget
        const budget = document.getElementById('budget').value;
        if (budget <= 0) {
            e.preventDefault();
            alert('Il budget deve essere maggiore di 0');
            return false;
        }
        
        // Validazione dimensione file prima dell'invio
        const fileInput = document.getElementById('image');
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            if (file.size > 2 * 1024 * 1024) {
                e.preventDefault();
                alert('L\'immagine non può superare i 2MB');
                return false;
            }
        }
    });
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