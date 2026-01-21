<?php
// includes/config.php - VERSIONE SICURA PER PRODUZIONE

// === PERCORSO BASE ASSOLUTO === 
$base_dir = dirname(__DIR__); 
define('BASE_DIR', $base_dir);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'influencer_marketplace');
define('DB_USER', 'sam');
define('DB_PASS', 'A6Hd&Q%plvx4lxp7');

// Error reporting - PRODUZIONE: disabilita display errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

// === SESSION CONFIGURATION ===
$session_lifetime = 1209600; // 14 giorni in secondi

// Verifica se la sessione è già attiva
if (session_status() === PHP_SESSION_NONE) {
    // Configura i cookie della sessione prima di avviarla
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
} else {
    // Sessione già attiva, non chiamare session_set_cookie_params()
    error_log("Attenzione: sessione già attiva in config.php");
}

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, 
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection error. Please try again later.");
}

// Applica il timezone configurato
require_once __DIR__ . '/system_functions.php';
apply_system_timezone();

// Path constants
define('ROOT_PATH', BASE_DIR);
define('INCLUDES_PATH', BASE_DIR . '/includes');

// === UPLOAD CONFIGURATION ===
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB in bytes

// === BASE_URL CALCOLATO DINAMICAMENTE ===
$script_path = dirname($_SERVER['SCRIPT_NAME']);
$project_folder = basename(BASE_DIR);
define('BASE_URL', '/' . $project_folder);

// === COSTANTI PER MATCHING AVANZATO ===
define('MATCHING_MAX_RESULTS', 200);
define('MATCHING_RESULTS_PER_PAGE', 25);

// Tier system per calcolo budget limit
define('BUDGET_TIER_LOW_MAX', 200);
define('BUDGET_TIER_MEDIUM_MAX', 1000);
define('BUDGET_TIER_LOW_PERCENT', 0.5);
define('BUDGET_TIER_MEDIUM_PERCENT', 0.3);
define('BUDGET_TIER_HIGH_PERCENT', 0.2);

// Punteggi matching
define('SCORE_NICHE_EXACT', 35);
define('SCORE_NICHE_SIMILAR', 15);
define('SCORE_PLATFORMS_MAX', 30);
define('SCORE_AFFORDABILITY_MAX', 20);
define('SCORE_RATING_MAX', 15);
define('SCORE_VIEWS_BONUS', 5);

// Soglie affordability
define('AFFORDABILITY_PENALTY_1_1X', 5);
define('AFFORDABILITY_PENALTY_1_5X', 10);
define('AFFORDABILITY_PENALTY_2X', 20);

// Soglie match score per badge
define('SCORE_PERFECT_MATCH_MIN', 70);
define('SCORE_RECOMMENDED_MATCH_MIN', 40);

// Flag per evitare inclusioni multiple
$config_loaded = true;

// === SISTEMA MANUTENZIONE INTEGRATO ===
define('SKIP_MAINTENANCE_CHECK', false);

/**
 * Controllo manutenzione automatico per tutte le pagine pubbliche
 */
function initialize_maintenance_system($pdo) {
    $maintenance_file = __DIR__ . '/maintenance.php';
    
    if (!file_exists($maintenance_file)) {
        return false;
    }
    
    require_once $maintenance_file;
    
    if (should_check_maintenance()) {
        check_maintenance_mode($pdo);
    }
    
    return true;
}

/**
 * Determina se eseguire il controllo manutenzione per la richiesta corrente
 */
