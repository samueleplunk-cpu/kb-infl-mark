<?php
require_once '../includes/admin_header.php';
require_once '../includes/functions.php';
require_once '../includes/general_settings_functions.php';
require_once '../includes/social_network_functions.php';
require_once '../includes/category_functions.php';
require_once '../includes/system_functions.php';

$page_title = "Impostazioni Generali";
$active_menu = "general-settings";

// Gestione form di salvataggio
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_categories') {
        $result = save_categories_settings($_POST);
    } elseif ($action === 'save_social_networks') {
        $result = save_social_networks_settings($_POST);
    } elseif ($action === 'delete_social_network') {
        $social_id = $_POST['social_id'] ?? '';
        if ($social_id) {
            if (delete_social_network($social_id)) {
                $_SESSION['success_message'] = 'Social network eliminato con successo!';
            } else {
                $_SESSION['error_message'] = 'Errore nell\'eliminazione del social network';
            }
        }
    } elseif ($action === 'delete_category') {
        $category_id = $_POST['category_id'] ?? '';
        if ($category_id) {
            $result = delete_category($pdo, $category_id);
            if ($result['success']) {
                $_SESSION['success_message'] = 'Categoria eliminata con successo!';
            } else {
                $_SESSION['error_message'] = $result['error'];
            }
        }
    } elseif ($action === 'save_system_settings') {
        $result = save_system_settings($_POST);
    }
    
    if (isset($result) && $result['success']) {
        $_SESSION['success_message'] = $result['message'];
    } elseif (isset($result)) {
        $_SESSION['error_message'] = $result['message'];
    }
    
    // Redirect per evitare reinvio form
    header("Location: general-settings.php");
    exit;
}

