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
// INCLUSIONE FUNZIONI CATEGORIE
// =============================================
require_once dirname(__DIR__) . '/includes/category_functions.php';

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
// RECUPERO DATI BRAND ESISTENTI
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
// VERIFICA SE LA COLONNA LOGO ESISTE
// =============================================
$logo_column_exists = false;
try {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM brands LIKE 'logo'");
    $stmt->execute();
    $logo_column_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    // Se c'è un errore, assumiamo che la colonna non esista
    $logo_column_exists = false;
}

// =============================================
// RECUPERO CATEGORIE DAL DATABASE
// =============================================
$industries = get_active_categories($pdo);

// =============================================
// LISTA COMPLETA DELLE NAZIONI
// =============================================
$countries = [
    'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua e Barbuda', 
    'Argentina', 'Armenia', 'Australia', 'Austria', 'Azerbaigian', 'Bahamas', 
    'Bahrain', 'Bangladesh', 'Barbados', 'Bielorussia', 'Belgio', 'Belize', 
    'Benin', 'Bhutan', 'Bolivia', 'Bosnia ed Erzegovina', 'Botswana', 'Brasile', 
    'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cambogia', 'Camerun', 
    'Canada', 'Capo Verde', 'Repubblica Centrafricana', 'Ciad', 'Cile', 'Cina', 
    'Colombia', 'Comore', 'Congo', 'Costa Rica', 'Croazia', 'Cuba', 'Cipro', 
    'Repubblica Ceca', 'Danimarca', 'Gibuti', 'Dominica', 'Repubblica Dominicana', 
    'Timor Est', 'Ecuador', 'Egitto', 'El Salvador', 'Guinea Equatoriale', 
    'Eritrea', 'Estonia', 'Etiopia', 'Figi', 'Finlandia', 'Francia', 'Gabon', 
    'Gambia', 'Georgia', 'Germania', 'Ghana', 'Grecia', 'Grenada', 'Guatemala', 
    'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti', 'Honduras', 'Ungheria', 
    'Islanda', 'India', 'Indonesia', 'Iran', 'Iraq', 'Irlanda', 'Israele', 
    'Italia', 'Giamaica', 'Giamaica', 'Giordania', 'Kazakistan', 'Kenya', 
    'Kiribati', 'Kuwait', 'Kirghizistan', 'Laos', 'Lettonia', 'Libano', 
    'Lesotho', 'Liberia', 'Libia', 'Liechtenstein', 'Lituania', 'Lussemburgo', 
    'Madagascar', 'Malawi', 'Malaysia', 'Maldive', 'Mali', 'Malta', 
    'Isole Marshall', 'Mauritania', 'Mauritius', 'Messico', 'Micronesia', 
    'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Marocco', 'Mozambico', 
    'Myanmar', 'Namibia', 'Nauru', 'Nepal', 'Paesi Bassi', 'Nuova Zelanda', 
    'Nicaragua', 'Niger', 'Nigeria', 'Corea del Nord', 'Macedonia del Nord', 
    'Norvegia', 'Oman', 'Pakistan', 'Palau', 'Panama', 'Papua Nuova Guinea', 
    'Paraguay', 'Peru', 'Filippine', 'Polonia', 'Portogallo', 'Qatar', 
    'Romania', 'Russia', 'Ruanda', 'Saint Kitts e Nevis', 'Santa Lucia', 
    'Saint Vincent e Grenadine', 'Samoa', 'San Marino', 'Sao Tome e Principe', 
    'Arabia Saudita', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 
    'Singapore', 'Slovacchia', 'Slovenia', 'Isole Salomone', 'Somalia', 
    'Sudafrica', 'Corea del Sud', 'Sudan del Sud', 'Spagna', 'Sri Lanka', 
    'Sudan', 'Suriname', 'Svezia', 'Svizzera', 'Siria', 'Taiwan', 'Tagikistan', 
    'Tanzania', 'Thailandia', 'Togo', 'Tonga', 'Trinidad e Tobago', 'Tunisia', 
    'Turchia', 'Turkmenistan', 'Tuvalu', 'Uganda', 'Ucraina', 
    'Emirati Arabi Uniti', 'Regno Unito', 'Stati Uniti', 'Uruguay', 
    'Uzbekistan', 'Vanuatu', 'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 
    'Zimbabwe'
];