function should_check_maintenance() {
    if (defined('SKIP_MAINTENANCE_CHECK') && SKIP_MAINTENANCE_CHECK === true) {
        return false;
    }
    
    $current_script = $_SERVER['SCRIPT_NAME'] ?? '';
    $current_uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // File di configurazione e inclusi
    $excluded_scripts = [
        'config.php',
        'maintenance.php',
        'auth_functions.php',
        'admin_functions.php',
        'email_functions.php',
        'functions.php'
    ];
    
    foreach ($excluded_scripts as $script) {
        if (strpos($current_script, $script) !== false || 
            basename($current_script) === $script) {
            return false;
        }
    }
    
    // Percorsi admin
    $admin_paths = ['/admin/', '/admin/', '/backend/'];
    foreach ($admin_paths as $admin_path) {
        if (strpos($current_script, $admin_path) !== false || 
            strpos($current_uri, $admin_path) !== false) {
            return false;
        }
    }
    
    // API, AJAX, webhooks
    $api_paths = ['/api/', '/ajax/', '/webhook/', '/callback/', '/payment/'];
    foreach ($api_paths as $api_path) {
        if (strpos($current_uri, $api_path) !== false) {
            return false;
        }
    }
    
    // File statici e assets
    $static_paths = ['/assets/', '/assets/', '/css/', '/js/', '/img/', '/images/'];
    foreach ($static_paths as $static_path) {
        if (strpos($current_uri, $static_path) !== false) {
            return false;
        }
    }
    
    // File di sistema
    $system_files = [
        'robots.txt',
        'sitemap.xml',
        '.well-known/',
        'favicon.ico',
        'health-check.php',
        'server-status'
    ];
    
    foreach ($system_files as $file) {
        if (strpos($current_uri, $file) !== false) {
            return false;
        }
    }
    
    // Richieste specifiche per manutenzione
    if (strpos($current_uri, 'maintenance-check') !== false || 
        strpos($current_uri, 'test-maintenance') !== false) {
        return false;
    }
    
    // Controlla se è una richiesta AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return false;
    }
    
    // Controlla se è una richiesta per bot/SEO
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $bots = ['googlebot', 'bingbot', 'slurp', 'duckduckbot'];
    foreach ($bots as $bot) {
        if (stripos($user_agent, $bot) !== false) {
            return false;
        }
    }
    
    return true;
}

// Inizializzazione sistema manutenzione
try {
    initialize_maintenance_system($pdo);
} catch (Exception $e) {
    // Silenzioso in produzione
}

// === FUNZIONI PER MATCHING AVANZATO ===

/**
 * Calcola il budget limit in base al tier system
 */
function calculate_budget_limit($campaign_budget) {
    if ($campaign_budget <= BUDGET_TIER_LOW_MAX) {
        return $campaign_budget * BUDGET_TIER_LOW_PERCENT;
    } elseif ($campaign_budget <= BUDGET_TIER_MEDIUM_MAX) {
        return $campaign_budget * BUDGET_TIER_MEDIUM_PERCENT;
    } else {
        return $campaign_budget * BUDGET_TIER_HIGH_PERCENT;
    }
}

/**
 * Calcola lo score di matching avanzato per un influencer
 */
