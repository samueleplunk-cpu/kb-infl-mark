<?php
ob_start();

// Inizia l'output buffering per prevenire problemi di redirect
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Percorso assoluto per config
$config_file = dirname(__DIR__) . '/includes/config.php';

if (file_exists($config_file)) {
    require_once $config_file;
    
    // Includi functions.php per avere la funzione is_admin()
    $functions_file = dirname(__DIR__) . '/includes/functions.php';
    if (file_exists($functions_file)) {
        require_once $functions_file;
    }
    
    require_once 'admin_functions.php';
} else {
    die("Config file not found: " . $config_file);
}

// INCLUSIONE SISTEMA MANUTENZIONE - AGGIUNTA IMPORTANTE
$maintenance_file = dirname(__DIR__) . '/includes/maintenance.php';
if (file_exists($maintenance_file)) {
    require_once $maintenance_file;
}

// Controllo accesso admin
require_admin_login();

// Controllo timeout sessione admin
check_admin_session_timeout();

// Determina se siamo nella pagina settings per mantenere il menu aperto
$is_settings_page = basename($_SERVER['PHP_SELF']) == 'settings.php';
$is_notifications_page = basename($_SERVER['PHP_SELF']) == 'notifications.php';
// MODIFICATO: Aggiunto 'reports.php' all'array delle pagine di moderazione
$is_moderation_page = in_array(basename($_SERVER['PHP_SELF']), ['moderation.php', 'brand-campaigns.php', 'sponsors.php', 'messages.php', 'conversation.php', 'reports.php']);
$is_pages_menu_page = basename($_SERVER['PHP_SELF']) == 'pages-menu.php';
$is_general_settings_page = basename($_SERVER['PHP_SELF']) == 'general-settings.php';
$is_communications_page = basename($_SERVER['PHP_SELF']) == 'communications.php'; // NUOVA VARIABILE PER COMUNICAZIONI
// NUOVA VARIABILE PER AMMINISTRAZIONE
$is_administration_page = in_array(basename($_SERVER['PHP_SELF']), ['users.php']);
// NUOVA VARIABILE PER TICKET
$is_tickets_page = basename($_SERVER['PHP_SELF']) == 'tickets.php';

// Funzione per contare ticket aperti (solo se le funzioni ticket esistono)
function get_open_tickets_count() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM tickets 
            WHERE status IN ('open', 'in_progress')
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Errore nel conteggio dei ticket aperti: " . $e->getMessage());
        return 0;
    }
}