// Carica le impostazioni correnti
$categories_settings = get_categories_settings();
$social_networks_settings = get_social_networks_settings();
$system_settings = get_system_settings();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Impostazioni Generali</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> Aggiorna
            </button>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $_SESSION['error_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <!-- Nav tabs -->
        <ul class="nav nav-tabs" id="generalSettingsTabs" role="tablist">
            <!-- TAB CATEGORIE (primo tab, attivo di default) -->
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab" aria-controls="categories" aria-selected="true">
                    <i class="fas fa-tags me-2"></i>Categorie
                </button>
            </li>
            <!-- TAB SOCIAL NETWORK (secondo tab) -->
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="social-networks-tab" data-bs-toggle="tab" data-bs-target="#social-networks" type="button" role="tab" aria-controls="social-networks" aria-selected="false">
                    <i class="fas fa-share-alt me-2"></i>Social Network
                </button>
            </li>
            <!-- TAB SISTEMA (terzo e ultimo tab) -->
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab" aria-controls="system" aria-selected="false">
                    <i class="fas fa-cog me-2"></i>Sistema
                </button>
            </li>
        </ul>

        <!-- Tab panes -->
        <div class="tab-content p-4 border border-top-0 rounded-bottom">
            <!-- Tab Categorie -->
            <div class="tab-pane fade show active" id="categories" role="tabpanel" aria-labelledby="categories-tab">
                <form method="POST" id="categoriesForm">
                    <input type="hidden" name="action" value="save_categories">
                    
                    <!-- Sezione Gestione Categorie -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-tags me-2"></i>Gestione Categorie
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Gestisci le categorie disponibili per influencer e brand.</p>
                            <div id="categoriesContainer">
                                <?php
                                $categories = $categories_settings['categories'] ?? [];
                                $counter = 0;
                                
                                foreach ($categories as $category): ?>
                                    <div class="category-item card mb-3">
                                        <div class="card-body">
                                            <?php if (isset($category['id'])): ?>
                                                <input type="hidden" name="categories[<?php echo $counter; ?>][id]" value="<?php echo $category['id']; ?>">
                                            <?php endif; ?>
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Nome Categoria *</label>
                                                    <input type="text" class="form-control category-name" 
                                                           name="categories[<?php echo $counter; ?>][name]" 
                                                           value="<?php echo htmlspecialchars($category['name']); ?>" 
                                                           placeholder="Nome della categoria" required>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Slug *</label>
                                                    <input type="text" class="form-control category-slug" 
                                                           name="categories[<?php echo $counter; ?>][slug]" 
                                                           value="<?php echo htmlspecialchars($category['slug']); ?>" 
                                                           placeholder="slug-categoria" required>
                                                    <div class="form-text">URL-friendly version</div>
                                                </div>
                                                <div class="col-md-2 mb-3">
                                                    <label class="form-label">Ordine</label>
                                                    <input type="number" class="form-control" 
                                                           name="categories[<?php echo $counter; ?>][order]" 
                                                           value="<?php echo htmlspecialchars($category['order'] ?? $category['display_order'] ?? ($counter + 1)); ?>" 
                                                           min="1" required>
                                                </div>
                                                <div class="col-md-2 mb-3">
                                                    <label class="form-label">Stato</label>
                                                    <div class="form-check form-switch mt-2">
                                                        <input type="checkbox" class="form-check-input" 
                                                               name="categories[<?php echo $counter; ?>][active]" 
                                                               value="1" <?php echo (($category['active'] ?? $category['is_active'] ?? true)) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Attiva</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-category" 
                                                            data-category-id="<?php echo $category['id'] ?? ''; ?>" 
                                                            data-category-name="<?php echo htmlspecialchars($category['name']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                $counter++;
                                endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addCategoryItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Categoria
                            </button>
                        </div>
                    </div>

                    <!-- Pulsanti di salvataggio -->
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Salva Categorie
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tab Social Network -->
            <div class="tab-pane fade" id="social-networks" role="tabpanel" aria-labelledby="social-networks-tab">
                <form method="POST" id="socialNetworksForm">
                    <input type="hidden" name="action" value="save_social_networks">
                    
                    <!-- Sezione Gestione Social Network -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-share-alt me-2"></i>Gestione Social Network
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Gestisci i social network disponibili per gli influencer.</p>
                            <div id="socialNetworksContainer">
                                <?php
                                $social_networks = $social_networks_settings['social_networks'] ?? [];
                                
                                foreach ($social_networks as $index => $social): ?>
                                    <div class="social-network-item card mb-3">
                                        <div class="card-body">
                                            <input type="hidden" name="social_networks[<?php echo $index; ?>][id]" value="<?php echo $social['id'] ?? ''; ?>">
                                            <div class="row">
                                                <div class="col-md-2 mb-3">
                                                    <label class="form-label">Nome</label>
                                                    <input type="text" class="form-control" name="social_networks[<?php echo $index; ?>][name]" 
                                                           value="<?php echo htmlspecialchars($social['name']); ?>" 
                                                           placeholder="Nome social" required>
                                                </div>
                                                <div class="col-md-2 mb-3">
                                                    <label class="form-label">Slug</label>
                                                    <input type="text" class="form-control" name="social_networks[<?php echo $index; ?>][slug]" 
                                                           value="<?php echo htmlspecialchars($social['slug']); ?>" 
                                                           placeholder="slug" required>
                                                </div>
                                                <div class="col-md-2 mb-3">
                                                    <label class="form-label">Icona</label>
                                                    <select class="form-select" name="social_networks[<?php echo $index; ?>][icon]">
    <option value="fab fa-instagram" <?php echo ($social['icon'] === 'fab fa-instagram') ? 'selected' : ''; ?>>Instagram</option>
    <option value="fab fa-tiktok" <?php echo ($social['icon'] === 'fab fa-tiktok') ? 'selected' : ''; ?>>TikTok</option>
    <option value="fab fa-youtube" <?php echo ($social['icon'] === 'fab fa-youtube') ? 'selected' : ''; ?>>YouTube</option>
    <option value="fab fa-facebook" <?php echo ($social['icon'] === 'fab fa-facebook') ? 'selected' : ''; ?>>Facebook</option>
    <option value="fab fa-twitter" <?php echo ($social['icon'] === 'fab fa-twitter') ? 'selected' : ''; ?>>Twitter</option>
    <option value="fab fa-linkedin" <?php echo ($social['icon'] === 'fab fa-linkedin') ? 'selected' : ''; ?>>LinkedIn</option>
    <option value="fab fa-pinterest" <?php echo ($social['icon'] === 'fab fa-pinterest') ? 'selected' : ''; ?>>Pinterest</option>
    <option value="fab fa-snapchat" <?php echo ($social['icon'] === 'fab fa-snapchat') ? 'selected' : ''; ?>>Snapchat</option>
    <option value="fab fa-twitch" <?php echo ($social['icon'] === 'fab fa-twitch') ? 'selected' : ''; ?>>Twitch</option>
    <option value="fab fa-telegram" <?php echo ($social['icon'] === 'fab fa-telegram') ? 'selected' : ''; ?>>Telegram</option>
    <option value="fab fa-threads" <?php echo ($social['icon'] === 'fab fa-threads') ? 'selected' : ''; ?>>Threads</option>
</select>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">URL Base</label>
                                                    <input type="text" class="form-control" name="social_networks[<?php echo $index; ?>][base_url]" 
                                                           value="<?php echo htmlspecialchars($social['base_url']); ?>" 
                                                           placeholder="https://..." required>
                                                    <div class="form-text">URL base per i profili</div>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Ordine</label>
                                                    <input type="number" class="form-control" name="social_networks[<?php echo $index; ?>][order]" 
                                                           value="<?php echo htmlspecialchars($social['display_order']); ?>" 
                                                           min="1" required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Stato</label>
                                                    <div class="form-check form-switch mt-2">
                                                        <input type="checkbox" class="form-check-input" 
                                                               name="social_networks[<?php echo $index; ?>][active]" 
                                                               value="1" <?php echo !empty($social['is_active']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Attivo</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end gap-1">
                                                    <?php if (isset($social['id'])): ?>
                                                        <button type="button" class="btn btn-danger btn-sm delete-social-network" 
                                                                data-social-id="<?php echo $social['id']; ?>" 
                                                                data-social-name="<?php echo htmlspecialchars($social['name']); ?>">
                                                            <i class="fas fa-trash"></i> Elimina
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-danger btn-sm remove-social-network">
                                                            <i class="fas fa-trash"></i> Rimuovi
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addSocialNetworkItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Social Network
                            </button>
                        </div>
                    </div>

                    <!-- Pulsanti di salvataggio -->
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Salva Social Network
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Form per eliminazione -->
                <form method="POST" id="deleteSocialNetworkForm" style="display: none;">
                    <input type="hidden" name="action" value="delete_social_network">
                    <input type="hidden" name="social_id" id="delete_social_id">
                </form>
            </div>

            <!-- TAB SISTEMA -->
            <div class="tab-pane fade" id="system" role="tabpanel" aria-labelledby="system-tab">
                <form method="POST" id="systemForm">
                    <input type="hidden" name="action" value="save_system_settings">
                    
                    <!-- Sezione Impostazioni Sistema -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-clock me-2"></i>Impostazioni Data e Ora
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Configura il fuso orario e le impostazioni di data/ora della piattaforma.</p>
                            
                            <div class="row">
                                <!-- Fuso Orario -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Fuso Orario *</label>
                                    <select class="form-select" name="timezone" required>
                                        <?php
                                        $timezones = DateTimeZone::listIdentifiers();
                                        $current_timezone = $system_settings['timezone'] ?? 'Europe/Rome';
                                        foreach ($timezones as $tz):
                                        ?>
                                            <option value="<?php echo htmlspecialchars($tz); ?>" <?php echo $tz === $current_timezone ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tz); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Seleziona il fuso orario della piattaforma</div>
                                </div>
                                
                                <!-- Formato Data -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Formato Data</label>
                                    <select class="form-select" name="date_format">
                                        <?php
                                        $date_formats = [
                                            'd/m/Y' => 'GG/MM/AAAA (italiano)',
                                            'Y-m-d' => 'AAAA-MM-GG (internazionale)',
                                            'm/d/Y' => 'MM/GG/AAAA (USA)',
                                            'd F Y' => 'GG Mese AAAA (esteso)'
                                        ];
                                        $current_date_format = $system_settings['date_format'] ?? 'd/m/Y';
                                        foreach ($date_formats as $format => $label):
                                        ?>
                                            <option value="<?php echo htmlspecialchars($format); ?>" <?php echo $format === $current_date_format ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Formato Ora -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Formato Ora</label>
                                    <select class="form-select" name="time_format">
                                        <?php
                                        $time_formats = [
                                            'H:i' => '24 ore (es: 14:30)',
                                            'h:i A' => '12 ore (es: 02:30 PM)'
                                        ];
                                        $current_time_format = $system_settings['time_format'] ?? 'H:i';
                                        foreach ($time_formats as $format => $label):
                                        ?>
                                            <option value="<?php echo htmlspecialchars($format); ?>" <?php echo $format === $current_time_format ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Sincronizzazione Automatica -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sincronizzazione Orario</label>
                                    <div class="form-check form-switch mt-2">
                                        <input type="checkbox" class="form-check-input" name="auto_sync_time" value="1" <?php echo (!empty($system_settings['auto_sync_time']) && $system_settings['auto_sync_time'] === '1') ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Sincronizza automaticamente con server NTP</label>
                                    </div>
                                    <div class="form-text">Se attivo, sincronizza periodicamente l'orario con server NTP</div>
                                </div>
                            </div>
                            
                            <!-- Anteprima Data/Ora -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h6 class="card-title mb-0">Anteprima Data/Ora</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="timePreview">
                                                <?php
                                                // Mostra l'ora attuale con le impostazioni correnti
                                                $timezone = $system_settings['timezone'] ?? 'Europe/Rome';
                                                $date_format = $system_settings['date_format'] ?? 'd/m/Y';
                                                $time_format = $system_settings['time_format'] ?? 'H:i';
                                                
                                                try {
                                                    $date = new DateTime('now', new DateTimeZone($timezone));
                                                    echo '<p class="mb-1"><strong>Data:</strong> ' . $date->format($date_format) . '</p>';
                                                    echo '<p class="mb-1"><strong>Ora:</strong> ' . $date->format($time_format) . '</p>';
                                                    echo '<p class="mb-0"><strong>Fuso orario:</strong> ' . $timezone . ' (UTC' . $date->format('P') . ')</p>';
                                                } catch (Exception $e) {
                                                    echo '<p class="text-danger">Errore nella visualizzazione dell\'ora</p>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pulsanti di salvataggio -->
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Salva Impostazioni Sistema
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="refreshTimePreview()">
                                <i class="fas fa-sync-alt me-2"></i>Aggiorna Anteprima
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Form per eliminazione categoria -->
<form method="POST" id="deleteCategoryForm" style="display: none;">
    <input type="hidden" name="action" value="delete_category">
    <input type="hidden" name="category_id" id="delete_category_id">
</form>

<script>
let categoryCounter = <?php echo count($categories_settings['categories'] ?? []); ?>;
let socialNetworkCounter = <?php echo count($social_networks_settings['social_networks'] ?? []); ?>;

// Inizializzazione event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Auto-generate slug from category name
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('category-name') && e.target.closest('.category-item')) {
            const nameInput = e.target;
            const slugInput = nameInput.closest('.row').querySelector('.category-slug');
            const nameValue = nameInput.value;
            
            if (!slugInput.value || slugInput.value === nameInput.defaultValue?.toLowerCase().replace(/\s+/g, '-')) {
                const slug = nameValue.toLowerCase()
                    .replace(/\s+/g, '-')
                    .replace(/[^a-z0-9-]/g, '')
                    .replace(/-+/g, '-')
                    .replace(/^-+|-+$/g, '');
                slugInput.value = slug;
            }
        }
    });

    // Gestione eliminazione social network
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-social-network')) {
            const button = e.target.closest('button');
            const socialId = button.getAttribute('data-social-id');
            const socialName = button.getAttribute('data-social-name');
            
            if (confirm(`Sei sicuro di voler eliminare il social network "${socialName}"?`)) {
                document.getElementById('delete_social_id').value = socialId;
                document.getElementById('deleteSocialNetworkForm').submit();
            }
        }
        
        if (e.target.closest('.remove-social-network')) {
            const button = e.target.closest('button');
            removeSocialNetworkItem(button);
        }
    });

    // Gestione eliminazione categoria
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-category')) {
            const button = e.target.closest('button');
            removeCategoryItem(button);
        }
    });
    
    // Aggiorna anteprima timezone quando cambiano i campi
    const systemForm = document.getElementById('systemForm');
    if (systemForm) {
        ['timezone', 'date_format', 'time_format'].forEach(field => {
            const element = systemForm.querySelector(`[name="${field}"]`);
            if (element) {
                element.addEventListener('change', refreshTimePreview);
            }
        });
    }
});

