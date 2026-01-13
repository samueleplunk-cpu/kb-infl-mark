<?php
require_once '../includes/admin_header.php';
require_once '../includes/functions.php';

// Includi funzioni specifiche per la gestione delle pagine
require_once '../includes/page_functions.php';

$page_title = "Gestione Pagine e Menu";
$active_menu = "pages-menu";

// Gestione form di salvataggio
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_footer_settings') {
        $result = save_footer_settings($_POST, $_FILES);
    } elseif ($action === 'save_header_settings') {
        $result = save_header_settings($_POST, $_FILES);
    } elseif ($action === 'save_header_brands_settings') {
        $result = save_header_brands_settings($_POST, $_FILES);
    } elseif ($action === 'save_header_influencers_settings') {
        $result = save_header_influencers_settings($_POST, $_FILES);
    } elseif ($action === 'save_footer_bi_settings') {
        $result = save_footer_bi_settings($_POST, $_FILES);
    }
    
    if (isset($result) && $result['success']) {
        $_SESSION['success_message'] = $result['message'];
    } elseif (isset($result)) {
        $_SESSION['error_message'] = $result['message'];
    }
    
    // Redirect per evitare reinvio form
    header("Location: pages-menu.php");
    exit;
}

// Carica le impostazioni correnti
$footer_settings = get_footer_settings();
$header_settings = get_header_settings();
$header_brands_settings = get_header_brands_settings();
$header_influencers_settings = get_header_influencers_settings();
$footer_bi_settings = get_footer_bi_settings();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestione Pagine e Menu</h1>
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