$open_tickets_count = get_open_tickets_count();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Influencer Marketplace</title>
	<link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/favicon.ico">
    <meta name="msapplication-TileImage" content="/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Icone Bootstrap Icons per ticket -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: #fff;
            padding: 0.75rem 1rem;
            cursor: pointer;
        }
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .sidebar .nav-link.active {
            background-color: #007bff;
        }
        .main-content {
            padding: 20px;
        }
        .stat-card {
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .navbar-brand {
            font-weight: 600;
        }
        /* Stili per il sottomenu */
        .sidebar .nav-link.collapsed .fa-chevron-down {
            transition: transform 0.2s;
        }
        .sidebar .nav-link:not(.collapsed) .fa-chevron-down {
            transform: rotate(180deg);
        }
        .sidebar .nav .nav-link {
            padding-left: 2rem;
        }
        /* Stili aggiuntivi per il banner manutenzione permanente */
        .maintenance-banner {
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
            position: relative;
        }
        .maintenance-banner::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #ffc107;
            border-radius: 2px;
        }
        .maintenance-status-badge {
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        /* Stili per le comunicazioni */
        .admin-comm-alert {
            border-left: 4px solid #0d6efd;
            border-radius: 0.375rem;
        }
        .comm-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .comm-item {
            transition: all 0.3s ease;
        }
        .comm-item:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
        }
        /* Stili per i ticket */
        .badge-priority-low { background-color: #6c757d; }
        .badge-priority-medium { background-color: #0dcaf0; }
        .badge-priority-high { background-color: #fd7e14; }
        .badge-priority-urgent { background-color: #dc3545; }
        .ticket-status-open { color: #28a745; }
        .ticket-status-in_progress { color: #ffc107; }
        .ticket-status-resolved { color: #17a2b8; }
        .ticket-status-closed { color: #6c757d; }
        /* Responsive per le comunicazioni */
        @media (max-width: 768px) {
            .comm-actions {
                flex-direction: column;
                gap: 0.25rem;
            }
        }
        /* Badge per i ticket nella sidebar */
        .sidebar .nav-link .badge {
            font-size: 0.65rem;
            padding: 0.2rem 0.4rem;
            position: relative;
            top: -1px;
        }
    </style>
</head>
<body>
    <!-- Navbar Top -->
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/dashboard.php">
                <i class="fas fa-crown me-2"></i>Admin Panel
            </a>
            <div class="d-flex">
                <span class="navbar-text me-3">
                    <i class="fas fa-user-shield me-1"></i>
                    <strong><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></strong>
                    <?php if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin']): ?>
                        <span class="badge bg-danger ms-1">Super Admin</span>
                    <?php else: ?>
                        <span class="badge bg-secondary ms-1">Admin</span>
                    <?php endif; ?>
                </span>
                <div class="btn-group">
                    <a href="/" class="btn btn-outline-info btn-sm me-2" target="_blank">
                        <i class="fas fa-external-link-alt me-1"></i>Vedi Sito
                    </a>
                    <a href="/admin/logout.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar d-md-block collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="/admin/dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'influencers.php' ? 'active' : ''; ?>" href="/admin/influencers.php">
                                <i class="fas fa-users me-2"></i> Influencer
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'brands.php' ? 'active' : ''; ?>" href="/admin/brands.php">
                                <i class="fas fa-building me-2"></i> Brands
                            </a>
                        </li>
                        
                        <!-- Menu Moderazione a tendina -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $is_moderation_page ? '' : 'collapsed'; ?>" 
                               data-bs-toggle="collapse" 
                               href="#moderationSubmenu" 
                               role="button" 
                               aria-expanded="<?php echo $is_moderation_page ? 'true' : 'false'; ?>" 
                               aria-controls="moderationSubmenu">
                                <i class="fas fa-shield-alt me-2"></i> Moderazione
                                <i class="fas fa-chevron-down float-end mt-1"></i>
                            </a>
                            <div class="collapse <?php echo $is_moderation_page ? 'show' : ''; ?>" id="moderationSubmenu">
                                <ul class="nav flex-column ms-3">
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'brand-campaigns.php' ? 'active' : ''; ?>" 
                                           href="/admin/brand-campaigns.php">
                                            <i class="fas fa-bullhorn me-2"></i> Campagne Brand
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'sponsors.php' ? 'active' : ''; ?>" 
                                           href="/admin/sponsors.php">
                                            <i class="fas fa-star me-2"></i> Sponsor Influencer
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' || basename($_SERVER['PHP_SELF']) == 'conversation.php' ? 'active' : ''; ?>" 
                                           href="/admin/messages.php">
                                            <i class="fas fa-comment-alt me-2"></i> Messaggi
                                        </a>
                                    </li>
                                    <!-- NUOVA VOCE: Segnalazioni Utenti -->
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" 
                                           href="/admin/reports.php">
                                            <i class="fas fa-flag me-2"></i> Segnalazioni Utenti
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <!-- NUOVA VOCE: Ticket di supporto -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $is_tickets_page ? 'active' : ''; ?>" href="/admin/tickets.php">
                                <i class="bi bi-ticket-perforated me-2"></i> Ticket di supporto
                                <?php if ($open_tickets_count > 0): ?>
                                    <span class="badge bg-danger float-end mt-1"><?php echo $open_tickets_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>

                        <!-- NUOVO MENU: Amministrazione a tendina -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $is_administration_page ? '' : 'collapsed'; ?>" 
                               data-bs-toggle="collapse" 
                               href="#administrationSubmenu" 
                               role="button" 
                               aria-expanded="<?php echo $is_administration_page ? 'true' : 'false'; ?>" 
                               aria-controls="administrationSubmenu">
                                <i class="fas fa-user-shield me-2"></i> Amministrazione
                                <i class="fas fa-chevron-down float-end mt-1"></i>
                            </a>
                            <div class="collapse <?php echo $is_administration_page ? 'show' : ''; ?>" id="administrationSubmenu">
                                <ul class="nav flex-column ms-3">
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" 
                                           href="/admin/users.php">
                                            <i class="fas fa-users-cog me-2"></i> Utenti Admin
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <!-- Menu Impostazioni a tendina - MODIFICATO: Aggiunto Comunicazioni dopo Notifiche -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $is_settings_page || $is_notifications_page || $is_pages_menu_page || $is_general_settings_page || $is_communications_page ? '' : 'collapsed'; ?>" 
                               data-bs-toggle="collapse" 
                               href="#settingsSubmenu" 
                               role="button" 
                               aria-expanded="<?php echo $is_settings_page || $is_notifications_page || $is_pages_menu_page || $is_general_settings_page || $is_communications_page ? 'true' : 'false'; ?>" 
                               aria-controls="settingsSubmenu">
                                <i class="fas fa-cog me-2"></i> Impostazioni
                                <i class="fas fa-chevron-down float-end mt-1"></i>
                            </a>
                            <div class="collapse <?php echo $is_settings_page || $is_notifications_page || $is_pages_menu_page || $is_general_settings_page || $is_communications_page ? 'show' : ''; ?>" id="settingsSubmenu">
                                <ul class="nav flex-column ms-3">
                                    <!-- NUOVA VOCE GENERALI (PRIMA DI PAGINE E MENU) -->
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $is_general_settings_page ? 'active' : ''; ?>" 
                                           href="/admin/general-settings.php">
                                            <i class="fas fa-sliders-h me-2"></i> Generali
                                        </a>
                                    </li>
                                    <!-- VOCE ESISTENTE PAGINE E MENU -->
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $is_pages_menu_page ? 'active' : ''; ?>" 
                                           href="/admin/pages-menu.php">
                                            <i class="fas fa-file-alt me-2"></i> Pagine e Menu
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $is_notifications_page ? 'active' : ''; ?>" 
                                           href="/admin/notifications.php">
                                            <i class="fas fa-bell me-2"></i> Notifiche
                                        </a>
                                    </li>
                                    <!-- NUOVA VOCE COMUNICAZIONI -->
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $is_communications_page ? 'active' : ''; ?>" 
                                           href="/admin/communications.php">
                                            <i class="fas fa-bullhorn me-2"></i> Comunicazioni
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $is_settings_page ? 'active' : ''; ?>" 
                                           href="/admin/settings.php">
                                            <i class="fas fa-wrench me-2"></i> Modalità Manutenzione
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                
                <!-- Messaggi di notifica -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Banner Manutenzione (solo se attiva) - MODIFICATO: Rimosso alert e reso permanente -->
                <?php if (is_maintenance_mode($pdo)): ?>
                <div class="maintenance-banner bg-warning text-dark p-3 mb-4 border border-warning rounded">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-tools fa-2x me-3"></i>
                        <div class="flex-grow-1">
                            <h5 class="mb-1 fw-bold">🛠️ Modalità Manutenzione Attiva</h5>
                            <p class="mb-0">Il frontend del sito è temporaneamente non disponibile per gli utenti regolari. 
                            <a href="/admin/settings.php" class="fw-bold text-dark text-decoration-underline">Gestisci impostazioni</a></p>
                        </div>
                        <div class="maintenance-status-badge bg-dark text-warning px-3 py-2 rounded">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            MANUTENZIONE ATTIVA
                        </div>
                    </div>
                </div>
                <?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Gestione sidebar che ESCLUDE ESPLICITAMENTE gli accordion
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggles = document.querySelectorAll('.sidebar [data-bs-toggle="collapse"]');
    
    dropdownToggles.forEach(toggle => {
        // ESCLUDI ESPLICITAMENTE gli accordion con l'attributo data-exclude-sidebar-toggle
        if (toggle.closest('[data-exclude-sidebar-toggle="true"]')) {
            return; // Salta completamente gli accordion marcati
        }
        
        toggle.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            
            if (target && target.classList.contains('show')) {
                e.preventDefault();
                e.stopPropagation();
                
                const bsCollapse = bootstrap.Collapse.getInstance(target) || new bootstrap.Collapse(target, { toggle: false });
                bsCollapse.hide();
            } else {
                dropdownToggles.forEach(otherToggle => {
                    if (otherToggle !== this && !otherToggle.closest('[data-exclude-sidebar-toggle="true"]')) {
                        const otherTarget = document.querySelector(otherToggle.getAttribute('href'));
                        if (otherTarget && otherTarget.classList.contains('show')) {
                            const bsOtherCollapse = bootstrap.Collapse.getInstance(otherTarget) || new bootstrap.Collapse(otherTarget, { toggle: false });
                            bsOtherCollapse.hide();
                        }
                    }
                });
            }
        });
    });
    
    // Gestione dinamica delle comunicazioni (se presente)
    const commForm = document.getElementById('addCommunicationForm');
    if (commForm) {
        commForm.addEventListener('submit', function(e) {
            const message = this.querySelector('textarea[name="message"]');
            if (message && message.value.trim().length > 500) {
                e.preventDefault();
                alert('Il messaggio non può superare i 500 caratteri.');
                message.focus();
            }
        });
    }
    
    // Tooltip per i pulsanti
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>