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
    $stmt = $pdo->prepare("SELECT * FROM influencers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        header("Location: create-profile.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo influencer: " . $e->getMessage();
}

// =============================================
// VERIFICA PARAMETRO ID SPONSOR
// =============================================
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: sponsors.php?error=invalid_sponsor_id");
    exit();
}

$sponsor_id = intval($_GET['id']);

// =============================================
// RECUPERO DATI SPONSOR CON VERIFICA PROPRIETÀ
// =============================================
$sponsor = null;
try {
    $stmt = $pdo->prepare("
        SELECT * FROM sponsors 
        WHERE id = ? AND influencer_id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$sponsor_id, $influencer['id']]);
    $sponsor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sponsor) {
        header("Location: sponsors.php?error=sponsor_not_found");
        exit();
    }
    
    // Decodifica JSON fields
    if (!empty($sponsor['platforms'])) {
        $sponsor['platforms'] = json_decode($sponsor['platforms'], true);
    } else {
        $sponsor['platforms'] = [];
    }
    
    if (!empty($sponsor['target_audience'])) {
        $sponsor['target_audience'] = json_decode($sponsor['target_audience'], true);
    } else {
        $sponsor['target_audience'] = ['age_range' => '', 'gender' => ''];
    }
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento dello sponsor: " . $e->getMessage();
}

// =============================================
// ELENCO CATEGORIE DINAMICHE E PIATTAFORME
// =============================================