<!-- Modal di Conferma Eliminazione -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmModalLabel">Conferma Eliminazione</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Sei sicuro di voler eliminare questo collegamento?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Conferma Eliminazione</button>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <!-- Nav tabs -->
        <ul class="nav nav-tabs" id="pagesMenuTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="header-tab" data-bs-toggle="tab" data-bs-target="#header" type="button" role="tab" aria-controls="header" aria-selected="true">
                    <i class="fas fa-heading me-2"></i>Header Homepage
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="footer-tab" data-bs-toggle="tab" data-bs-target="#footer" type="button" role="tab" aria-controls="footer" aria-selected="false">
                    <i class="fas fa-shoe-prints me-2"></i>Footer Homepage
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="header-brands-tab" data-bs-toggle="tab" data-bs-target="#header-brands" type="button" role="tab" aria-controls="header-brands" aria-selected="false">
                    <i class="fas fa-users me-2"></i>Header Brands
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="header-influencers-tab" data-bs-toggle="tab" data-bs-target="#header-influencers" type="button" role="tab" aria-controls="header-influencers" aria-selected="false">
                    <i class="fas fa-user-friends me-2"></i>Header Influencers
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="footer-bi-tab" data-bs-toggle="tab" data-bs-target="#footer-bi" type="button" role="tab" aria-controls="footer-bi" aria-selected="false">
                    <i class="fas fa-shoe-prints me-2"></i>Footer B&I
                </button>
            </li>
        </ul>

        <!-- Tab panes -->
        <div class="tab-content p-4 border border-top-0 rounded-bottom">
            <!-- Tab Header -->
            <div class="tab-pane fade show active" id="header" role="tabpanel" aria-labelledby="header-tab">
                <form method="POST" id="headerForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_header_settings">
                    
                    <!-- Sezione Logo -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-image me-2"></i>Logo Header
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="header_logo_text" class="form-label">Testo Logo (Fallback)</label>
                                    <input type="text" class="form-control" id="header_logo_text" name="header_logo_text" 
                                           value="<?php echo htmlspecialchars($header_settings['logo_text'] ?? 'Kibbiz'); ?>" 
                                           placeholder="Inserisci il testo del logo" required>
                                    <div class="form-text">Questo testo verrà mostrato se non viene caricata un'immagine logo</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="header_logo" class="form-label">Immagine Logo</label>
                                    <input type="file" class="form-control" id="header_logo" name="header_logo" 
                                           accept="image/*">
                                    <div class="form-text">
                                        Carica un'immagine per il logo. Dimensioni consigliate: 150x50px. 
                                        Formati supportati: JPG, PNG, GIF, WebP.
                                        <?php if (!empty($header_settings['logo_url'])): ?>
                                            <br>
                                            <strong>Logo attuale:</strong>
                                            <img src="<?php echo htmlspecialchars($header_settings['logo_url']); ?>" 
                                                 alt="Logo Header" style="max-height: 50px; margin-left: 10px;">
                                            <br>
                                            <label class="mt-2">
                                                <input type="checkbox" name="remove_header_logo" value="1">
                                                Rimuovi logo attuale
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sezione Menu di Navigazione -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-bars me-2"></i>Menu di Navigazione
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="navMenuContainer">
                                <?php
                                $nav_menus = $header_settings['nav_menus'] ?? [
                                    ['label' => 'Funzionalità', 'url' => '#features', 'target_blank' => false],
                                    ['label' => 'Come Funziona', 'url' => '#how-it-works', 'target_blank' => false],
                                    ['label' => 'Chi Siamo', 'url' => '#about', 'target_blank' => false]
                                ];
                                
                                foreach ($nav_menus as $index => $menu): ?>
                                    <div class="nav-menu-item card mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Etichetta</label>
                                                    <input type="text" class="form-control" name="nav_menus[<?php echo $index; ?>][label]" 
                                                           value="<?php echo htmlspecialchars($menu['label']); ?>" 
                                                           placeholder="Nome del menu" required>
                                                </div>
                                                <div class="col-md-5 mb-3">
                                                    <label class="form-label">URL</label>
                                                    <input type="text" class="form-control" name="nav_menus[<?php echo $index; ?>][url]" 
                                                           value="<?php echo htmlspecialchars($menu['url']); ?>" 
                                                           placeholder="https://..." required>
                                                </div>
                                                <div class="col-md-2 mb-3">
                                                    <label class="form-label">Target</label>
                                                    <div class="form-check mt-2">
                                                        <input type="checkbox" class="form-check-input" 
                                                               name="nav_menus[<?php echo $index; ?>][target_blank]" 
                                                               value="1" <?php echo !empty($menu['target_blank']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Apri in nuova pagina</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-nav-menu" 
                                                            data-item-type="nav-menu" data-item-label="<?php echo htmlspecialchars($menu['label']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addNavMenuItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Voce Menu
                            </button>
                        </div>
                    </div>

                    <!-- Pulsanti di salvataggio -->
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Salva Impostazioni Header
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tab Footer -->
            <div class="tab-pane fade" id="footer" role="tabpanel" aria-labelledby="footer-tab">
                <form method="POST" id="footerForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_footer_settings">
                    
                    <!-- Sezione Titolo e Descrizione -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-heading me-2"></i>Titolo e Descrizione
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="footer_title" class="form-label">Titolo Footer</label>
                                    <input type="text" class="form-control" id="footer_title" name="footer_title" 
                                           value="<?php echo htmlspecialchars($footer_settings['title'] ?? 'Kibbiz'); ?>" 
                                           placeholder="Inserisci il titolo del footer">
                                    <div class="form-text">Questo sarà il titolo principale nel footer (es. "Kibbiz")</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="footer_description" class="form-label">Descrizione/Sottotitolo</label>
                                    <textarea class="form-control" id="footer_description" name="footer_description" 
                                              rows="3" placeholder="Inserisci la descrizione del footer"><?php echo htmlspecialchars($footer_settings['description'] ?? 'Uniamo Brand e Influencer per crescere insieme.'); ?></textarea>
                                    <div class="form-text">Breve descrizione sotto il titolo</div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label for="footer_logo" class="form-label">Logo Footer</label>
                                    <input type="file" class="form-control" id="footer_logo" name="footer_logo" 
                                           accept="image/*">
                                    <div class="form-text">
                                        Carica un'immagine per il logo. Dimensioni consigliate: 150x50px. 
                                        Formati supportati: JPG, PNG, GIF, WebP.
                                        <?php if (!empty($footer_settings['logo_url'])): ?>
                                            <br>
                                            <strong>Logo attuale:</strong>
                                            <img src="<?php echo htmlspecialchars($footer_settings['logo_url']); ?>" 
                                                 alt="Logo Footer" style="max-height: 50px; margin-left: 10px;">
                                            <br>
                                            <label class="mt-2">
                                                <input type="checkbox" name="remove_logo" value="1">
                                                Rimuovi logo attuale
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sezione Link Veloci -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-link me-2"></i>Link Veloci
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="quickLinksContainer">
                                <?php
                                $quick_links = $footer_settings['quick_links'] ?? [
                                    ['label' => 'Home', 'url' => '/', 'target_blank' => false],
                                    ['label' => 'Funzionalità', 'url' => '#features', 'target_blank' => false],
                                    ['label' => 'Come Funziona', 'url' => '#how-it-works', 'target_blank' => false],
                                    ['label' => 'Login', 'url' => '/auth/login.php', 'target_blank' => false],
                                    ['label' => 'Registrati', 'url' => '/auth/register.php', 'target_blank' => false]
                                ];
                                
                                foreach ($quick_links as $index => $link): ?>
                                    <div class="quick-link-item card mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Etichetta</label>
                                                    <input type="text" class="form-control" name="quick_links[<?php echo $index; ?>][label]" 
                                                           value="<?php echo htmlspecialchars($link['label']); ?>" 
                                                           placeholder="Nome del link" required>
                                                </div>
                                                <div class="col-md-5 mb-3">
                                                    <label class="form-label">URL</label>
                                                    <input type="text" class="form-control" name="quick_links[<?php echo $index; ?>][url]" 
                                                           value="<?php echo htmlspecialchars($link['url']); ?>" 
                                                           placeholder="https://..." required>
                                                </div>
                                                <div class="col-md-2 mb-3">
                                                    <label class="form-label">Target</label>
                                                    <div class="form-check mt-2">
                                                        <input type="checkbox" class="form-check-input" 
                                                               name="quick_links[<?php echo $index; ?>][target_blank]" 
                                                               value="1" <?php echo !empty($link['target_blank']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Apri in nuova pagina</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-quick-link" 
                                                            data-item-type="quick-link" data-item-label="<?php echo htmlspecialchars($link['label']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addQuickLinkItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Link Veloce
                            </button>
                        </div>
                    </div>

                    <!-- Sezione Supporto -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-life-ring me-2"></i>Supporto
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="supportLinksContainer">
                                <?php
                                $support_links = $footer_settings['support_links'] ?? [
                                    ['label' => 'Contattaci', 'url' => '#', 'target_blank' => false],
                                    ['label' => 'FAQ', 'url' => '#', 'target_blank' => false],
                                    ['label' => 'Privacy Policy', 'url' => '#', 'target_blank' => false],
                                    ['label' => 'Termini di Servizio', 'url' => '#', 'target_blank' => false]
                                ];
                                
                                foreach ($support_links as $index => $link): ?>
                                    <div class="support-link-item card mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Etichetta</label>
                                                    <input type="text" class="form-control" name="support_links[<?php echo $index; ?>][label]" 
                                                           value="<?php echo htmlspecialchars($link['label']); ?>" 
                                                           placeholder="Nome del link" required>
                                                </div>
                                                <div class="col-md-5 mb-3">
                                                    <label class="form-label">URL</label>
                                                    <input type="text" class="form-control" name="support_links[<?php echo $index; ?>][url]" 
                                                           value="<?php echo htmlspecialchars($link['url']); ?>" 
                                                           placeholder="https://..." required>
                                                </div>
                                                <div class="col-md-2 mb-3">
                                                    <label class="form-label">Target</label>
                                                    <div class="form-check mt-2">
                                                        <input type="checkbox" class="form-check-input" 
                                                               name="support_links[<?php echo $index; ?>][target_blank]" 
                                                               value="1" <?php echo !empty($link['target_blank']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Apri in nuova pagina</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-support-link" 
                                                            data-item-type="support-link" data-item-label="<?php echo htmlspecialchars($link['label']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addSupportLinkItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Link Supporto
                            </button>
                        </div>
                    </div>

                    <!-- Sezione Social Media -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-share-alt me-2"></i>Seguici su - Social Media
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="socialMediaContainer">
                                <?php
                                $social_links = $footer_settings['social_links'] ?? [
                                    ['platform' => 'instagram', 'url' => '#', 'icon' => 'fab fa-instagram'],
                                    ['platform' => 'tiktok', 'url' => '#', 'icon' => 'fab fa-tiktok'],
                                    ['platform' => 'linkedin', 'url' => '#', 'icon' => 'fab fa-linkedin']
                                ];
                                
                                foreach ($social_links as $index => $social): ?>
                                    <div class="social-media-item card mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Piattaforma</label>
                                                    <select class="form-select social-platform" name="social_links[<?php echo $index; ?>][platform]">
                                                        <option value="instagram" <?php echo ($social['platform'] === 'instagram') ? 'selected' : ''; ?>>Instagram</option>
                                                        <option value="tiktok" <?php echo ($social['platform'] === 'tiktok') ? 'selected' : ''; ?>>TikTok</option>
                                                        <option value="linkedin" <?php echo ($social['platform'] === 'linkedin') ? 'selected' : ''; ?>>LinkedIn</option>
                                                        <option value="facebook" <?php echo ($social['platform'] === 'facebook') ? 'selected' : ''; ?>>Facebook</option>
                                                        <option value="twitter" <?php echo ($social['platform'] === 'twitter') ? 'selected' : ''; ?>>Twitter</option>
                                                        <option value="youtube" <?php echo ($social['platform'] === 'youtube') ? 'selected' : ''; ?>>YouTube</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Icona</label>
                                                    <select class="form-select social-icon" name="social_links[<?php echo $index; ?>][icon]">
                                                        <option value="fab fa-instagram" <?php echo ($social['icon'] === 'fab fa-instagram') ? 'selected' : ''; ?>>Instagram</option>
                                                        <option value="fab fa-tiktok" <?php echo ($social['icon'] === 'fab fa-tiktok') ? 'selected' : ''; ?>>TikTok</option>
                                                        <option value="fab fa-linkedin" <?php echo ($social['icon'] === 'fab fa-linkedin') ? 'selected' : ''; ?>>LinkedIn</option>
                                                        <option value="fab fa-facebook" <?php echo ($social['icon'] === 'fab fa-facebook') ? 'selected' : ''; ?>>Facebook</option>
                                                        <option value="fab fa-twitter" <?php echo ($social['icon'] === 'fab fa-twitter') ? 'selected' : ''; ?>>Twitter</option>
                                                        <option value="fab fa-youtube" <?php echo ($social['icon'] === 'fab fa-youtube') ? 'selected' : ''; ?>>YouTube</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-5 mb-3">
                                                    <label class="form-label">URL Profilo</label>
                                                    <input type="url" class="form-control" name="social_links[<?php echo $index; ?>][url]" 
                                                           value="<?php echo htmlspecialchars($social['url']); ?>" 
                                                           placeholder="https://...">
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-social" 
                                                            data-item-type="social" data-item-label="<?php echo htmlspecialchars($social['platform']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addSocialItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Social
                            </button>
                        </div>
                    </div>
					
					                    <!-- Copyright -->
                    <div class="card mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-copyright me-2"></i>Testo Copyright
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="footer_copyright" class="form-label">Testo Copyright</label>
                                    <input type="text" class="form-control" id="footer_copyright" name="footer_copyright" 
                                           value="<?php echo htmlspecialchars($footer_settings['copyright'] ?? '© Kibbiz {year}. Tutti i diritti riservati.'); ?>" 
                                           placeholder="Inserisci il testo del copyright">
                                    <div class="form-text">
                                        Personalizza il testo del copyright. Usa il segnaposto <code>{year}</code> per visualizzare automaticamente l'anno corrente.
                                        Esempio: "© Kibbiz {year}. Tutti i diritti riservati." diventerà "© Kibbiz 2025. Tutti i diritti riservati."
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pulsanti di salvataggio -->
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Salva Impostazioni Footer
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tab Header Brands -->
            <div class="tab-pane fade" id="header-brands" role="tabpanel" aria-labelledby="header-brands-tab">
                <form method="POST" id="headerBrandsForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_header_brands_settings">
                    
                    <!-- Sezione Logo Header Brands - NUOVA SEZIONE -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-image me-2"></i>Logo Header Brands
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="header_brands_logo_text" class="form-label">Testo Logo (Fallback)</label>
                                    <input type="text" class="form-control" id="header_brands_logo_text" name="header_brands_logo_text" 
                                           value="<?php echo htmlspecialchars($header_brands_settings['logo_text'] ?? 'Kibbiz'); ?>" 
                                           placeholder="Inserisci il testo del logo" required>
                                    <div class="form-text">Questo testo verrà mostrato se non viene caricata un'immagine logo</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="header_brands_logo" class="form-label">Immagine Logo</label>
                                    <input type="file" class="form-control" id="header_brands_logo" name="header_brands_logo" 
                                           accept="image/*">
                                    <div class="form-text">
                                        Carica un'immagine per il logo. Dimensioni consigliate: 150x50px. 
                                        Formati supportati: JPG, PNG, GIF, WebP.
                                        <?php if (!empty($header_brands_settings['logo_url'])): ?>
                                            <br>
                                            <strong>Logo attuale:</strong>
                                            <img src="<?php echo htmlspecialchars($header_brands_settings['logo_url']); ?>" 
                                                 alt="Logo Header Brands" style="max-height: 50px; margin-left: 10px;">
                                            <br>
                                            <label class="mt-2">
                                                <input type="checkbox" name="remove_header_brands_logo" value="1">
                                                Rimuovi logo attuale
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sezione Menu Principale Brands -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-bars me-2"></i>Menu Principale Brands
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Gestisci le voci del menu principale che i brand visualizzano dopo il login.</p>
                            <div id="mainMenuContainer">
                                <?php
                                $main_menus = $header_brands_settings['main_menus'] ?? [
                                    ['label' => 'Dashboard', 'url' => '/brands/dashboard.php', 'target_blank' => false, 'order' => 1, 'icon' => 'fas fa-tachometer-alt'],
                                    ['label' => 'Campagne', 'url' => '/brands/campaigns.php', 'target_blank' => false, 'order' => 2, 'icon' => 'fas fa-bullhorn'],
                                    ['label' => 'Messaggi', 'url' => '/brands/messages/conversation-list.php', 'target_blank' => false, 'order' => 3, 'icon' => 'fas fa-envelope'],
                                    ['label' => 'Cerca Influencer', 'url' => '/brands/search-influencers.php', 'target_blank' => false, 'order' => 4, 'icon' => 'fas fa-search']
                                ];
                                
                                foreach ($main_menus as $index => $menu): ?>
                                    <div class="main-menu-item card mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Etichetta</label>
                                                    <input type="text" class="form-control" name="main_menus[<?php echo $index; ?>][label]" 
                                                           value="<?php echo htmlspecialchars($menu['label']); ?>" 
                                                           placeholder="Nome del menu" required>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Icona</label>
                                                    <select class="form-select" name="main_menus[<?php echo $index; ?>][icon]">
                                                        <option value="">Nessuna icona</option>
                                                        <option value="fas fa-tachometer-alt" <?php echo ($menu['icon'] ?? '') === 'fas fa-tachometer-alt' ? 'selected' : ''; ?>>Dashboard</option>
                                                        <option value="fas fa-bullhorn" <?php echo ($menu['icon'] ?? '') === 'fas fa-bullhorn' ? 'selected' : ''; ?>>Campagne</option>
                                                        <option value="fas fa-envelope" <?php echo ($menu['icon'] ?? '') === 'fas fa-envelope' ? 'selected' : ''; ?>>Messaggi</option>
                                                        <option value="fas fa-search" <?php echo ($menu['icon'] ?? '') === 'fas fa-search' ? 'selected' : ''; ?>>Cerca</option>
                                                        <option value="fas fa-chart-bar" <?php echo ($menu['icon'] ?? '') === 'fas fa-chart-bar' ? 'selected' : ''; ?>>Analytics</option>
                                                        <option value="fas fa-users" <?php echo ($menu['icon'] ?? '') === 'fas fa-users' ? 'selected' : ''; ?>>Utenti</option>
                                                        <option value="fas fa-cog" <?php echo ($menu['icon'] ?? '') === 'fas fa-cog' ? 'selected' : ''; ?>>Impostazioni</option>
                                                        <option value="fas fa-home" <?php echo ($menu['icon'] ?? '') === 'fas fa-home' ? 'selected' : ''; ?>>Home</option>
                                                        <option value="fas fa-bell" <?php echo ($menu['icon'] ?? '') === 'fas fa-bell' ? 'selected' : ''; ?>>Notifiche</option>
                                                        <option value="fas fa-user" <?php echo ($menu['icon'] ?? '') === 'fas fa-user' ? 'selected' : ''; ?>>Profilo</option>
                                                        <option value="fas fa-sign-out-alt" <?php echo ($menu['icon'] ?? '') === 'fas fa-sign-out-alt' ? 'selected' : ''; ?>>Logout</option>
                                                    </select>
                                                    <div class="form-text">Seleziona un'icona per questa voce menu</div>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">URL</label>
                                                    <input type="text" class="form-control" name="main_menus[<?php echo $index; ?>][url]" 
                                                           value="<?php echo htmlspecialchars($menu['url']); ?>" 
                                                           placeholder="https://..." required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Ordine</label>
                                                    <input type="number" class="form-control" name="main_menus[<?php echo $index; ?>][order]" 
                                                           value="<?php echo htmlspecialchars($menu['order']); ?>" 
                                                           min="1" required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Target</label>
                                                    <div class="form-check mt-2">
                                                        <input type="checkbox" class="form-check-input" 
                                                               name="main_menus[<?php echo $index; ?>][target_blank]" 
                                                               value="1" <?php echo !empty($menu['target_blank']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Nuova pagina</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-main-menu" 
                                                            data-item-type="main-menu" data-item-label="<?php echo htmlspecialchars($menu['label']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addMainMenuItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Voce Menu Principale
                            </button>
                        </div>
                    </div>

                    <!-- Sezione Menu Profilo Brands -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user-circle me-2"></i>Menu Profilo Brands
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Gestisci le voci del menu a tendina del profilo per i brand.</p>
                            <div id="profileMenuContainer">
                                <?php
                                $profile_menus = $header_brands_settings['profile_menus'] ?? [
                                    ['label' => 'Impostazioni', 'url' => '/brands/settings.php', 'target_blank' => false, 'order' => 1, 'icon' => 'fas fa-cog'],
                                    ['label' => 'Logout', 'url' => '/auth/logout.php', 'target_blank' => false, 'order' => 2, 'icon' => 'fas fa-sign-out-alt']
                                ];
                                
                                foreach ($profile_menus as $index => $menu): ?>
                                    <div class="profile-menu-item card mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Etichetta</label>
                                                    <input type="text" class="form-control" name="profile_menus[<?php echo $index; ?>][label]" 
                                                           value="<?php echo htmlspecialchars($menu['label']); ?>" 
                                                           placeholder="Nome del menu" required>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Icona</label>
                                                    <select class="form-select" name="profile_menus[<?php echo $index; ?>][icon]">
                                                        <option value="">Nessuna icona</option>
                                                        <option value="fas fa-tachometer-alt" <?php echo ($menu['icon'] ?? '') === 'fas fa-tachometer-alt' ? 'selected' : ''; ?>>Dashboard</option>
                                                        <option value="fas fa-bullhorn" <?php echo ($menu['icon'] ?? '') === 'fas fa-bullhorn' ? 'selected' : ''; ?>>Campagne</option>
                                                        <option value="fas fa-envelope" <?php echo ($menu['icon'] ?? '') === 'fas fa-envelope' ? 'selected' : ''; ?>>Messaggi</option>
                                                        <option value="fas fa-search" <?php echo ($menu['icon'] ?? '') === 'fas fa-search' ? 'selected' : ''; ?>>Cerca</option>
                                                        <option value="fas fa-chart-bar" <?php echo ($menu['icon'] ?? '') === 'fas fa-chart-bar' ? 'selected' : ''; ?>>Analytics</option>
                                                        <option value="fas fa-users" <?php echo ($menu['icon'] ?? '') === 'fas fa-users' ? 'selected' : ''; ?>>Utenti</option>
                                                        <option value="fas fa-cog" <?php echo ($menu['icon'] ?? '') === 'fas fa-cog' ? 'selected' : ''; ?>>Impostazioni</option>
                                                        <option value="fas fa-home" <?php echo ($menu['icon'] ?? '') === 'fas fa-home' ? 'selected' : ''; ?>>Home</option>
                                                        <option value="fas fa-bell" <?php echo ($menu['icon'] ?? '') === 'fas fa-bell' ? 'selected' : ''; ?>>Notifiche</option>
                                                        <option value="fas fa-user" <?php echo ($menu['icon'] ?? '') === 'fas fa-user' ? 'selected' : ''; ?>>Profilo</option>
                                                        <option value="fas fa-sign-out-alt" <?php echo ($menu['icon'] ?? '') === 'fas fa-sign-out-alt' ? 'selected' : ''; ?>>Logout</option>
                                                    </select>
                                                    <div class="form-text">Seleziona un'icona per questa voce menu</div>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">URL</label>
                                                    <input type="text" class="form-control" name="profile_menus[<?php echo $index; ?>][url]" 
                                                           value="<?php echo htmlspecialchars($menu['url']); ?>" 
                                                           placeholder="https://..." required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Ordine</label>
                                                    <input type="number" class="form-control" name="profile_menus[<?php echo $index; ?>][order]" 
                                                           value="<?php echo htmlspecialchars($menu['order']); ?>" 
                                                           min="1" required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Target</label>
                                                    <div class="form-check mt-2">
                                                        <input type="checkbox" class="form-check-input" 
                                                               name="profile_menus[<?php echo $index; ?>][target_blank]" 
                                                               value="1" <?php echo !empty($menu['target_blank']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Nuova pagina</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-profile-menu" 
                                                            data-item-type="profile-menu" data-item-label="<?php echo htmlspecialchars($menu['label']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addProfileMenuItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Voce Menu Profilo
                            </button>
                        </div>
                    </div>

                    <!-- Pulsanti di salvataggio -->
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Salva Impostazioni Header Brands
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tab Header Influencers -->
            <div class="tab-pane fade" id="header-influencers" role="tabpanel" aria-labelledby="header-influencers-tab">
                <form method="POST" id="headerInfluencersForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_header_influencers_settings">
                    
                    <!-- Sezione Logo Header Influencers -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-image me-2"></i>Logo Header Influencers
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="header_influencers_logo_text" class="form-label">Testo Logo (Fallback)</label>
                                    <input type="text" class="form-control" id="header_influencers_logo_text" name="header_influencers_logo_text" 
                                           value="<?php echo htmlspecialchars($header_influencers_settings['logo_text'] ?? 'Kibbiz'); ?>" 
                                           placeholder="Inserisci il testo del logo" required>
                                    <div class="form-text">Questo testo verrà mostrato se non viene caricata un'immagine logo</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="header_influencers_logo" class="form-label">Immagine Logo</label>
                                    <input type="file" class="form-control" id="header_influencers_logo" name="header_influencers_logo" 
                                           accept="image/*">
                                    <div class="form-text">
                                        Carica un'immagine per il logo. Dimensioni consigliate: 150x50px. 
                                        Formati supportati: JPG, PNG, GIF, WebP.
                                        <?php if (!empty($header_influencers_settings['logo_url'])): ?>
                                            <br>
                                            <strong>Logo attuale:</strong>
                                            <img src="<?php echo htmlspecialchars($header_influencers_settings['logo_url']); ?>" 
                                                 alt="Logo Header Influencers" style="max-height: 50px; margin-left: 10px;">
                                            <br>
                                            <label class="mt-2">
                                                <input type="checkbox" name="remove_header_influencers_logo" value="1">
                                                Rimuovi logo attuale
                                            </label>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sezione Menu Principale Influencers -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-bars me-2"></i>Menu Principale Influencers
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Gestisci le voci del menu principale che gli influencer visualizzano dopo il login.</p>
                            <div id="mainMenuInfluencersContainer">
                                <?php
                                $main_menus_influencers = $header_influencers_settings['main_menus'] ?? [
                                    ['label' => 'Dashboard', 'url' => '/influencers/dashboard.php', 'target_blank' => false, 'order' => 1, 'icon' => 'fas fa-tachometer-alt'],
                                    ['label' => 'Campagne', 'url' => '/influencers/campaigns.php', 'target_blank' => false, 'order' => 2, 'icon' => 'fas fa-bullhorn'],
                                    ['label' => 'Messaggi', 'url' => '/influencers/messages/conversation-list.php', 'target_blank' => false, 'order' => 3, 'icon' => 'fas fa-envelope'],
                                    ['label' => 'Analytics', 'url' => '/influencers/analytics.php', 'target_blank' => false, 'order' => 4, 'icon' => 'fas fa-chart-bar']
                                ];
                                
                                foreach ($main_menus_influencers as $index => $menu): ?>
                                    <div class="main-menu-influencers-item card mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Etichetta</label>
                                                    <input type="text" class="form-control" name="main_menus[<?php echo $index; ?>][label]" 
                                                           value="<?php echo htmlspecialchars($menu['label']); ?>" 
                                                           placeholder="Nome del menu" required>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Icona</label>
                                                    <select class="form-select" name="main_menus[<?php echo $index; ?>][icon]">
                                                        <option value="">Nessuna icona</option>
                                                        <option value="fas fa-tachometer-alt" <?php echo ($menu['icon'] ?? '') === 'fas fa-tachometer-alt' ? 'selected' : ''; ?>>Dashboard</option>
                                                        <option value="fas fa-bullhorn" <?php echo ($menu['icon'] ?? '') === 'fas fa-bullhorn' ? 'selected' : ''; ?>>Campagne</option>
                                                        <option value="fas fa-envelope" <?php echo ($menu['icon'] ?? '') === 'fas fa-envelope' ? 'selected' : ''; ?>>Messaggi</option>
                                                        <option value="fas fa-search" <?php echo ($menu['icon'] ?? '') === 'fas fa-search' ? 'selected' : ''; ?>>Cerca</option>
                                                        <option value="fas fa-chart-bar" <?php echo ($menu['icon'] ?? '') === 'fas fa-chart-bar' ? 'selected' : ''; ?>>Analytics</option>
                                                        <option value="fas fa-users" <?php echo ($menu['icon'] ?? '') === 'fas fa-users' ? 'selected' : ''; ?>>Utenti</option>
                                                        <option value="fas fa-cog" <?php echo ($menu['icon'] ?? '') === 'fas fa-cog' ? 'selected' : ''; ?>>Impostazioni</option>
                                                        <option value="fas fa-home" <?php echo ($menu['icon'] ?? '') === 'fas fa-home' ? 'selected' : ''; ?>>Home</option>
                                                        <option value="fas fa-bell" <?php echo ($menu['icon'] ?? '') === 'fas fa-bell' ? 'selected' : ''; ?>>Notifiche</option>
                                                        <option value="fas fa-user" <?php echo ($menu['icon'] ?? '') === 'fas fa-user' ? 'selected' : ''; ?>>Profilo</option>
                                                        <option value="fas fa-sign-out-alt" <?php echo ($menu['icon'] ?? '') === 'fas fa-sign-out-alt' ? 'selected' : ''; ?>>Logout</option>
                                                    </select>
                                                    <div class="form-text">Seleziona un'icona per questa voce menu</div>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">URL</label>
                                                    <input type="text" class="form-control" name="main_menus[<?php echo $index; ?>][url]" 
                                                           value="<?php echo htmlspecialchars($menu['url']); ?>" 
                                                           placeholder="https://..." required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Ordine</label>
                                                    <input type="number" class="form-control" name="main_menus[<?php echo $index; ?>][order]" 
                                                           value="<?php echo htmlspecialchars($menu['order']); ?>" 
                                                           min="1" required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Target</label>
                                                    <div class="form-check mt-2">
                                                        <input type="checkbox" class="form-check-input" 
                                                               name="main_menus[<?php echo $index; ?>][target_blank]" 
                                                               value="1" <?php echo !empty($menu['target_blank']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Nuova pagina</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-main-menu-influencers" 
                                                            data-item-type="main-menu-influencers" data-item-label="<?php echo htmlspecialchars($menu['label']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addMainMenuInfluencersItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Voce Menu Principale
                            </button>
                        </div>
                    </div>

                    <!-- Sezione Menu Profilo Influencers -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user-circle me-2"></i>Menu Profilo Influencers
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Gestisci le voci del menu a tendina del profilo per gli influencer.</p>
                            <div id="profileMenuInfluencersContainer">
                                <?php
                                $profile_menus_influencers = $header_influencers_settings['profile_menus'] ?? [
                                    ['label' => 'Impostazioni', 'url' => '/influencers/settings.php', 'target_blank' => false, 'order' => 1, 'icon' => 'fas fa-cog'],
                                    ['label' => 'Logout', 'url' => '/auth/logout.php', 'target_blank' => false, 'order' => 2, 'icon' => 'fas fa-sign-out-alt']
                                ];
                                
                                foreach ($profile_menus_influencers as $index => $menu): ?>
                                    <div class="profile-menu-influencers-item card mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Etichetta</label>
                                                    <input type="text" class="form-control" name="profile_menus[<?php echo $index; ?>][label]" 
                                                           value="<?php echo htmlspecialchars($menu['label']); ?>" 
                                                           placeholder="Nome del menu" required>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Icona</label>
                                                    <select class="form-select" name="profile_menus[<?php echo $index; ?>][icon]">
                                                        <option value="">Nessuna icona</option>
                                                        <option value="fas fa-tachometer-alt" <?php echo ($menu['icon'] ?? '') === 'fas fa-tachometer-alt' ? 'selected' : ''; ?>>Dashboard</option>
                                                        <option value="fas fa-bullhorn" <?php echo ($menu['icon'] ?? '') === 'fas fa-bullhorn' ? 'selected' : ''; ?>>Campagne</option>
                                                        <option value="fas fa-envelope" <?php echo ($menu['icon'] ?? '') === 'fas fa-envelope' ? 'selected' : ''; ?>>Messaggi</option>
                                                        <option value="fas fa-search" <?php echo ($menu['icon'] ?? '') === 'fas fa-search' ? 'selected' : ''; ?>>Cerca</option>
                                                        <option value="fas fa-chart-bar" <?php echo ($menu['icon'] ?? '') === 'fas fa-chart-bar' ? 'selected' : ''; ?>>Analytics</option>
                                                        <option value="fas fa-users" <?php echo ($menu['icon'] ?? '') === 'fas fa-users' ? 'selected' : ''; ?>>Utenti</option>
                                                        <option value="fas fa-cog" <?php echo ($menu['icon'] ?? '') === 'fas fa-cog' ? 'selected' : ''; ?>>Impostazioni</option>
                                                        <option value="fas fa-home" <?php echo ($menu['icon'] ?? '') === 'fas fa-home' ? 'selected' : ''; ?>>Home</option>
                                                        <option value="fas fa-bell" <?php echo ($menu['icon'] ?? '') === 'fas fa-bell' ? 'selected' : ''; ?>>Notifiche</option>
                                                        <option value="fas fa-user" <?php echo ($menu['icon'] ?? '') === 'fas fa-user' ? 'selected' : ''; ?>>Profilo</option>
                                                        <option value="fas fa-sign-out-alt" <?php echo ($menu['icon'] ?? '') === 'fas fa-sign-out-alt' ? 'selected' : ''; ?>>Logout</option>
                                                    </select>
                                                    <div class="form-text">Seleziona un'icona per questa voce menu</div>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">URL</label>
                                                    <input type="text" class="form-control" name="profile_menus[<?php echo $index; ?>][url]" 
                                                           value="<?php echo htmlspecialchars($menu['url']); ?>" 
                                                           placeholder="https://..." required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Ordine</label>
                                                    <input type="number" class="form-control" name="profile_menus[<?php echo $index; ?>][order]" 
                                                           value="<?php echo htmlspecialchars($menu['order']); ?>" 
                                                           min="1" required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Target</label>
                                                    <div class="form-check mt-2">
                                                        <input type="checkbox" class="form-check-input" 
                                                               name="profile_menus[<?php echo $index; ?>][target_blank]" 
                                                               value="1" <?php echo !empty($menu['target_blank']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Nuova pagina</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-profile-menu-influencers" 
                                                            data-item-type="profile-menu-influencers" data-item-label="<?php echo htmlspecialchars($menu['label']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addProfileMenuInfluencersItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Voce Menu Profilo
                            </button>
                        </div>
                    </div>

                    <!-- Pulsanti di salvataggio -->
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Salva Impostazioni Header Influencers
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tab Footer B&I -->
            <div class="tab-pane fade" id="footer-bi" role="tabpanel" aria-labelledby="footer-bi-tab">
                <form method="POST" id="footerBIForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_footer_bi_settings">
                    
                    <!-- Sezione Colonna 1: Titolo e Descrizione -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-heading me-2"></i>Colonna 1 - Titolo e Descrizione
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="footer_bi_title" class="form-label">Titolo</label>
                                    <input type="text" class="form-control" id="footer_bi_title" name="footer_bi_title" 
                                           value="<?php echo htmlspecialchars($footer_bi_settings['title'] ?? 'Influencer Marketplace'); ?>" 
                                           placeholder="Inserisci il titolo" required>
                                    <div class="form-text">Titolo principale della colonna (es. "Influencer Marketplace")</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="footer_bi_description" class="form-label">Descrizione</label>
                                    <textarea class="form-control" id="footer_bi_description" name="footer_bi_description" 
                                              rows="3" placeholder="Inserisci la descrizione"><?php echo htmlspecialchars($footer_bi_settings['description'] ?? 'La piattaforma per connettere influencer e brand.'); ?></textarea>
                                    <div class="form-text">Breve descrizione sotto il titolo</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sezione Colonna 2: Link Utili -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-link me-2"></i>Colonna 2 - Link Utili
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Gestisci i link della colonna "Link Utili"</p>
                            <div id="usefulLinksContainer">
                                <?php
                                $useful_links = $footer_bi_settings['useful_links'] ?? [
                                    ['label' => 'Home', 'url' => '/', 'target_blank' => false, 'order' => 1],
                                    ['label' => 'Login', 'url' => '/auth/login.php', 'target_blank' => false, 'order' => 2],
                                    ['label' => 'Registrati', 'url' => '/auth/register.php', 'target_blank' => false, 'order' => 3]
                                ];
                                
                                foreach ($useful_links as $index => $link): ?>
                                    <div class="useful-link-item card mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Etichetta</label>
                                                    <input type="text" class="form-control" name="useful_links[<?php echo $index; ?>][label]" 
                                                           value="<?php echo htmlspecialchars($link['label']); ?>" 
                                                           placeholder="Nome del link" required>
                                                </div>
                                                <div class="col-md-5 mb-3">
                                                    <label class="form-label">URL</label>
                                                    <input type="text" class="form-control" name="useful_links[<?php echo $index; ?>][url]" 
                                                           value="<?php echo htmlspecialchars($link['url']); ?>" 
                                                           placeholder="https://..." required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Ordine</label>
                                                    <input type="number" class="form-control" name="useful_links[<?php echo $index; ?>][order]" 
                                                           value="<?php echo htmlspecialchars($link['order']); ?>" 
                                                           min="1" required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Target</label>
                                                    <div class="form-check mt-2">
                                                        <input type="checkbox" class="form-check-input" 
                                                               name="useful_links[<?php echo $index; ?>][target_blank]" 
                                                               value="1" <?php echo !empty($link['target_blank']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Nuova pagina</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-useful-link" 
                                                            data-item-type="useful-link" data-item-label="<?php echo htmlspecialchars($link['label']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addUsefulLinkItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Link Utile
                            </button>
                        </div>
                    </div>

                    <!-- Sezione Colonna 3: Nuova Colonna -->
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-columns me-2"></i>Colonna 3 - Nuova Colonna
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="new_column_title" class="form-label">Titolo Colonna</label>
                                    <input type="text" class="form-control" id="new_column_title" name="new_column_title" 
                                           value="<?php echo htmlspecialchars($footer_bi_settings['new_column_title'] ?? 'Nuova Colonna'); ?>" 
                                           placeholder="Titolo della colonna">
                                </div>
                            </div>
                            <p class="text-muted mb-3">Gestisci i link della colonna "Nuova Colonna"</p>
                            <div id="newColumnLinksContainer">
                                <?php
                                $new_column_links = $footer_bi_settings['new_column_links'] ?? [
                                    ['label' => 'Link 1', 'url' => '#', 'target_blank' => false, 'order' => 1],
                                    ['label' => 'Link 2', 'url' => '#', 'target_blank' => false, 'order' => 2],
                                    ['label' => 'Link 3', 'url' => '#', 'target_blank' => false, 'order' => 3]
                                ];
                                
                                foreach ($new_column_links as $index => $link): ?>
                                    <div class="new-column-link-item card mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Etichetta</label>
                                                    <input type="text" class="form-control" name="new_column_links[<?php echo $index; ?>][label]" 
                                                           value="<?php echo htmlspecialchars($link['label']); ?>" 
                                                           placeholder="Nome del link" required>
                                                </div>
                                                <div class="col-md-5 mb-3">
                                                    <label class="form-label">URL</label>
                                                    <input type="text" class="form-control" name="new_column_links[<?php echo $index; ?>][url]" 
                                                           value="<?php echo htmlspecialchars($link['url']); ?>" 
                                                           placeholder="https://..." required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Ordine</label>
                                                    <input type="number" class="form-control" name="new_column_links[<?php echo $index; ?>][order]" 
                                                           value="<?php echo htmlspecialchars($link['order']); ?>" 
                                                           min="1" required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Target</label>
                                                    <div class="form-check mt-2">
                                                        <input type="checkbox" class="form-check-input" 
                                                               name="new_column_links[<?php echo $index; ?>][target_blank]" 
                                                               value="1" <?php echo !empty($link['target_blank']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label">Nuova pagina</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-new-column-link" 
                                                            data-item-type="new-column-link" data-item-label="<?php echo htmlspecialchars($link['label']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addNewColumnLinkItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Link
                            </button>
                        </div>
                    </div>

                    <!-- Sezione Colonna 4: Seguici su - Social Media -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-share-alt me-2"></i>Colonna 4 - Seguici su
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="social_column_title" class="form-label">Titolo Colonna</label>
                                    <input type="text" class="form-control" id="social_column_title" name="social_column_title" 
                                           value="<?php echo htmlspecialchars($footer_bi_settings['social_column_title'] ?? 'Seguici su'); ?>" 
                                           placeholder="Titolo della colonna social">
                                </div>
                            </div>
                            <div id="socialMediaBIContainer">
                                <?php
                                $social_links_bi = $footer_bi_settings['social_links'] ?? [
                                    ['platform' => 'instagram', 'url' => '#', 'icon' => 'fab fa-instagram', 'order' => 1],
                                    ['platform' => 'tiktok', 'url' => '#', 'icon' => 'fab fa-tiktok', 'order' => 2],
                                    ['platform' => 'linkedin', 'url' => '#', 'icon' => 'fab fa-linkedin', 'order' => 3]
                                ];
                                
                                foreach ($social_links_bi as $index => $social): ?>
                                    <div class="social-media-bi-item card mb-3">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Piattaforma</label>
                                                    <select class="form-select social-platform-bi" name="social_links[<?php echo $index; ?>][platform]">
                                                        <option value="instagram" <?php echo ($social['platform'] === 'instagram') ? 'selected' : ''; ?>>Instagram</option>
                                                        <option value="tiktok" <?php echo ($social['platform'] === 'tiktok') ? 'selected' : ''; ?>>TikTok</option>
                                                        <option value="linkedin" <?php echo ($social['platform'] === 'linkedin') ? 'selected' : ''; ?>>LinkedIn</option>
                                                        <option value="facebook" <?php echo ($social['platform'] === 'facebook') ? 'selected' : ''; ?>>Facebook</option>
                                                        <option value="twitter" <?php echo ($social['platform'] === 'twitter') ? 'selected' : ''; ?>>Twitter</option>
                                                        <option value="youtube" <?php echo ($social['platform'] === 'youtube') ? 'selected' : ''; ?>>YouTube</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label class="form-label">Icona</label>
                                                    <select class="form-select social-icon-bi" name="social_links[<?php echo $index; ?>][icon]">
                                                        <option value="fab fa-instagram" <?php echo ($social['icon'] === 'fab fa-instagram') ? 'selected' : ''; ?>>Instagram</option>
                                                        <option value="fab fa-tiktok" <?php echo ($social['icon'] === 'fab fa-tiktok') ? 'selected' : ''; ?>>TikTok</option>
                                                        <option value="fab fa-linkedin" <?php echo ($social['icon'] === 'fab fa-linkedin') ? 'selected' : ''; ?>>LinkedIn</option>
                                                        <option value="fab fa-facebook" <?php echo ($social['icon'] === 'fab fa-facebook') ? 'selected' : ''; ?>>Facebook</option>
                                                        <option value="fab fa-twitter" <?php echo ($social['icon'] === 'fab fa-twitter') ? 'selected' : ''; ?>>Twitter</option>
                                                        <option value="fab fa-youtube" <?php echo ($social['icon'] === 'fab fa-youtube') ? 'selected' : ''; ?>>YouTube</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">URL Profilo</label>
                                                    <input type="url" class="form-control" name="social_links[<?php echo $index; ?>][url]" 
                                                           value="<?php echo htmlspecialchars($social['url']); ?>" 
                                                           placeholder="https://..." required>
                                                </div>
                                                <div class="col-md-1 mb-3">
                                                    <label class="form-label">Ordine</label>
                                                    <input type="number" class="form-control" name="social_links[<?php echo $index; ?>][order]" 
                                                           value="<?php echo htmlspecialchars($social['order']); ?>" 
                                                           min="1" required>
                                                </div>
                                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                                    <button type="button" class="btn btn-danger btn-sm remove-social-bi" 
                                                            data-item-type="social-bi" data-item-label="<?php echo htmlspecialchars($social['platform']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="button" class="btn btn-success btn-sm" onclick="addSocialBIItem()">
                                <i class="fas fa-plus me-1"></i>Aggiungi Social
                            </button>
                        </div>
                    </div>
					
					<!-- Sezione Copyright -->
<div class="card mb-4">
    <div class="card-header bg-secondary text-white">
        <h5 class="card-title mb-0">
            <i class="fas fa-copyright me-2"></i>Copyright
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-12 mb-3">
                <label for="footer_bi_copyright" class="form-label">Testo Copyright</label>
                <input type="text" class="form-control" id="footer_bi_copyright" name="footer_bi_copyright" 
                       value="<?php echo htmlspecialchars($footer_bi_settings['copyright'] ?? '© 2025 Influencer Marketplace. Tutti i diritti riservati.'); ?>" 
                       placeholder="Inserisci il testo del copyright" required>
                <div class="form-text">
                    Personalizza il testo del copyright. Usa il segnaposto <code>{year}</code> per visualizzare automaticamente l'anno corrente.
                    Esempio: "© 2025 Influencer Marketplace. Tutti i diritti riservati."
                </div>
            </div>
        </div>
    </div>
</div>

                    <!-- Pulsanti di salvataggio -->
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Salva Impostazioni Footer B&I
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let socialCounter = <?php echo count($footer_settings['social_links'] ?? []); ?>;
let quickLinkCounter = <?php echo count($footer_settings['quick_links'] ?? []); ?>;
let supportLinkCounter = <?php echo count($footer_settings['support_links'] ?? []); ?>;
let navMenuCounter = <?php echo count($header_settings['nav_menus'] ?? []); ?>;
let mainMenuCounter = <?php echo count($header_brands_settings['main_menus'] ?? []); ?>;
let profileMenuCounter = <?php echo count($header_brands_settings['profile_menus'] ?? []); ?>;
let mainMenuInfluencersCounter = <?php echo count($header_influencers_settings['main_menus'] ?? []); ?>;
let profileMenuInfluencersCounter = <?php echo count($header_influencers_settings['profile_menus'] ?? []); ?>;
let usefulLinkCounter = <?php echo count($footer_bi_settings['useful_links'] ?? []); ?>;
let newColumnLinkCounter = <?php echo count($footer_bi_settings['new_column_links'] ?? []); ?>;
let socialBICounter = <?php echo count($footer_bi_settings['social_links'] ?? []); ?>;

// Variabili per gestire il modal di conferma
let currentDeleteButton = null;
let currentItemType = null;

// Inizializzazione event listeners per i pulsanti elimina
document.addEventListener('DOMContentLoaded', function() {
    // Aggiungi event listener per tutti i pulsanti elimina
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-nav-menu') || 
            e.target.closest('.remove-quick-link') || 
            e.target.closest('.remove-support-link') || 
            e.target.closest('.remove-social') ||
            e.target.closest('.remove-main-menu') ||
            e.target.closest('.remove-profile-menu') ||
            e.target.closest('.remove-main-menu-influencers') ||
            e.target.closest('.remove-profile-menu-influencers') ||
            e.target.closest('.remove-useful-link') ||
            e.target.closest('.remove-new-column-link') ||
            e.target.closest('.remove-social-bi')) {
            
            const button = e.target.closest('button');
            currentDeleteButton = button;
            currentItemType = button.getAttribute('data-item-type');
            const itemLabel = button.getAttribute('data-item-label');
            
            // Mostra il modal di conferma
            const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            modal.show();
        }
    });
    
    // Gestione conferma eliminazione
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (currentDeleteButton && currentItemType) {
            // Chiama la funzione appropriata in base al tipo di elemento
            switch(currentItemType) {
                case 'nav-menu':
                    removeNavMenuItem(currentDeleteButton);
                    break;
                case 'quick-link':
                    removeQuickLinkItem(currentDeleteButton);
                    break;
                case 'support-link':
                    removeSupportLinkItem(currentDeleteButton);
                    break;
                case 'social':
                    removeSocialItem(currentDeleteButton);
                    break;
                case 'main-menu':
                    removeMainMenuItem(currentDeleteButton);
                    break;
                case 'profile-menu':
                    removeProfileMenuItem(currentDeleteButton);
                    break;
                case 'main-menu-influencers':
                    removeMainMenuInfluencersItem(currentDeleteButton);
                    break;
                case 'profile-menu-influencers':
                    removeProfileMenuInfluencersItem(currentDeleteButton);
                    break;
                case 'useful-link':
                    removeUsefulLinkItem(currentDeleteButton);
                    break;
                case 'new-column-link':
                    removeNewColumnLinkItem(currentDeleteButton);
                    break;
                case 'social-bi':
                    removeSocialBIItem(currentDeleteButton);
                    break;
            }
            
            // Chiudi il modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
            modal.hide();
            
            // Reset delle variabili
            currentDeleteButton = null;
            currentItemType = null;
        }
    });
});

// Funzioni per Menu di Navigazione
function addNavMenuItem() {
    navMenuCounter++;
    const container = document.getElementById('navMenuContainer');
    const newItem = document.createElement('div');
    newItem.className = 'nav-menu-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Etichetta</label>
                    <input type="text" class="form-control" name="nav_menus[${navMenuCounter}][label]" 
                           placeholder="Nome del menu" required>
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">URL</label>
                    <input type="text" class="form-control" name="nav_menus[${navMenuCounter}][url]" 
                           placeholder="https://..." required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Target</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" 
                               name="nav_menus[${navMenuCounter}][target_blank]" value="1">
                        <label class="form-check-label">Apri in nuova pagina</label>
                    </div>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-nav-menu" 
                            data-item-type="nav-menu" data-item-label="Nuova voce menu">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeNavMenuItem(button) {
    const item = button.closest('.nav-menu-item');
    if (document.querySelectorAll('.nav-menu-item').length > 1) {
        item.remove();
    } else {
        alert('Deve esserci almeno una voce menu!');
    }
}

// Funzioni per Link Veloci
function addQuickLinkItem() {
    quickLinkCounter++;
    const container = document.getElementById('quickLinksContainer');
    const newItem = document.createElement('div');
    newItem.className = 'quick-link-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Etichetta</label>
                    <input type="text" class="form-control" name="quick_links[${quickLinkCounter}][label]" 
                           placeholder="Nome del link" required>
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">URL</label>
                    <input type="text" class="form-control" name="quick_links[${quickLinkCounter}][url]" 
                           placeholder="https://..." required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Target</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" 
                               name="quick_links[${quickLinkCounter}][target_blank]" value="1">
                        <label class="form-check-label">Apri in nuova pagina</label>
                    </div>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-quick-link" 
                            data-item-type="quick-link" data-item-label="Nuovo link veloce">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeQuickLinkItem(button) {
    const item = button.closest('.quick-link-item');
    if (document.querySelectorAll('.quick-link-item').length > 1) {
        item.remove();
    } else {
        alert('Deve esserci almeno un link veloce!');
    }
}

// Funzioni per Link Supporto
function addSupportLinkItem() {
    supportLinkCounter++;
    const container = document.getElementById('supportLinksContainer');
    const newItem = document.createElement('div');
    newItem.className = 'support-link-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Etichetta</label>
                    <input type="text" class="form-control" name="support_links[${supportLinkCounter}][label]" 
                           placeholder="Nome del link" required>
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">URL</label>
                    <input type="text" class="form-control" name="support_links[${supportLinkCounter}][url]" 
                           placeholder="https://..." required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Target</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" 
                               name="support_links[${supportLinkCounter}][target_blank]" value="1">
                        <label class="form-check-label">Apri in nuova pagina</label>
                    </div>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-support-link" 
                            data-item-type="support-link" data-item-label="Nuovo link supporto">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeSupportLinkItem(button) {
    const item = button.closest('.support-link-item');
    if (document.querySelectorAll('.support-link-item').length > 1) {
        item.remove();
    } else {
        alert('Deve esserci almeno un link di supporto!');
    }
}

// Funzioni per Social Media
function addSocialItem() {
    socialCounter++;
    const container = document.getElementById('socialMediaContainer');
    const newItem = document.createElement('div');
    newItem.className = 'social-media-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Piattaforma</label>
                    <select class="form-select social-platform" name="social_links[new_${socialCounter}][platform]">
                        <option value="instagram">Instagram</option>
                        <option value="tiktok">TikTok</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="facebook">Facebook</option>
                        <option value="twitter">Twitter</option>
                        <option value="youtube">YouTube</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Icona</label>
                    <select class="form-select social-icon" name="social_links[new_${socialCounter}][icon]">
                        <option value="fab fa-instagram">Instagram</option>
                        <option value="fab fa-tiktok">TikTok</option>
                        <option value="fab fa-linkedin">LinkedIn</option>
                        <option value="fab fa-facebook">Facebook</option>
                        <option value="fab fa-twitter">Twitter</option>
                        <option value="fab fa-youtube">YouTube</option>
                    </select>
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">URL Profilo</label>
                    <input type="url" class="form-control" name="social_links[new_${socialCounter}][url]" 
                           placeholder="https://..." required>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-social" 
                            data-item-type="social" data-item-label="Nuovo social">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeSocialItem(button) {
    const item = button.closest('.social-media-item');
    if (document.querySelectorAll('.social-media-item').length > 1) {
        item.remove();
    } else {
        alert('Deve esserci almeno un social network!');
    }
}

// Funzioni per Menu Principale Brands
function addMainMenuItem() {
    mainMenuCounter++;
    const container = document.getElementById('mainMenuContainer');
    const newItem = document.createElement('div');
    newItem.className = 'main-menu-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Etichetta</label>
                    <input type="text" class="form-control" name="main_menus[${mainMenuCounter}][label]" 
                           placeholder="Nome del menu" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Icona</label>
                    <select class="form-select" name="main_menus[${mainMenuCounter}][icon]">
                        <option value="">Nessuna icona</option>
                        <option value="fas fa-tachometer-alt">Dashboard</option>
                        <option value="fas fa-bullhorn">Campagne</option>
                        <option value="fas fa-envelope">Messaggi</option>
                        <option value="fas fa-search">Cerca</option>
                        <option value="fas fa-chart-bar">Analytics</option>
                        <option value="fas fa-users">Utenti</option>
                        <option value="fas fa-cog">Impostazioni</option>
                        <option value="fas fa-home">Home</option>
                        <option value="fas fa-bell">Notifiche</option>
                        <option value="fas fa-user">Profilo</option>
                        <option value="fas fa-sign-out-alt">Logout</option>
                    </select>
                    <div class="form-text">Seleziona un'icona per questa voce menu</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">URL</label>
                    <input type="text" class="form-control" name="main_menus[${mainMenuCounter}][url]" 
                           placeholder="https://..." required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Ordine</label>
                    <input type="number" class="form-control" name="main_menus[${mainMenuCounter}][order]" 
                           value="${mainMenuCounter + 1}" min="1" required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Target</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" 
                               name="main_menus[${mainMenuCounter}][target_blank]" value="1">
                        <label class="form-check-label">Nuova pagina</label>
                    </div>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-main-menu" 
                            data-item-type="main-menu" data-item-label="Nuova voce menu">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeMainMenuItem(button) {
    const item = button.closest('.main-menu-item');
    item.remove();
}

// Funzioni per Menu Profilo Brands
function addProfileMenuItem() {
    profileMenuCounter++;
    const container = document.getElementById('profileMenuContainer');
    const newItem = document.createElement('div');
    newItem.className = 'profile-menu-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Etichetta</label>
                    <input type="text" class="form-control" name="profile_menus[${profileMenuCounter}][label]" 
                           placeholder="Nome del menu" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Icona</label>
                    <select class="form-select" name="profile_menus[${profileMenuCounter}][icon]">
                        <option value="">Nessuna icona</option>
                        <option value="fas fa-tachometer-alt">Dashboard</option>
                        <option value="fas fa-bullhorn">Campagne</option>
                        <option value="fas fa-envelope">Messaggi</option>
                        <option value="fas fa-search">Cerca</option>
                        <option value="fas fa-chart-bar">Analytics</option>
                        <option value="fas fa-users">Utenti</option>
                        <option value="fas fa-cog">Impostazioni</option>
                        <option value="fas fa-home">Home</option>
                        <option value="fas fa-bell">Notifiche</option>
                        <option value="fas fa-user">Profilo</option>
                        <option value="fas fa-sign-out-alt">Logout</option>
                    </select>
                    <div class="form-text">Seleziona un'icona per questa voce menu</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">URL</label>
                    <input type="text" class="form-control" name="profile_menus[${profileMenuCounter}][url]" 
                           placeholder="https://..." required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Ordine</label>
                    <input type="number" class="form-control" name="profile_menus[${profileMenuCounter}][order]" 
                           value="${profileMenuCounter + 1}" min="1" required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Target</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" 
                               name="profile_menus[${profileMenuCounter}][target_blank]" value="1">
                        <label class="form-check-label">Nuova pagina</label>
                    </div>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-profile-menu" 
                            data-item-type="profile-menu" data-item-label="Nuova voce menu">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeProfileMenuItem(button) {
    const item = button.closest('.profile-menu-item');
    item.remove();
}

// Funzioni per Menu Principale Influencers
function addMainMenuInfluencersItem() {
    mainMenuInfluencersCounter++;
    const container = document.getElementById('mainMenuInfluencersContainer');
    const newItem = document.createElement('div');
    newItem.className = 'main-menu-influencers-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Etichetta</label>
                    <input type="text" class="form-control" name="main_menus[${mainMenuInfluencersCounter}][label]" 
                           placeholder="Nome del menu" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Icona</label>
                    <select class="form-select" name="main_menus[${mainMenuInfluencersCounter}][icon]">
                        <option value="">Nessuna icona</option>
                        <option value="fas fa-tachometer-alt">Dashboard</option>
                        <option value="fas fa-bullhorn">Campagne</option>
                        <option value="fas fa-envelope">Messaggi</option>
                        <option value="fas fa-search">Cerca</option>
                        <option value="fas fa-chart-bar">Analytics</option>
                        <option value="fas fa-users">Utenti</option>
                        <option value="fas fa-cog">Impostazioni</option>
                        <option value="fas fa-home">Home</option>
                        <option value="fas fa-bell">Notifiche</option>
                        <option value="fas fa-user">Profilo</option>
                        <option value="fas fa-sign-out-alt">Logout</option>
                    </select>
                    <div class="form-text">Seleziona un'icona per questa voce menu</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">URL</label>
                    <input type="text" class="form-control" name="main_menus[${mainMenuInfluencersCounter}][url]" 
                           placeholder="https://..." required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Ordine</label>
                    <input type="number" class="form-control" name="main_menus[${mainMenuInfluencersCounter}][order]" 
                           value="${mainMenuInfluencersCounter + 1}" min="1" required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Target</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" 
                               name="main_menus[${mainMenuInfluencersCounter}][target_blank]" value="1">
                        <label class="form-check-label">Nuova pagina</label>
                    </div>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-main-menu-influencers" 
                            data-item-type="main-menu-influencers" data-item-label="Nuova voce menu">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeMainMenuInfluencersItem(button) {
    const item = button.closest('.main-menu-influencers-item');
    item.remove();
}

// Funzioni per Menu Profilo Influencers
function addProfileMenuInfluencersItem() {
    profileMenuInfluencersCounter++;
    const container = document.getElementById('profileMenuInfluencersContainer');
    const newItem = document.createElement('div');
    newItem.className = 'profile-menu-influencers-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Etichetta</label>
                    <input type="text" class="form-control" name="profile_menus[${profileMenuInfluencersCounter}][label]" 
                           placeholder="Nome del menu" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Icona</label>
                    <select class="form-select" name="profile_menus[${profileMenuInfluencersCounter}][icon]">
                        <option value="">Nessuna icona</option>
                        <option value="fas fa-tachometer-alt">Dashboard</option>
                        <option value="fas fa-bullhorn">Campagne</option>
                        <option value="fas fa-envelope">Messaggi</option>
                        <option value="fas fa-search">Cerca</option>
                        <option value="fas fa-chart-bar">Analytics</option>
                        <option value="fas fa-users">Utenti</option>
                        <option value="fas fa-cog">Impostazioni</option>
                        <option value="fas fa-home">Home</option>
                        <option value="fas fa-bell">Notifiche</option>
                        <option value="fas fa-user">Profilo</option>
                        <option value="fas fa-sign-out-alt">Logout</option>
                    </select>
                    <div class="form-text">Seleziona un'icona per questa voce menu</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">URL</label>
                    <input type="text" class="form-control" name="profile_menus[${profileMenuInfluencersCounter}][url]" 
                           placeholder="https://..." required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Ordine</label>
                    <input type="number" class="form-control" name="profile_menus[${profileMenuInfluencersCounter}][order]" 
                           value="${profileMenuInfluencersCounter + 1}" min="1" required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Target</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" 
                               name="profile_menus[${profileMenuInfluencersCounter}][target_blank]" value="1">
                        <label class="form-check-label">Nuova pagina</label>
                    </div>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-profile-menu-influencers" 
                            data-item-type="profile-menu-influencers" data-item-label="Nuova voce menu">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeProfileMenuInfluencersItem(button) {
    const item = button.closest('.profile-menu-influencers-item');
    item.remove();
}

// Funzioni per Link Utili
function addUsefulLinkItem() {
    usefulLinkCounter++;
    const container = document.getElementById('usefulLinksContainer');
    const newItem = document.createElement('div');
    newItem.className = 'useful-link-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Etichetta</label>
                    <input type="text" class="form-control" name="useful_links[${usefulLinkCounter}][label]" 
                           placeholder="Nome del link" required>
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">URL</label>
                    <input type="text" class="form-control" name="useful_links[${usefulLinkCounter}][url]" 
                           placeholder="https://..." required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Ordine</label>
                    <input type="number" class="form-control" name="useful_links[${usefulLinkCounter}][order]" 
                           value="${usefulLinkCounter + 1}" min="1" required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Target</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" 
                               name="useful_links[${usefulLinkCounter}][target_blank]" value="1">
                        <label class="form-check-label">Nuova pagina</label>
                    </div>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-useful-link" 
                            data-item-type="useful-link" data-item-label="Nuovo link utile">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeUsefulLinkItem(button) {
    const item = button.closest('.useful-link-item');
    item.remove();
}

// Funzioni per Nuova Colonna Links
function addNewColumnLinkItem() {
    newColumnLinkCounter++;
    const container = document.getElementById('newColumnLinksContainer');
    const newItem = document.createElement('div');
    newItem.className = 'new-column-link-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Etichetta</label>
                    <input type="text" class="form-control" name="new_column_links[${newColumnLinkCounter}][label]" 
                           placeholder="Nome del link" required>
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">URL</label>
                    <input type="text" class="form-control" name="new_column_links[${newColumnLinkCounter}][url]" 
                           placeholder="https://..." required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Ordine</label>
                    <input type="number" class="form-control" name="new_column_links[${newColumnLinkCounter}][order]" 
                           value="${newColumnLinkCounter + 1}" min="1" required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Target</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" 
                               name="new_column_links[${newColumnLinkCounter}][target_blank]" value="1">
                        <label class="form-check-label">Nuova pagina</label>
                    </div>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-new-column-link" 
                            data-item-type="new-column-link" data-item-label="Nuovo link">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeNewColumnLinkItem(button) {
    const item = button.closest('.new-column-link-item');
    item.remove();
}

// Funzioni per Social Media B&I
function addSocialBIItem() {
    socialBICounter++;
    const container = document.getElementById('socialMediaBIContainer');
    const newItem = document.createElement('div');
    newItem.className = 'social-media-bi-item card mb-3';
    newItem.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Piattaforma</label>
                    <select class="form-select social-platform-bi" name="social_links[${socialBICounter}][platform]">
                        <option value="instagram">Instagram</option>
                        <option value="tiktok">TikTok</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="facebook">Facebook</option>
                        <option value="twitter">Twitter</option>
                        <option value="youtube">YouTube</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Icona</label>
                    <select class="form-select social-icon-bi" name="social_links[${socialBICounter}][icon]">
                        <option value="fab fa-instagram">Instagram</option>
                        <option value="fab fa-tiktok">TikTok</option>
                        <option value="fab fa-linkedin">LinkedIn</option>
                        <option value="fab fa-facebook">Facebook</option>
                        <option value="fab fa-twitter">Twitter</option>
                        <option value="fab fa-youtube">YouTube</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">URL Profilo</label>
                    <input type="url" class="form-control" name="social_links[${socialBICounter}][url]" 
                           placeholder="https://..." required>
                </div>
                <div class="col-md-1 mb-3">
                    <label class="form-label">Ordine</label>
                    <input type="number" class="form-control" name="social_links[${socialBICounter}][order]" 
                           value="${socialBICounter + 1}" min="1" required>
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <button type="button" class="btn btn-danger btn-sm remove-social-bi" 
                            data-item-type="social-bi" data-item-label="Nuovo social">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeSocialBIItem(button) {
    const item = button.closest('.social-media-bi-item');
    item.remove();
}

// Aggiorna dinamicamente le icone quando cambia la piattaforma
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('social-platform')) {
        const platform = e.target.value;
        const iconSelect = e.target.closest('.row').querySelector('.social-icon');
        const iconMap = {
            'instagram': 'fab fa-instagram',
            'tiktok': 'fab fa-tiktok',
            'linkedin': 'fab fa-linkedin',
            'facebook': 'fab fa-facebook',
            'twitter': 'fab fa-twitter',
            'youtube': 'fab fa-youtube'
        };
        iconSelect.value = iconMap[platform] || 'fab fa-link';
    }
});

// Aggiorna dinamicamente le icone quando cambia la piattaforma (per B&I)
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('social-platform-bi')) {
        const platform = e.target.value;
        const iconSelect = e.target.closest('.row').querySelector('.social-icon-bi');
        const iconMap = {
            'instagram': 'fab fa-instagram',
            'tiktok': 'fab fa-tiktok',
            'linkedin': 'fab fa-linkedin',
            'facebook': 'fab fa-facebook',
            'twitter': 'fab fa-twitter',
            'youtube': 'fab fa-youtube'
        };
        iconSelect.value = iconMap[platform] || 'fab fa-link';
    }
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>