function calculate_advanced_match_score($influencer, $campaign_niche, $campaign_platforms, $budget_limit) {
    $score = 0;
    $match_details = [];
    
    // 1. NICHE MATCHING (50 punti totali)
    if ($influencer['niche'] === $campaign_niche) {
        $score += SCORE_NICHE_EXACT;
        $match_details['niche'] = 'exact';
        $match_details['niche_score'] = SCORE_NICHE_EXACT;
    } else {
        $influencer_niche_lower = strtolower($influencer['niche']);
        $campaign_niche_lower = strtolower($campaign_niche);
        
        if (strpos($influencer_niche_lower, $campaign_niche_lower) !== false || 
            strpos($campaign_niche_lower, $influencer_niche_lower) !== false) {
            $score += SCORE_NICHE_SIMILAR;
            $match_details['niche'] = 'similar';
            $match_details['niche_score'] = SCORE_NICHE_SIMILAR;
        } else {
            $match_details['niche'] = 'none';
            $match_details['niche_score'] = 0;
        }
    }
    
    // 2. PLATFORM MATCHING (30 punti)
    $platform_matches = 0;
    $total_platforms = count($campaign_platforms);
    
    foreach ($campaign_platforms as $platform) {
        switch ($platform) {
            case 'instagram':
                if (!empty($influencer['instagram_handle'])) $platform_matches++;
                break;
            case 'tiktok':
                if (!empty($influencer['tiktok_handle'])) $platform_matches++;
                break;
            case 'youtube':
                if (!empty($influencer['youtube_handle'])) $platform_matches++;
                break;
            case 'facebook':
                if (!empty($influencer['facebook_handle'])) $platform_matches++;
                break;
            case 'twitter':
                if (!empty($influencer['twitter_handle'])) $platform_matches++;
                break;
        }
    }
    
    if ($total_platforms > 0) {
        $platform_score = ($platform_matches / $total_platforms) * SCORE_PLATFORMS_MAX;
        $score += $platform_score;
        $match_details['platforms'] = [
            'matches' => $platform_matches,
            'total' => $total_platforms,
            'score' => round($platform_score, 2)
        ];
    } else {
        $match_details['platforms'] = [
            'matches' => 0,
            'total' => 0,
            'score' => 0
        ];
    }
    
    // 3. AFFORDABILITY SCORING (20 punti)
    $influencer_rate = floatval($influencer['rate']);
    $budget_limit = floatval($budget_limit);
    
    if ($influencer_rate <= $budget_limit) {
        $affordability_score = SCORE_AFFORDABILITY_MAX;
        $match_details['affordability'] = 'within_budget';
    } else {
        $ratio = $influencer_rate / $budget_limit;
        
        if ($ratio <= 1.1) {
            $affordability_score = SCORE_AFFORDABILITY_MAX - AFFORDABILITY_PENALTY_1_1X;
            $match_details['affordability'] = 'slightly_over';
        } elseif ($ratio <= 1.5) {
            $affordability_score = SCORE_AFFORDABILITY_MAX - AFFORDABILITY_PENALTY_1_5X;
            $match_details['affordability'] = 'moderately_over';
        } else {
            $affordability_score = SCORE_AFFORDABILITY_MAX - AFFORDABILITY_PENALTY_2X;
            $match_details['affordability'] = 'significantly_over';
        }
        
        $match_details['budget_ratio'] = round($ratio, 2);
        $match_details['over_amount'] = $influencer_rate - $budget_limit;
    }
    
    $score += $affordability_score;
    $match_details['affordability_score'] = $affordability_score;
    
    // 4. QUALITÀ INFLUENCER (20 punti)
    $rating = floatval($influencer['rating']);
    $rating_score = ($rating / 5) * SCORE_RATING_MAX;
    $score += $rating_score;
    $match_details['rating_score'] = round($rating_score, 2);
    $match_details['rating'] = $rating;
    
    // Bonus profile views
    $profile_views = intval($influencer['profile_views']);
    $views_bonus = 0;
    
    if ($profile_views > 10000) {
        $views_bonus = SCORE_VIEWS_BONUS;
    } elseif ($profile_views > 5000) {
        $views_bonus = 3;
    } elseif ($profile_views > 1000) {
        $views_bonus = 1;
    }
    
    $score += $views_bonus;
    $match_details['views_bonus'] = $views_bonus;
    $match_details['profile_views'] = $profile_views;
    
    // Score finale
    $final_score = min(round($score, 2), 100);
    $match_details['final_score'] = $final_score;
    $match_details['total_score_breakdown'] = $score;
    
    return [
        'score' => $final_score,
        'details' => $match_details
    ];
}

/**
 * Restituisce il badge per il tipo di match
 */