// Carica le categorie dal database
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
    
    // Fallback alle categorie statiche se non ci sono categorie nel database
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
    error_log("Errore nel caricamento delle categorie: " . $e->getMessage());
    
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
// MAPPA CATEGORIE UNIFICATE (per retrocompatibilità)
// =============================================
$category_mapping = [
    'lifestyle' => 'Lifestyle',
    'fashion' => 'Fashion',
    'beauty' => 'Beauty & Makeup',
    'fitness' => 'Fitness & Wellness',
    'travel' => 'Travel',
    'food' => 'Food',
    'tech' => 'Tech',
    'gaming' => 'Gaming'
];

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
        
        // Determina lo stato in base al pulsante cliccato
        $status = $sponsor['status']; // Mantiene lo stato corrente di default
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'save_active') {
                $status = 'active';
            } elseif ($_POST['action'] === 'save_draft') {
                $status = 'draft';
            }
        }

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
        // GESTIONE UPLOAD IMMAGINE
        // =============================================
        $image_url = $sponsor['image_url']; // Mantieni l'immagine corrente di default
        
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
                // Cancella l'immagine vecchia se esiste e non è il placeholder
                if (!empty($sponsor['image_url']) && 
                    $sponsor['image_url'] !== 'sponsor_influencer_preview.png' &&
                    file_exists($upload_dir . $sponsor['image_url'])) {
                    unlink($upload_dir . $sponsor['image_url']);
                }
                $image_url = $filename;
            } else {
                throw new Exception("Errore nel caricamento dell'immagine");
            }
        }
        
        // Checkbox per rimuovere immagine esistente
        if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            if (!empty($sponsor['image_url']) && 
                $sponsor['image_url'] !== 'sponsor_influencer_preview.png' &&
                file_exists($upload_dir . $sponsor['image_url'])) {
                unlink($upload_dir . $sponsor['image_url']);
            }
            $image_url = null;
        }

        // Aggiornamento nel database
        $stmt = $pdo->prepare("
            UPDATE sponsors 
            SET title = ?, 
                image_url = ?, 
                budget = ?, 
                category = ?, 
                description = ?, 
                platforms = ?, 
                target_audience = ?, 
                status = ?,
                updated_at = NOW()
            WHERE id = ? AND influencer_id = ?
        ");
        
        $stmt->execute([
            $title,
            $image_url,
            $budget,
            $category,
            $description,
            json_encode($platforms_selected),
            json_encode($target_audience),
            $status,
            $sponsor_id,
            $influencer['id']
        ]);
        
        // Reindirizza alla lista sponsor con messaggio di successo
        header("Location: sponsors.php?success=sponsor_updated");
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
            <h2>Modifica Sponsor</h2>
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

        <?php if (isset($_GET['success']) && $_GET['success'] == 'sponsor_updated'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Sponsor aggiornato con successo!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Modifica Sponsor -->
        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="sponsorForm">
                    <input type="hidden" name="sponsor_id" value="<?php echo htmlspecialchars($sponsor['id']); ?>">
                    
                    <div class="row">
                        <!-- Informazioni Base -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="title" class="form-label">Titolo sponsor *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($sponsor['title']); ?>" required
                                       placeholder="Es: Collaborazione Instagram Fashion">
                            </div>

                            <div class="mb-3">
                                <label for="budget" class="form-label">Budget *</label>
                                <input type="number" class="form-control" id="budget" name="budget" 
                                       min="0" step="0.01" 
                                       value="<?php echo htmlspecialchars($sponsor['budget']); ?>" required>
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
                                    <?php foreach ($categories as $key => $cat_name): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>" 
                                                <?php echo ($sponsor['category'] === $key) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Piattaforme Social - SPOSTATA SOTTO CATEGORIA -->
                            <div class="mb-3">
                                <label for="platforms" class="form-label">Piattaforme social *</label>
                                <div class="border p-3 rounded">
                                    <?php foreach ($platforms as $key => $platform): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="platforms[]" value="<?php echo $key; ?>" 
                                                   id="platform_<?php echo $key; ?>"
                                                   <?php echo (in_array($key, $sponsor['platforms'])) ? 'checked' : ''; ?>>
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
                                <!-- MODIFICATO: "Immagine di Anteprima" in "Immagine di copertina" -->
                                <label class="form-label">Immagine di copertina</label>
                                
                                <!-- Anteprima immagine corrente -->
                                <?php 
                                $placeholder_path = '/uploads/placeholder/sponsor_influencer_preview.png';
                                if (!empty($sponsor['image_url'])) {
                                    $current_image_path = '/uploads/sponsor/' . htmlspecialchars($sponsor['image_url']);
                                    ?>
                                    <div class="mb-3">
                                        <p><strong>Immagine corrente:</strong></p>
                                        <img src="<?php echo $current_image_path; ?>" 
                                             alt="Immagine sponsor corrente" 
                                             class="img-thumbnail" 
                                             style="max-width: 200px; max-height: 200px;"
                                             onerror="this.onerror=null; this.src='<?php echo $placeholder_path; ?>';">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="remove_image" value="1" id="remove_image">
                                            <label class="form-check-label text-danger" for="remove_image">
                                                Rimuovi immagine corrente
                                            </label>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <p class="text-muted">Nessuna immagine caricata</p>
                                <?php } ?>
                                
                                <!-- Input per nuova immagine -->
                                <label for="image" class="form-label mt-2">Cambia immagine:</label>
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
                                  placeholder="Descrivi la tua proposta di collaborazione, cosa offri ai brand..."><?php echo htmlspecialchars($sponsor['description']); ?></textarea>
                    </div>

                    <!-- Target Audience -->
                    <div class="mb-4">
                        <h5>Target Audience</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="age_range" class="form-label">Fascia d'Età del Tuo Pubblico</label>
                                <input type="text" class="form-control" id="age_range" name="age_range" 
                                       value="<?php echo htmlspecialchars($sponsor['target_audience']['age_range'] ?? ''); ?>" 
                                       placeholder="es. 18-35">
                            </div>
                            <div class="col-md-6">
                                <label for="gender" class="form-label">Genere del Tuo Pubblico</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Tutti</option>
                                    <option value="male" <?php echo (($sponsor['target_audience']['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Maschile</option>
                                    <option value="female" <?php echo (($sponsor['target_audience']['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Femminile</option>
                                    <option value="both" <?php echo (($sponsor['target_audience']['gender'] ?? '') === 'both') ? 'selected' : ''; ?>>Entrambi</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Pulsanti -->
                    <div class="d-flex gap-2">
                        <?php if ($sponsor['status'] === 'draft'): ?>
                            <button type="submit" name="action" value="save_draft" class="btn btn-outline-primary">
                                💾 Aggiorna Bozza
                            </button>
                            <button type="submit" name="action" value="save_active" class="btn btn-primary">
                                🚀 Pubblica Sponsor
                            </button>
                        <?php elseif ($sponsor['status'] === 'active'): ?>
                            <button type="submit" name="action" value="save_active" class="btn btn-primary">
                                Salva modifiche
                            </button>
                        <?php else: ?>
                            <button type="submit" name="action" value="save_active" class="btn btn-primary">
                                Salva modifiche
                            </button>
                        <?php endif; ?>
                        
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
    const removeImageCheckbox = document.getElementById('remove_image');
    
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
                
                // Deseleziona il checkbox "rimuovi immagine" se selezionato
                if (removeImageCheckbox) {
                    removeImageCheckbox.checked = false;
                }
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Funzione per aggiornare l'anteprima con il pulsante di eliminazione
    function updateImagePreview(imageData) {
        imagePreview.innerHTML = `
            <div class="position-relative d-inline-block">
                <p><strong>Nuova immagine:</strong></p>
                <img src="${imageData}" 
                     class="img-thumbnail" 
                     style="max-width: 200px; max-height: 200px;"
                     alt="Anteprima nuova immagine">
                <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                        onclick="removeNewImage()" title="Rimuovi nuova immagine"
                        style="width: 25px; height: 25px; padding: 0; border-radius: 50%;">
                    ×
                </button>
            </div>
        `;
    }
    
    // Funzione per rimuovere la nuova immagine (non quella esistente)
    window.removeNewImage = function() {
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
        
        // Conferma se si sta rimuovendo l'immagine
        if (removeImageCheckbox && removeImageCheckbox.checked) {
            if (!confirm('Sei sicuro di voler rimuovere l\'immagine corrente? Questa azione non può essere annullata.')) {
                e.preventDefault();
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