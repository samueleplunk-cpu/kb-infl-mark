<?php

// Percorso assoluto per config
$config_file = dirname(__DIR__) . '/includes/config.php';

if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die("Config file not found: " . $config_file);
}

// Includi funzioni notifica
$notification_functions_file = dirname(__DIR__) . '/includes/notification_functions.php';
if (file_exists($notification_functions_file)) {
    require_once $notification_functions_file;
}

// Includi funzioni per le pagine
$page_functions_file = dirname(__DIR__) . '/includes/page_functions.php';
if (file_exists($page_functions_file)) {
    require_once $page_functions_file;
}

// Verifica sessione
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Includi functions.php per avere accesso alle funzioni
$functions_file = dirname(__DIR__) . '/includes/functions.php';
if (file_exists($functions_file)) {
    require_once $functions_file;
}

// INCLUSIONE SISTEMA MANUTENZIONE - AGGIUNTA IMPORTANTE
$maintenance_file = dirname(__DIR__) . '/includes/maintenance.php';
if (file_exists($maintenance_file)) {
    require_once $maintenance_file;
    
    // Controlla se siamo in una pagina pubblica (non admin)
    $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
    $is_admin_page = strpos($current_script, '/admin/') !== false;
    
    // Applica controllo manutenzione solo su pagine pubbliche
    if (!$is_admin_page) {
        check_maintenance_mode($pdo);
    }
}

// Conta messaggi non letti usando la funzione (se disponibile)
$unread_count = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if (function_exists('count_unread_messages')) {
        $unread_count = count_unread_messages($pdo, $_SESSION['user_id'], $_SESSION['user_type']);
    } else {
        // Fallback: usa la logica originale se la funzione non esiste
        try {
            if ($_SESSION['user_type'] === 'brand') {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as unread 
                    FROM messages m
                    JOIN conversations c ON m.conversation_id = c.id
                    JOIN brands b ON c.brand_id = b.id
                    WHERE b.user_id = ? AND m.sender_type = 'influencer' AND m.is_read = FALSE
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $unread_count = $result['unread'] ?? 0;
            } else if ($_SESSION['user_type'] === 'influencer') {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as unread 
                    FROM messages m
                    JOIN conversations c ON m.conversation_id = c.id
                    JOIN influencers i ON c.influencer_id = i.id
                    WHERE i.user_id = ? AND m.sender_type = 'brand' AND m.is_read = FALSE
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $unread_count = $result['unread'] ?? 0;
            }
        } catch (Exception $e) {
            // Silenzioso in caso di errore
            error_log("Errore nel conteggio messaggi non letti: " . $e->getMessage());
        }
    }
}

// Conta notifiche non lette
$unread_notifications_count = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && function_exists('count_unread_notifications')) {
    $unread_notifications_count = count_unread_notifications($pdo, $_SESSION['user_id'], $_SESSION['user_type']);
}

// Carica le impostazioni del menu brand se l'utente è loggato come brand
$header_brands_settings = [];
$main_menus = [];
$profile_menus = [];
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'brand' && function_exists('get_header_brands_settings')) {
    $header_brands_settings = get_header_brands_settings();
    $main_menus = $header_brands_settings['main_menus'] ?? [];
    $profile_menus = $header_brands_settings['profile_menus'] ?? [];
}