// Funzioni per Categorie
function addCategoryItem() {
    categoryCounter++;
    const container = document.getElementById('categoriesContainer');
    const newItem = document.createElement('div');
    newItem.className = 'category-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Nome Categoria *</label>
                    <input type="text" class="form-control category-name" 
                           name="categories[${categoryCounter}][name]" 
                           placeholder="Nome della categoria" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Slug *</label>
                    <input type="text" class="form-control category-slug" 
                           name="categories[${categoryCounter}][slug]" 
                           placeholder="slug-categoria" required>
                    <div class="form-text">URL-friendly version</div>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Ordine</label>
                    <input type="number" class="form-control" 
                           name="categories[${categoryCounter}][order]" 
                           value="${categoryCounter + 1}" min="1" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Stato</label>
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" class="form-check-input" 
                               name="categories[${categoryCounter}][active]" value="1" checked>
                        <label class="form-check-label">Attiva</label>
                    </div>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-category">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeCategoryItem(button) {
    const item = button.closest('.category-item');
    const categoryId = button.getAttribute('data-category-id');
    const categoryName = button.getAttribute('data-category-name');
    
    if (categoryId && categoryId !== '') {
        if (confirm(`Sei sicuro di voler eliminare la categoria "${categoryName}"?\n\nQuesta azione non può essere annullata.`)) {
            document.getElementById('delete_category_id').value = categoryId;
            document.getElementById('deleteCategoryForm').submit();
        }
    } else {
        if (confirm('Sei sicuro di voler rimuovere questa categoria?')) {
            item.remove();
        }
    }
}