function get_match_badge($match_score, $match_details) {
    $details = json_decode($match_details, true);
    
    if ($match_score >= SCORE_PERFECT_MATCH_MIN) {
        return '<span class="badge bg-success">🎯 Match Perfetto</span>';
    } elseif ($match_score >= SCORE_RECOMMENDED_MATCH_MIN) {
        if (isset($details['affordability']) && $details['affordability'] !== 'within_budget') {
            return '<span class="badge bg-warning text-dark">💰 Budget Elevato</span>';
        } elseif (isset($details['niche']) && $details['niche'] === 'similar') {
            return '<span class="badge bg-info">📈 Niche Correlato</span>';
        } else {
            return '<span class="badge bg-primary">👍 Match Consigliato</span>';
        }
    } else {
        return '<span class="badge bg-secondary">📋 Match Base</span>';
    }
}

/**
 * Restituisce l'indicatore di affordability
 */
function get_affordability_indicator($influencer_rate, $budget_limit) {
    $rate = floatval($influencer_rate);
    $limit = floatval($budget_limit);
    
    if ($rate <= $limit) {
        return '<span class="badge bg-success">✅ Nel budget</span>';
    } else {
        $over_amount = $rate - $limit;
        $ratio = $rate / $limit;
        
        if ($ratio <= 1.1) {
            return '<span class="badge bg-warning text-dark">⚠️ €' . number_format($over_amount, 2) . ' over</span>';
        } elseif ($ratio <= 1.5) {
            return '<span class="badge bg-warning">⚠️ €' . number_format($over_amount, 2) . ' over</span>';
        } else {
            return '<span class="badge bg-danger">❌ Budget insufficiente</span>';
        }
    }
}

/**
 * Esegue il matching avanzato a due fasi
 */