// Carica le impostazioni del menu influencer se l'utente è loggato come influencer
$header_influencers_settings = [];
$main_menus_influencers = [];
$profile_menus_influencers = [];
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'influencer' && function_exists('get_header_influencers_settings')) {
    $header_influencers_settings = get_header_influencers_settings();
    $main_menus_influencers = $header_influencers_settings['main_menus'] ?? [];
    $profile_menus_influencers = $header_influencers_settings['profile_menus'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php
        // Priorità per il titolo della pagina:
        // 1. $page_title (impostata da page.php o altre pagine)
        // 2. $meta_title (impostata da altre pagine che non usano $page_title)  
        // 3. Titolo predefinito
        if (isset($page_title) && !empty($page_title)) {
            echo htmlspecialchars($page_title);
        } elseif (isset($meta_title) && !empty($meta_title)) {
            echo htmlspecialchars($meta_title);
        } else {
            echo 'Influencer Marketplace';
        }
        ?>
    </title>
    
    <?php if (isset($page_description) && !empty($page_description)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <?php elseif (isset($meta_description) && !empty($meta_description)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <?php endif; ?>
    
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/favicon.ico">
    <meta name="msapplication-TileImage" content="/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <?php if (isset($og_tags) && !empty($og_tags)): ?>
    <!-- Open Graph tags (se forniti da page.php) -->
    <?php echo $og_tags; ?>
    <?php endif; ?>
    
    <style>
        .navbar-nav .nav-link {
            position: relative;
            padding: 0.5rem 1rem;
        }
        .badge {
            font-size: 0.7rem;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .message-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .notification-dropdown {
            min-width: 300px;
            max-width: 400px;
        }
        .notification-item {
            border-bottom: 1px solid #eee;
            padding: 0.5rem 0;
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item.unread {
            background-color: #f8f9fa;
        }
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <!-- Logo Dinamico per Brand e Influencer -->
            <a class="navbar-brand" href="/">
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'brand' && !empty($header_brands_settings['logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($header_brands_settings['logo_url']); ?>" 
                         alt="<?php echo htmlspecialchars($header_brands_settings['logo_text'] ?? 'Kibbiz'); ?>" 
                         style="max-height: 30px;">
                <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'brand'): ?>
                    <?php echo htmlspecialchars($header_brands_settings['logo_text'] ?? 'Kibbiz'); ?>
                <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'influencer' && !empty($header_influencers_settings['logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($header_influencers_settings['logo_url']); ?>" 
                         alt="<?php echo htmlspecialchars($header_influencers_settings['logo_text'] ?? 'Kibbiz'); ?>" 
                         style="max-height: 30px;">
                <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'influencer'): ?>
                    <?php echo htmlspecialchars($header_influencers_settings['logo_text'] ?? 'Kibbiz'); ?>
                <?php else: ?>
                    <i class="fas fa-store me-2"></i>Influencer Marketplace
                <?php endif; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['user_type'] === 'brand'): ?>
                            <!-- Menu Brand Dinamico -->
                            <?php if (!empty($main_menus)): ?>
                                <?php foreach ($main_menus as $menu): ?>
                                    <a class="nav-link <?php echo strtolower($menu['label']) === 'messaggi' ? 'position-relative' : ''; ?>" 
                                       href="<?php echo htmlspecialchars($menu['url']); ?>"
                                       <?php echo !empty($menu['target_blank']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                        <?php if (!empty($menu['icon'])): ?>
                                            <i class="<?php echo htmlspecialchars($menu['icon']); ?> me-1"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($menu['label']); ?>
                                        
                                        <?php if (strtolower($menu['label']) === 'messaggi' && $unread_count > 0): ?>
                                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger message-badge">
                                                <?php echo $unread_count; ?>
                                                <span class="visually-hidden">messaggi non letti</span>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Fallback al menu statico se non ci sono impostazioni dinamiche -->
                                <a class="nav-link" href="/brands/dashboard.php">
                                    <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                                </a>
                                <a class="nav-link" href="/brands/campaigns.php">
                                    <i class="fas fa-bullhorn me-1"></i> Campagne
                                </a>
                                <a class="nav-link position-relative" href="/brands/messages/conversation-list.php">
                                    <i class="fas fa-envelope me-1"></i> Messaggi
                                    <?php if ($unread_count > 0): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger message-badge">
                                            <?php echo $unread_count; ?>
                                            <span class="visually-hidden">messaggi non letti</span>
                                        </span>
                                    <?php endif; ?>
                                </a>
                                <a class="nav-link" href="/brands/search-influencers.php">
                                    <i class="fas fa-search me-1"></i> Cerca Influencer
                                </a>
                            <?php endif; ?>

                        <?php elseif ($_SESSION['user_type'] === 'influencer'): ?>
                            <!-- Menu Influencer Dinamico -->
                            <?php if (!empty($main_menus_influencers)): ?>
                                <?php foreach ($main_menus_influencers as $menu): ?>
                                    <a class="nav-link <?php echo strtolower($menu['label']) === 'messaggi' ? 'position-relative' : ''; ?>" 
                                       href="<?php echo htmlspecialchars($menu['url']); ?>"
                                       <?php echo !empty($menu['target_blank']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                        <?php if (!empty($menu['icon'])): ?>
                                            <i class="<?php echo htmlspecialchars($menu['icon']); ?> me-1"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($menu['label']); ?>
                                        
                                        <?php if (strtolower($menu['label']) === 'messaggi' && $unread_count > 0): ?>
                                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger message-badge">
                                                <?php echo $unread_count; ?>
                                                <span class="visually-hidden">messaggi non letti</span>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <!-- Fallback al menu statico se non ci sono impostazioni dinamiche -->
                                <a class="nav-link" href="/influencers/dashboard.php">
                                    <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                                </a>
                                <a class="nav-link" href="/influencers/campaigns.php">
                                    <i class="fas fa-bullhorn me-1"></i> Campagne
                                </a>
                                <a class="nav-link position-relative" href="/influencers/messages/conversation-list.php">
                                    <i class="fas fa-envelope me-1"></i> Messaggi
                                    <?php if ($unread_count > 0): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger message-badge">
                                            <?php echo $unread_count; ?>
                                            <span class="visually-hidden">messaggi non letti</span>
                                        </span>
                                    <?php endif; ?>
                                </a>
                                <a class="nav-link" href="/influencers/analytics.php">
                                    <i class="fas fa-chart-bar me-1"></i> Analytics
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Icona Notifiche -->
                        <div class="nav-item dropdown">
                            <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-bell me-1"></i>
                                <?php if ($unread_notifications_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $unread_notifications_count; ?>
                                        <span class="visually-hidden">notifiche non lette</span>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                                <li><h6 class="dropdown-header">Notifiche</h6></li>
                                <?php 
                                if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && function_exists('get_unread_notifications')) {
                                    $notifications = get_unread_notifications($pdo, $_SESSION['user_id'], $_SESSION['user_type'], 5);
                                    
                                    if (empty($notifications)): ?>
                                        <li><span class="dropdown-item text-muted">Nessuna notifica</span></li>
                                    <?php else: 
                                        foreach ($notifications as $notification): ?>
                                            <li class="notification-item unread">
                                                <div class="dropdown-item">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($notification['title']); ?></div>
                                                    <div class="small"><?php echo htmlspecialchars($notification['message']); ?></div>
                                                    <div class="notification-time">
                                                        <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-center" href="/<?php echo $_SESSION['user_type']; ?>s/settings.php#notifications">
                                                <small>Gestisci notifiche</small>
                                            </a>
                                        </li>
                                    <?php endif;
                                } else { ?>
                                    <li><span class="dropdown-item text-muted">Caricamento...</span></li>
                                <?php } ?>
                            </ul>
                        </div>
                        
                        <!-- Menu utente loggato -->
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i> 
                                <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Profilo'); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($_SESSION['user_type'] === 'brand'): ?>
                                    <?php if (!empty($profile_menus)): ?>
                                        <!-- Menu profilo dinamico per brand -->
                                        <?php foreach ($profile_menus as $menu): ?>
                                            <li>
                                                <a class="dropdown-item" href="<?php echo htmlspecialchars($menu['url']); ?>"
                                                   <?php echo !empty($menu['target_blank']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                                    <?php if (!empty($menu['icon'])): ?>
                                                        <i class="<?php echo htmlspecialchars($menu['icon']); ?> me-2"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($menu['label']); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <!-- Fallback al menu profilo statico -->
                                        <li><a class="dropdown-item" href="/brands/settings.php">
                                            <i class="fas fa-cog me-2"></i>Impostazioni
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="/auth/logout.php">
                                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                                        </a></li>
                                    <?php endif; ?>
                                <?php elseif ($_SESSION['user_type'] === 'influencer'): ?>
                                    <?php if (!empty($profile_menus_influencers)): ?>
                                        <!-- Menu profilo dinamico per influencer -->
                                        <?php foreach ($profile_menus_influencers as $menu): ?>
                                            <li>
                                                <a class="dropdown-item" href="<?php echo htmlspecialchars($menu['url']); ?>"
                                                   <?php echo !empty($menu['target_blank']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                                    <?php if (!empty($menu['icon'])): ?>
                                                        <i class="<?php echo htmlspecialchars($menu['icon']); ?> me-2"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($menu['label']); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
<li><a class="dropdown-item" href="/influencers/settings.php?action=personal-data">
    <i class="fas fa-user me-2"></i>Dati personali
</a></li>
                                        <li><a class="dropdown-item" href="/influencers/settings.php">
                                            <i class="fas fa-cog me-2"></i>Impostazioni
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="/auth/logout.php">
                                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                                        </a></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Menu utente non loggato -->
                        <a class="nav-link" href="/auth/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                        <a class="nav-link" href="/auth/register.php">
                            <i class="fas fa-user-plus me-1"></i> Registrati
                        </a>
                        <a class="nav-link" href="/about.php">
                            <i class="fas fa-info-circle me-1"></i> Info
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Messaggi di notifica -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show m-0 rounded-0" role="alert">
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show m-0 rounded-0" role="alert">
            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <main class="container mt-4">