// Funzioni per Social Network
function addSocialNetworkItem() {
    socialNetworkCounter++;
    const container = document.getElementById('socialNetworksContainer');
    const newItem = document.createElement('div');
    newItem.className = 'social-network-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-2 mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" class="form-control" name="social_networks[${socialNetworkCounter}][name]" 
                           placeholder="Nome social" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" class="form-control" name="social_networks[${socialNetworkCounter}][slug]" 
                           placeholder="slug" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Icona</label>
                    <select class="form-select" name="social_networks[${socialNetworkCounter}][icon]">
    <option value="fab fa-instagram">Instagram</option>
    <option value="fab fa-tiktok">TikTok</option>
    <option value="fab fa-youtube">YouTube</option>
    <option value="fab fa-facebook">Facebook</option>
    <option value="fab fa-twitter">Twitter</option>
    <option value="fab fa-linkedin">LinkedIn</option>
    <option value="fab fa-pinterest">Pinterest</option>
    <option value="fab fa-snapchat">Snapchat</option>
    <option value="fab fa-twitch">Twitch</option>
    <option value="fab fa-telegram">Telegram</option>
    <option value="fab fa-threads">Threads</option>
</select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">URL Base</label>
                    <input type="text" class="form-control" name="social_networks[${socialNetworkCounter}][base_url]" 
                           placeholder="https://..." required>
                    <div class="form-text">URL base per i profili</div>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Ordine</label>
                    <input type="number" class="form-control" name="social_networks[${socialNetworkCounter}][order]" 
                           value="${socialNetworkCounter + 1}" min="1" required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Stato</label>
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" class="form-check-input" 
                               name="social_networks[${socialNetworkCounter}][active]" value="1" checked>
                        <label class="form-check-label">Attivo</label>
                    </div>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-social-network">
                        <i class="fas fa-trash"></i> Rimuovi
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeSocialNetworkItem(button) {
    const item = button.closest('.social-network-item');
    item.remove();
}

// Funzione per aggiornare l'anteprima data/ora
function refreshTimePreview() {
    const form = document.getElementById('systemForm');
    const formData = new FormData(form);
    
    // Crea un oggetto con i dati del form
    const data = {};
    for (const [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    // Mostra l'anteprima basata sui valori attuali del form
    try {
        const timezone = data.timezone || 'Europe/Rome';
        const dateFormat = data.date_format || 'd/m/Y';
        const timeFormat = data.time_format || 'H:i';
        
        const now = new Date();
        const date = new Date(now.toLocaleString('en-US', { timeZone: timezone }));
        
        // Formatta la data
        let formattedDate = dateFormat
            .replace('d', String(date.getDate()).padStart(2, '0'))
            .replace('m', String(date.getMonth() + 1).padStart(2, '0'))
            .replace('Y', date.getFullYear())
            .replace('F', date.toLocaleString('it-IT', { month: 'long' }));
        
        // Formatta l'ora
        let hours = date.getHours();
        let minutes = String(date.getMinutes()).padStart(2, '0');
        let formattedTime;
        
        if (timeFormat.includes('A')) {
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            formattedTime = timeFormat
                .replace('h', String(hours).padStart(2, '0'))
                .replace('i', minutes)
                .replace('A', ampm);
        } else {
            formattedTime = timeFormat
                .replace('H', String(hours).padStart(2, '0'))
                .replace('i', minutes);
        }
        
        // Calcola offset UTC
        const offset = -date.getTimezoneOffset() / 60;
        const offsetStr = `UTC${offset >= 0 ? '+' : ''}${offset}:00`;
        
        // Aggiorna l'anteprima
        const preview = document.getElementById('timePreview');
        preview.innerHTML = `
            <p class="mb-1"><strong>Data:</strong> ${formattedDate}</p>
            <p class="mb-1"><strong>Ora:</strong> ${formattedTime}</p>
            <p class="mb-0"><strong>Fuso orario:</strong> ${timezone} (${offsetStr})</p>
        `;
    } catch (error) {
        console.error('Errore aggiornamento anteprima:', error);
        const preview = document.getElementById('timePreview');
        preview.innerHTML = '<p class="text-danger">Errore nella visualizzazione dell\'anteprima</p>';
    }
}
</script>

<?php require_once '../includes/admin_footer.php'; ?>