function perform_advanced_influencer_matching($pdo, $campaign_id, $campaign_niche, $campaign_platforms, $campaign_budget) {
    $debug_info = [];
    $debug_info[] = "=== ADVANCED INFLUENCER MATCHING ===";
    $debug_info[] = "Campaign ID: $campaign_id";
    $debug_info[] = "Campaign Niche: $campaign_niche";
    $debug_info[] = "Campaign Budget: €$campaign_budget";
    
    $budget_limit = calculate_budget_limit($campaign_budget);
    $debug_info[] = "Budget Limit (Tier System): €$budget_limit";
    
    $platform_conditions = [];
    $platform_params = [];
    
    if (in_array('instagram', $campaign_platforms)) {
        $platform_conditions[] = "(i.instagram_handle IS NOT NULL AND i.instagram_handle != '')";
    }
    if (in_array('tiktok', $campaign_platforms)) {
        $platform_conditions[] = "(i.tiktok_handle IS NOT NULL AND i.tiktok_handle != '')";
    }
    if (in_array('youtube', $campaign_platforms)) {
        $platform_conditions[] = "(i.youtube_handle IS NOT NULL AND i.youtube_handle != '')";
    }
    if (in_array('facebook', $campaign_platforms)) {
        $platform_conditions[] = "(i.facebook_handle IS NOT NULL AND i.facebook_handle != '')";
    }
    if (in_array('twitter', $campaign_platforms)) {
        $platform_conditions[] = "(i.twitter_handle IS NOT NULL AND i.twitter_handle != '')";
    }
    
    $platform_where = implode(' OR ', $platform_conditions);
    if (empty($platform_where)) {
        $platform_where = "1=1";
    }
    
    $debug_info[] = "Platform Conditions: $platform_where";
    $debug_info[] = "Platforms selected: " . implode(', ', $campaign_platforms);
    
    // FASE 1: MATCHING STRETTO (Niche esatto)
    $phase1_results = [];
    $phase1_query = "
        SELECT i.*, u.email 
        FROM influencers i 
        JOIN users u ON i.user_id = u.id 
        WHERE i.niche = ? 
        AND ($platform_where)
        AND i.id NOT IN (SELECT influencer_id FROM campaign_influencers WHERE campaign_id = ?)
        ORDER BY i.rating DESC, i.profile_views DESC
        LIMIT " . MATCHING_MAX_RESULTS . "
    ";
    
    $phase1_params = [$campaign_niche, $campaign_id];
    
    try {
        $stmt = $pdo->prepare($phase1_query);
        $stmt->execute($phase1_params);
        $phase1_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $debug_info[] = "PHASE 1 (Exact Niche): Found " . count($phase1_results) . " influencers";
    } catch (PDOException $e) {
        $debug_info[] = "ERROR Phase 1: " . $e->getMessage();
    }
    
    // FASE 2: MATCHING ALLARGATO (solo se necessario)
    $phase2_results = [];
    $remaining_slots = MATCHING_MAX_RESULTS - count($phase1_results);
    
    if ($remaining_slots > 0) {
        $debug_info[] = "Starting PHASE 2 - Remaining slots: $remaining_slots";
        
        $phase2_query = "
            SELECT i.*, u.email 
            FROM influencers i 
            JOIN users u ON i.user_id = u.id 
            WHERE (i.niche LIKE CONCAT('%', ?, '%') OR ? LIKE CONCAT('%', i.niche, '%'))
            AND i.niche != ?
            AND ($platform_where)
            AND i.id NOT IN (SELECT influencer_id FROM campaign_influencers WHERE campaign_id = ?)
            AND i.id NOT IN (SELECT id FROM (SELECT id FROM influencers WHERE niche = ?) AS exact_matches)
            ORDER BY i.rating DESC, i.profile_views DESC
            LIMIT $remaining_slots
        ";
        
        $phase2_params = [$campaign_niche, $campaign_niche, $campaign_niche, $campaign_id, $campaign_niche];
        
        try {
            $stmt = $pdo->prepare($phase2_query);
            $stmt->execute($phase2_params);
            $phase2_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $debug_info[] = "PHASE 2 (Similar Niche): Found " . count($phase2_results) . " influencers";
        } catch (PDOException $e) {
            $debug_info[] = "ERROR Phase 2: " . $e->getMessage();
        }
    } else {
        $debug_info[] = "PHASE 2 skipped - Maximum results already reached";
    }
    
    // Combina risultati
    $all_influencers = array_merge($phase1_results, $phase2_results);
    $debug_info[] = "TOTAL MATCHES: " . count($all_influencers);
    
    // Calcola score per ogni influencer
    $scored_influencers = [];
    foreach ($all_influencers as $influencer) {
        $match_result = calculate_advanced_match_score($influencer, $campaign_niche, $campaign_platforms, $budget_limit);
        
        $scored_influencer = $influencer;
        $scored_influencer['match_score'] = $match_result['score'];
        $scored_influencer['match_details'] = json_encode($match_result['details']);
        
        $scored_influencers[] = $scored_influencer;
    }
    
    // Ordina per score
    usort($scored_influencers, function($a, $b) {
        return $b['match_score'] <=> $a['match_score'];
    });
    
    // Inserisci nella tabella campaign_influencers
    $inserted_count = 0;
    foreach ($scored_influencers as $influencer) {
        try {
            $insert_stmt = $pdo->prepare("
                INSERT INTO campaign_influencers (campaign_id, influencer_id, match_score, match_details, status)
                VALUES (?, ?, ?, ?, 'pending')
                ON DUPLICATE KEY UPDATE 
                    match_score = VALUES(match_score),
                    match_details = VALUES(match_details),
                    updated_at = NOW()
            ");
            $insert_stmt->execute([
                $campaign_id, 
                $influencer['id'], 
                $influencer['match_score'],
                $influencer['match_details']
            ]);
            $inserted_count++;
        } catch (PDOException $e) {
            // Silenzioso in produzione
        }
    }
    
    $debug_info[] = "INSERTED INTO DATABASE: $inserted_count influencers";
    
    // Salva debug in session
    $_SESSION['matching_debug'] = $debug_info;
    
    return $scored_influencers;
}

// === INCLUSIONE FUNZIONI DI AUTENTICAZIONE ===
$auth_functions_file = __DIR__ . '/auth_functions.php';
if (file_exists($auth_functions_file)) {
    require_once $auth_functions_file;
} else {
    if (!function_exists('is_logged_in')) {
        function is_logged_in() {
            return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
        }
    }
    if (!function_exists('is_influencer')) {
        function is_influencer() {
            return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'influencer';
        }
    }
    if (!function_exists('login_user')) {
        function login_user($user_id, $user_type, $name) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_type'] = $user_type;
            $_SESSION['user_name'] = $name;
            $_SESSION['login_time'] = time();
        }
    }
}