// =============================================
// GESTIONE INVIO FORM
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitizzazione input
        $company_name = trim($_POST['company_name']);
        $industry = $_POST['industry'];
        $nationality = trim($_POST['nationality']);
        $description = trim($_POST['description']);
        
        // Validazione
        if (empty($company_name)) {
            throw new Exception("Il nome dell'azienda è obbligatorio");
        }

        if (empty($industry)) {
            throw new Exception("Il settore è obbligatorio");
        }

        // Validazione nazionalità obbligatoria
        if (empty($nationality)) {
            throw new Exception("La nazionalità è obbligatoria");
        }

        // Verifica che la nazionalità sia valida
        if (!in_array($nationality, $countries)) {
            throw new Exception("Nazionalità selezionata non valida");
        }

        // Verifica che la categoria selezionata esista e sia attiva
        $valid_categories = array_column($industries, 'name');
        if (!in_array($industry, $valid_categories)) {
            throw new Exception("Categoria selezionata non valida");
        }

        // Gestione upload logo (solo se la colonna esiste)
        $logo_path = $brand['logo'] ?? null;
        
        if ($logo_column_exists && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo_file = $_FILES['logo'];
            
            // Validazione file
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // MODIFICATO: 2MB invece di 5MB
            
            if (!in_array($logo_file['type'], $allowed_types)) {
                throw new Exception("Formato file non supportato. Usa JPG, PNG o GIF.");
            }
            
            if ($logo_file['size'] > $max_size) {
                throw new Exception("Il file è troppo grande. Dimensione massima: 2MB."); // MODIFICATO: messaggio aggiornato
            }
            
            // Crea directory se non esiste
            $upload_dir = dirname(__DIR__) . '/uploads/brands/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Genera nome file univoco
            $file_extension = pathinfo($logo_file['name'], PATHINFO_EXTENSION);
            $filename = 'brand_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
            $logo_path = 'uploads/brands/' . $filename;
            $full_path = dirname(__DIR__) . '/' . $logo_path;
            
            // Sposta file
            if (!move_uploaded_file($logo_file['tmp_name'], $full_path)) {
                throw new Exception("Errore nel caricamento del file.");
            }
            
            // Elimina vecchio logo se esiste
            if (!empty($brand['logo']) && file_exists(dirname(__DIR__) . '/' . $brand['logo'])) {
                unlink(dirname(__DIR__) . '/' . $brand['logo']);
            }
        }
        
        // Gestione rimozione logo (solo se la colonna esiste)
        if ($logo_column_exists && isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1' && !empty($brand['logo'])) {
            if (file_exists(dirname(__DIR__) . '/' . $brand['logo'])) {
                unlink(dirname(__DIR__) . '/' . $brand['logo']);
            }
            $logo_path = null;
        }
        
        // Costruisci query dinamica in base alle colonne disponibili
        $update_fields = [
            "company_name = ?",
            "industry = ?", 
            "nationality = ?",
            "description = ?",
            "updated_at = NOW()"
        ];
        
        $update_params = [
            $company_name,
            $industry,
            $nationality,
            $description
        ];
        
        // Aggiungi logo solo se la colonna esiste
        if ($logo_column_exists) {
            $update_fields[] = "logo = ?";
            $update_params[] = $logo_path;
        }
        
        $update_params[] = $_SESSION['user_id'];
        
        $update_query = "UPDATE brands SET " . implode(', ', $update_fields) . " WHERE user_id = ?";
        
        // Aggiornamento nel database
        $stmt = $pdo->prepare($update_query);
        $stmt->execute($update_params);

        $success = "Profilo aggiornato con successo!";
        
        // Ricarica i dati del brand
        $stmt = $pdo->prepare("SELECT * FROM brands WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $brand = $stmt->fetch(PDO::FETCH_ASSOC);

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
            <h2>Modifica Profilo Brand</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                ← Torna alla Dashboard
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

        <!-- Form Modifica Profilo -->
        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="profileForm">
                    <div class="row">
                        <!-- Informazioni Base -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company_name" class="form-label">Nome azienda *</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                       value="<?php echo htmlspecialchars($brand['company_name'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="industry" class="form-label">Categoria *</label>
                                <select class="form-select" id="industry" name="industry" required>
                                    <option value="">Seleziona un settore</option>
                                    <?php foreach ($industries as $industry_option): ?>
                                        <option value="<?php echo htmlspecialchars($industry_option['name']); ?>" 
                                                <?php echo (($brand['industry'] ?? '') === $industry_option['name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($industry_option['name']); ?>
                                            <?php if (!empty($industry_option['description'])): ?>
                                                - <?php echo htmlspecialchars($industry_option['description']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- NUOVO CAMPO: NAZIONALITÀ (sostituisce il sito web) -->
                            <div class="mb-3">
                                <label for="nationality" class="form-label">Nazionalità *</label>
                                <select class="form-select" id="nationality" name="nationality" required>
                                    <option value="">Seleziona una nazionalità</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?php echo htmlspecialchars($country); ?>" 
                                            <?php echo ($brand['nationality'] ?? '') === $country ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($country); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Upload Logo (solo se la colonna esiste) -->
                        <?php if ($logo_column_exists): ?>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Logo aziendale</label>
                                
                                <!-- Area Upload Logo -->
                                <div id="logoUploadArea">
                                    <!-- Logo attuale o placeholder -->
                                    <div id="currentLogoSection" class="mb-3">
                                        <?php 
                                        // Determina quale immagine mostrare
                                        $current_logo_url = '/uploads/placeholder/brand_admin_edit.png';
                                        $has_custom_logo = false;
                                        
                                        if (!empty($brand['logo'])) {
                                            // Verifica se il file esiste fisicamente
                                            $logo_full_path = dirname(__DIR__) . '/' . $brand['logo'];
                                            if (file_exists($logo_full_path)) {
                                                $current_logo_url = '/' . $brand['logo'];
                                                $has_custom_logo = true;
                                            }
                                        }
                                        ?>
                                        
                                        <img src="<?php echo htmlspecialchars($current_logo_url); ?>" 
                                             alt="<?php echo $has_custom_logo ? 'Logo attuale' : 'Logo'; ?>" 
                                             class="img-thumbnail mb-2" 
                                             style="max-height: 150px;">
                                        
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="changeLogoBtn">
                                                <?php if ($has_custom_logo): ?>
                                                    Cambia immagine
                                                <?php else: ?>
                                                    📁 Carica Immagine Personalizzata
                                                <?php endif; ?>
                                            </button>
                                            
                                            <?php if ($has_custom_logo): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm" id="removeCurrentLogoBtn">
                                                    Rimuovi immagine
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Input file nascosto -->
                                    <input type="file" class="form-control d-none" id="logo" name="logo" 
                                           accept="image/jpeg,image/jpg,image/png,image/gif">
                                    
                                    <!-- Anteprima nuovo logo con controlli -->
                                    <div id="logoPreviewContainer" class="mt-2" style="display: none;">
                                        <p class="text-muted">Anteprima nuovo logo:</p>
                                        <div class="position-relative d-inline-block">
                                            <img id="previewImage" class="img-thumbnail" style="max-height: 150px;">
                                        </div>
                                        <div class="d-flex gap-2 mt-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="changeImageBtn">
                                                Cambia immagine
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm" id="removeImageBtn">
                                                Rimuovi immagine
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-text">
                                    Formati supportati: JPG, PNG, GIF. Dimensione massima: 2MB.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Descrizione Azienda -->
                    <div class="mb-4">
                        <label for="description" class="form-label">Presenta il tuo Brand agli Influencer</label>
                        <textarea class="form-control" id="description" name="description" rows="6" 
                                  placeholder="Descrivi la tua azienda, la tua mission, i tuoi valori..."><?php echo htmlspecialchars($brand['description'] ?? ''); ?></textarea>
                        <div class="form-text">
                            Una buona descrizione aiuta gli influencer a comprendere meglio la tua azienda e aumenta le possibilità di collaborazione.
                        </div>
                    </div>

                    <!-- Pulsanti -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            Aggiorna profilo
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">Annulla</a>
                    </div>
                    
                    <!-- Campo hidden per rimozione logo -->
                    <?php if ($logo_column_exists): ?>
                    <input type="hidden" name="remove_logo" id="removeLogoField" value="0">
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('profileForm');
    const logoInput = document.getElementById('logo');
    const logoPreviewContainer = document.getElementById('logoPreviewContainer');
    const previewImage = document.getElementById('previewImage');
    const removeLogoField = document.getElementById('removeLogoField');
    const currentLogoSection = document.getElementById('currentLogoSection');
    
    // Elementi dei pulsanti
    const changeLogoBtn = document.getElementById('changeLogoBtn');
    const removeCurrentLogoBtn = document.getElementById('removeCurrentLogoBtn');
    const changeImageBtn = document.getElementById('changeImageBtn');
    const removeImageBtn = document.getElementById('removeImageBtn');
    
    // Gestione click su "Cambia Immagine" (logo attuale)
    if (changeLogoBtn) {
        changeLogoBtn.addEventListener('click', function() {
            logoInput.click();
        });
    }
    
    // Gestione click su "Rimuovi Immagine" (logo attuale)
    if (removeCurrentLogoBtn) {
        removeCurrentLogoBtn.addEventListener('click', function() {
            if (confirm('Sei sicuro di voler rimuovere il logo attuale?')) {
                removeLogoField.value = '1';
                // Aggiorna la sezione del logo corrente con placeholder
                showPlaceholderLogo();
            }
        });
    }
    
    // Gestione click su "Cambia Immagine" (anteprima)
    if (changeImageBtn) {
        changeImageBtn.addEventListener('click', function() {
            logoInput.click();
        });
    }
    
    // Gestione click su "Rimuovi Immagine" (anteprima)
    if (removeImageBtn) {
        removeImageBtn.addEventListener('click', function() {
            resetLogoInput();
            // Se c'era un logo attuale, ripristinalo
            if (currentLogoSection && removeLogoField.value === '0') {
                logoPreviewContainer.style.display = 'none';
                currentLogoSection.style.display = 'block';
            } else {
                logoPreviewContainer.style.display = 'none';
                showPlaceholderLogo();
            }
        });
    }
    
    // Gestione cambio file
    if (logoInput) {
        logoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validazione client-side del file
                const maxSize = 2 * 1024 * 1024; // MODIFICATO: 2MB invece di 5MB
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                
                if (!allowedTypes.includes(file.type)) {
                    alert('Formato file non supportato. Usa JPG, PNG o GIF.');
                    resetLogoInput();
                    return;
                }
                
                if (file.size > maxSize) {
                    alert('Il file è troppo grande. Dimensione massima: 2MB.'); // MODIFICATO: messaggio aggiornato
                    resetLogoInput();
                    return;
                }
                
                // Mostra anteprima
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    logoPreviewContainer.style.display = 'block';
                    
                    // Nascondi la sezione del logo attuale
                    if (currentLogoSection) {
                        currentLogoSection.style.display = 'none';
                    }
                    
                    // Reset del campo remove_logo se stiamo caricando una nuova immagine
                    removeLogoField.value = '0';
                }
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Funzione per resettare l'input file
    function resetLogoInput() {
        if (logoInput) {
            logoInput.value = '';
        }
        removeLogoField.value = '0';
    }
    
    // Funzione per mostrare il placeholder
    function showPlaceholderLogo() {
        if (currentLogoSection) {
            currentLogoSection.innerHTML = `
                <img src="/uploads/placeholder/brand_admin_edit.png" 
                     alt="Logo" 
                     class="img-thumbnail mb-2" 
                     style="max-height: 150px;">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="changeLogoBtn">
                        📁 Carica Immagine Personalizzata
                    </button>
                </div>
            `;
            currentLogoSection.style.display = 'block';
            
            // Re-attach event listener al nuovo pulsante
            document.getElementById('changeLogoBtn').addEventListener('click', function() {
                logoInput.click();
            });
        }
        logoPreviewContainer.style.display = 'none';
    }
    
    // Validazione client-side del form
    form.addEventListener('submit', function(e) {
        const companyName = document.getElementById('company_name').value.trim();
        const industry = document.getElementById('industry').value;
        const nationality = document.getElementById('nationality').value;
        
        if (!companyName) {
            e.preventDefault();
            alert('Il nome dell\'azienda è obbligatorio');
            document.getElementById('company_name').focus();
            return false;
        }
        
        if (!industry) {
            e.preventDefault();
            alert('Il settore è obbligatorio');
            document.getElementById('industry').focus();
            return false;
        }
        
        // Validazione nazionalità obbligatoria
        if (!nationality) {
            e.preventDefault();
            alert('La nazionalità è obbligatoria');
            document.getElementById('nationality').focus();
            return false;
        }
        
        // Validazione file se selezionato
        if (logoInput && logoInput.files.length > 0) {
            const file = logoInput.files[0];
            const maxSize = 2 * 1024 * 1024; // MODIFICATO: 2MB invece di 5MB
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            
            if (!allowedTypes.includes(file.type)) {
                e.preventDefault();
                alert('Formato file non supportato. Usa JPG, PNG o GIF.');
                return false;
            }
            
            if (file.size > maxSize) {
                e.preventDefault();
                alert('Il file è troppo grande. Dimensione massima: 2MB.'); // MODIFICATO: messaggio aggiornato
                return false;
            }
        }
    });

    // Notifica se non ci sono categorie disponibili
    const industrySelect = document.getElementById('industry');
    if (industrySelect && industrySelect.options.length <= 1) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-warning mt-2';
        alertDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Nessuna categoria disponibile. Contatta l\'amministratore del sistema.';
        industrySelect.parentNode.appendChild(alertDiv);
    }
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
.img-thumbnail {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.25rem;
}
.btn-sm {
    font-size: 0.875rem;
    padding: 0.25rem 0.5rem;
}
.badge {
    font-size: 0.75em;
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