// === INCLUSIONE FUNZIONI EMAIL ===
$email_functions_file = __DIR__ . '/email_functions.php';
if (file_exists($email_functions_file)) {
    require_once $email_functions_file;
} else {
    if (!function_exists('send_password_reset_email')) {
        function send_password_reset_email($email, $reset_link) {
            $subject = "Recupero Password - Influencer Marketplace";
            $message = "Clicca qui per reimpostare la password: $reset_link";
            $headers = "From: no-reply@influencer-marketplace.com";
            
            return mail($email, $subject, $message, $headers);
        }
    }
    if (!function_exists('send_password_changed_email')) {
        function send_password_changed_email($email) {
            return true;
        }
    }
}

// === INCLUSIONE FUNZIONI NOTIFICA ===
$notification_functions_file = __DIR__ . '/notification_functions.php';
if (file_exists($notification_functions_file)) {
    require_once $notification_functions_file;
}

// === CONTROLLO AUTOMATICO SCADENZE CAMPAGNE ===
function initializeCampaignExpirationCheck() {
    global $pdo;
    
    // Esegui il controllo solo occasionalmente (1 volta su 10) per performance
    if (mt_rand(1, 10) === 1) {
        try {
            // Verifica se la funzione esiste già
            if (function_exists('checkExpiredPausedCampaigns')) {
                checkExpiredPausedCampaigns();
            } else {
                // Fallback: esegui direttamente la query se la funzione non è disponibile
                $query = "
                    UPDATE campaigns 
                    SET status = 'expired', 
                        updated_at = NOW() 
                    WHERE status = 'paused' 
                    AND deadline_date IS NOT NULL 
                    AND deadline_date < CURDATE()
                ";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute();
                
                $expired_count = $stmt->rowCount();
                if ($expired_count > 0) {
                    error_log("Auto-expired $expired_count paused campaigns (fallback)");
                }
            }
        } catch (Exception $e) {
            // Silenzioso in produzione
            error_log("Error in campaign expiration check: " . $e->getMessage());
        }
    }
}

// Inizializza il controllo scadenze
initializeCampaignExpirationCheck();

// === FUNZIONI UTILITY AGGIUNTIVE ===
if (!function_exists('generate_secure_token')) {
    function generate_secure_token($length = 50) {
        return bin2hex(random_bytes($length));
    }
}

if (!function_exists('validate_token')) {
    function validate_token($pdo, $token) {
        try {
            $stmt = $pdo->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            $reset_request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reset_request) {
                return ['valid' => false, 'error' => 'Token non valido'];
            }
            
            if ($reset_request['used'] == 1) {
                return ['valid' => false, 'error' => 'Token già utilizzato'];
            }
            
            if (strtotime($reset_request['expires_at']) < time()) {
                return ['valid' => false, 'error' => 'Token scaduto'];
            }
            
            return [
                'valid' => true, 
                'email' => $reset_request['email'],
                'expires_at' => $reset_request['expires_at']
            ];
            
        } catch (PDOException $e) {
            return ['valid' => false, 'error' => 'Errore di sistema'];
        }
    }
}

if (!function_exists('cleanup_expired_tokens')) {
    function cleanup_expired_tokens($pdo) {
        try {
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            return 0;
        }
    }
}

// Pulizia automatica dei token scaduti
if (mt_rand(1, 100) === 1) {
    cleanup_expired_tokens($pdo);
}

// === VERIFICA CONFIGURAZIONE MATCHING ===
if (!defined('MATCHING_MAX_RESULTS')) {
    // Silenzioso in produzione
}
?>