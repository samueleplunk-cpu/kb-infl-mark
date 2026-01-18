<?php
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
// INCLUSIONE HEADER CON PERCORSO ASSOLUTO
// =============================================
$header_file = dirname(__DIR__) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;

// =============================================
// RECUPERO DATI INFLUENCER DAL DATABASE
// =============================================
$influencer = null;
$error = '';
$success = '';

try {
    $stmt = $pdo->prepare("
        SELECT id, user_id, full_name, bio, niche, 
           instagram_handle, tiktok_handle, youtube_handle, 
           facebook_handle, pinterest_handle, telegram_handle, 
           twitch_handle, threads_handle, website, rate, nationality,
           profile_image, profile_views, rating,
           created_at, updated_at 
    FROM influencers 
    WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo: " . $e->getMessage();
}

// =============================================
// RECUPERO CATEGORIE DAL DATABASE
// =============================================
$categories = [];
try {
    require_once dirname(__DIR__) . '/includes/category_functions.php';
    $categories = get_active_categories($pdo);
} catch (Exception $e) {
    // Silenzioso
}

// =============================================
// RECUPERO CANDIDATURE PER LA SEZIONE AGGIUNTA
// =============================================
$applications = [];
$application_stats = [
    'total_applications' => 0,
    'accepted_applications' => 0,
    'pending_applications' => 0
];

if ($influencer) {
    try {
        $stmt = $pdo->prepare("
            SELECT ca.*, c.name as campaign_name, c.budget, b.company_name,
                   ca.status, ca.created_at as application_date
            FROM campaign_applications ca
            JOIN campaigns c ON ca.campaign_id = c.id
            JOIN brands b ON c.brand_id = b.id
            WHERE ca.influencer_id = ?
            AND c.deleted_at IS NULL
            ORDER BY ca.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$influencer['id']]);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // MODIFICA QUI: Aggiungere JOIN con campaigns e filtro per deleted_at IS NULL
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_applications,
                   COUNT(CASE WHEN ca.status = 'accepted' THEN 1 END) as accepted_applications,
                   COUNT(CASE WHEN ca.status = 'pending' THEN 1 END) as pending_applications
            FROM campaign_applications ca
            JOIN campaigns c ON ca.campaign_id = c.id
            WHERE ca.influencer_id = ?
            AND c.deleted_at IS NULL
        ");
        $stmt->execute([$influencer['id']]);
        $application_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application_stats) {
            $application_stats = [
                'total_applications' => 0,
                'accepted_applications' => 0,
                'pending_applications' => 0
            ];
        }
        
    } catch (PDOException $e) {
        $applications = [];
        $application_stats = [
            'total_applications' => 0,
            'accepted_applications' => 0,
            'pending_applications' => 0
        ];
    }
}

?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Dashboard Influencer</h2>
            <?php if (!$influencer): ?>
                <a href="create-profile.php" class="btn btn-primary">
                    Crea Profilo Influencer
                </a>
            <?php else: ?>
                <a href="/auth/logout.php" class="btn btn-outline-primary">
                    Logout
                </a>
            <?php endif; ?>
        </div>
		
		<!-- Comunicazioni Admin -->
<?php
// Includi le funzioni delle comunicazioni
require_once dirname(__DIR__) . '/includes/communication_functions.php';

// Recupera le comunicazioni per influencer
$communications = get_admin_communications($pdo, 'influencer');

// Verifica se l'utente ha nascosto le comunicazioni
$hidden_comms = isset($_SESSION['hidden_admin_comms']) ? $_SESSION['hidden_admin_comms'] : [];
$visible_comms = array_filter($communications, function($comm) use ($hidden_comms) {
    return !in_array($comm['id'], $hidden_comms);
});

if (!empty($visible_comms)): ?>
<div class="row mb-4">
    <div class="col-12">
        <?php foreach ($visible_comms as $comm): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert" id="admin-comm-<?php echo $comm['id']; ?>">
                <div class="d-flex align-items-center">
                    <i class="fas fa-bullhorn me-3 fa-lg"></i>
                    <div class="flex-grow-1">
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($comm['message'])); ?></p>
                        <?php if (!empty($comm['link'])): ?>
                            <a href="<?php echo htmlspecialchars($comm['link']); ?>" 
                               target="_blank" 
                               class="alert-link">
                                <i class="fas fa-external-link-alt me-1"></i>Maggiori informazioni
                            </a>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn-close" 
                            onclick="hideAdminCommunication(<?php echo $comm['id']; ?>)">
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
function hideAdminCommunication(commId) {
    // Nasconde l'alert
    document.getElementById('admin-comm-' + commId).style.display = 'none';
    
    // Salva nello storage di sessione (cookie fallback)
    try {
        // Prova con AJAX per salvare nella sessione PHP
        fetch('/includes/hide_communication.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'comm_id=' + commId
        });
    } catch (error) {
        console.log('Comunicazione nascosta localmente');
    }
}
</script>

        <!-- Messaggi di stato -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == 'profile_created'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Profilo creato con successo!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Aggiungi questa sezione per gestire l'alert di modifica profilo -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['success']); ?>
                <?php unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Sezione: Profilo Mancante -->
        <?php if (!$influencer): ?>
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h4 class="card-title text-warning">Profilo Non Creato</h4>
                    <p class="card-text">
                        Per accedere alle funzionalità complete della piattaforma, 
                        devi prima creare il tuo profilo influencer.
                    </p>
                    <a href="create-profile.php" class="btn btn-warning btn-lg">
                        Crea il Tuo Profilo Ora
                    </a>
                </div>
            </div>

        <!-- Sezione: Profilo Esistente -->
        <?php else: ?>
            <div class="row">
                <!-- Immagine Profilo e Dati Base -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Profilo</h5>
                        </div>
                        <div class="card-body text-center">
                            <?php 
                            $profile_image_src = '/uploads/placeholder/influencer_admin_edit.png';
                            if (!empty($influencer['profile_image'])) {
                                $full_image_path = dirname(__DIR__) . '/uploads/' . $influencer['profile_image'];
                                if (file_exists($full_image_path)) {
                                    $profile_image_src = '/uploads/' . $influencer['profile_image'];
                                }
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($profile_image_src); ?>" 
                                 class="rounded-circle mb-3" 
                                 alt="Profile Image" 
                                 style="width: 150px; height: 150px; object-fit: cover;">
                               
                            <h4><?php echo htmlspecialchars_decode($influencer['full_name']); ?></h4>
                            <?php if (!empty($influencer['niche'])): ?>
                                <?php 
                                $display_niche = htmlspecialchars_decode($influencer['niche']);
                                $found_category_name = $display_niche;
                                
                                // Cerca il nome della categoria nel database
                                foreach ($categories as $cat) {
                                    if (is_array($cat)) {
                                        $cat_slug = $cat['slug'] ?? '';
                                        $cat_name = $cat['name'] ?? '';
                                        
                                        // Confronta con lo slug o il nome
                                        if (strtolower($cat_slug) === strtolower($display_niche) || 
                                            strtolower($cat_name) === strtolower($display_niche)) {
                                            $found_category_name = $cat_name;
                                            break;
                                        }
                                    }
                                }
                                
                                // Fallback per mappature comuni
                                $niche_display_map = [
                                    'beauty' => 'Beauty & Makeup',
                                    'fitness' => 'Fitness & Wellness',
                                    'finance' => 'Finance & Business',
                                    'entertainment' => 'Entertainment',
                                    'pet' => 'Pet',
                                    'education' => 'Education',
                                    'fashion' => 'Fashion',
                                    'lifestyle' => 'Lifestyle',
                                    'food' => 'Food',
                                    'travel' => 'Travel',
                                    'gaming' => 'Gaming',
                                    'tech' => 'Tech'
                                ];
                                
                                $lower_niche = strtolower(trim($found_category_name));
                                if (isset($niche_display_map[$lower_niche])) {
                                    $found_category_name = $niche_display_map[$lower_niche];
                                }
                                ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($found_category_name); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Dettagli Profilo -->
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Dettagli Profilo</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php 
                                // Ottieni tutti i social network attivi
                                $social_networks = get_active_social_networks();
                                
                                // Filtra solo i social network con handle compilato (non vuoto)
                                $configured_socials = [];
                                foreach ($social_networks as $social) {
                                    $handle_field = $social['slug'] . '_handle';
                                    
                                    // Verifica se l'handle esiste e non è vuoto
                                    if (isset($influencer[$handle_field])) {
                                        $handle_value = trim($influencer[$handle_field]);
                                        if (!empty($handle_value)) {
                                            $configured_socials[] = [
                                                'social' => $social,
                                                'handle_value' => $handle_value,
                                                'handle_field' => $handle_field
                                            ];
                                        }
                                    }
                                }
                                
                                // Se non ci sono social configurati, mostra un messaggio
                                if (empty($configured_socials)): ?>
                                    <div class="col-12">
                                        <p class="text-muted text-center mb-0">Nessun account social configurato</p>
                                    </div>
                                <?php else: 
                                    // Divide i social configurati in due colonne
                                    $half_count = ceil(count($configured_socials) / 2);
                                    $counter = 0;
                                ?>
                                    <div class="col-md-6">
                                        <?php foreach ($configured_socials as $item): 
                                            $counter++;
                                            $social = $item['social'];
                                            $handle_value = $item['handle_value'];
                                        ?>
                                            <div class="mb-3">
                                                <strong><?php echo htmlspecialchars($social['name']); ?>:</strong>
                                                <span class="float-end">
                                                    <?php 
                                                    // Platformi che non usano @: YouTube, Website, Twitch, Threads
                                                    $no_at_prefix = ['youtube', 'website', 'twitch', 'threads'];
                                                    if (in_array($social['slug'], $no_at_prefix)) {
                                                        echo htmlspecialchars($handle_value);
                                                    } else {
                                                        // Platformi che usano @: Instagram, TikTok, Facebook, Pinterest, Telegram
                                                        echo htmlspecialchars($handle_value);
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                            
                                            <?php if ($counter === $half_count): ?>
                                                </div><div class="col-md-6">
                                            <?php endif; ?>
                                            
                                        <?php endforeach; ?>
                                        
                                        <!-- Se il numero di social è dispari, assicurati che il div sia chiuso -->
                                        <?php if ($counter % 2 !== 0): ?>
                                            </div><div class="col-md-6">
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Colonna destra con tariffa, visualizzazioni e rating -->
                                <div class="col-md-6">
                                    <!-- NUOVA RIGA NAZIONALITÀ -->
                                    <div class="mb-3">
                                        <strong>Nazione:</strong>
                                        <span class="float-end">
                                            <?php echo !empty($influencer['nationality']) ? htmlspecialchars($influencer['nationality']) : 'N/D'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <strong>Tariffa:</strong>
                                        <span class="float-end">€<?php echo !empty($influencer['rate']) ? number_format($influencer['rate'], 2) : '0.00'; ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Visualizzazioni:</strong>
                                        <span class="float-end"><?php echo number_format($influencer['profile_views'] ?? 0); ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Rating:</strong>
                                        <span class="float-end">
                                            <?php 
                                            if (!empty($influencer['rating']) && $influencer['rating'] > 0) {
                                                echo number_format($influencer['rating'], 1) . '/5';
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($influencer['website'])): ?>
                                <div class="mb-3">
                                    <strong>Website:</strong>
                                    <span class="float-end">
                                        <a href="<?php echo htmlspecialchars($influencer['website']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($influencer['website']); ?>
                                        </a>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistiche Completamento Profilo -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Completamento Profilo</h5>
                </div>
                <div class="card-body">
                    <?php 
                    $completed = 0;
                    $total_fields = 8;
                    
                    if (!empty($influencer['full_name'])) $completed++;
                    if (!empty($influencer['bio'])) $completed++;
                    if (!empty($influencer['niche'])) $completed++;
                    if (!empty($influencer['instagram_handle'])) $completed++;
                    if (!empty($influencer['rate'])) $completed++;
                    if (!empty($influencer['profile_image'])) $completed++;
                    if (!empty($influencer['website'])) $completed++;
                    if (!empty($influencer['instagram_handle']) || !empty($influencer['tiktok_handle']) || !empty($influencer['youtube_handle'])) $completed++;
                    
                    $percentage = round(($completed / $total_fields) * 100);
                    ?>
                    <div class="mb-3">
                        <strong>Profilo Completato:</strong>
                        <span class="float-end"><?php echo $completed . '/' . $total_fields . ' (' . $percentage . '%)'; ?></span>
                    </div>
                    <div class="progress mb-3">
                        <div class="progress-bar <?php echo $percentage >= 80 ? 'bg-success' : ($percentage >= 50 ? 'bg-warning' : 'bg-danger'); ?>" 
                             role="progressbar" 
                             style="width: <?php echo $percentage; ?>%">
                            <?php echo $percentage; ?>%
                        </div>
                    </div>
                    <small class="text-muted">
                        Completa tutti i campi per aumentare la tua visibilità del <?php echo (100 - $percentage); ?>%
                    </small>
                </div>
            </div>

            <!-- Bio -->
            <?php if (!empty($influencer['bio'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Biografia</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($influencer['bio'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Sponsor Recenti -->
            <?php
            $recent_sponsors = [];
            if ($influencer) {
                try {
                    $stmt = $pdo->prepare("
                        SELECT id, title, image_url, budget, currency, created_at
                        FROM sponsors 
                        WHERE influencer_id = ? 
                        AND status = 'active'
                        AND deleted_at IS NULL
                        ORDER BY created_at DESC 
                        LIMIT 3
                    ");
                    $stmt->execute([$influencer['id']]);
                    $recent_sponsors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    // Silenzioso
                }
            }
            ?>

            <?php if (!empty($recent_sponsors)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Sponsor recenti</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($recent_sponsors as $sponsor): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <?php if (!empty($sponsor['image_url'])): ?>
                                                <img src="/uploads/sponsor/<?php echo htmlspecialchars($sponsor['image_url']); ?>" 
                                                     class="rounded mb-3" 
                                                     alt="<?php echo htmlspecialchars($sponsor['title']); ?>"
                                                     style="width: 100%; height: 120px; object-fit: cover;">
                                            <?php else: ?>
                                                <img src="/uploads/placeholder/sponsor_influencer_dashboard.png" 
                                                     class="rounded mb-3" 
                                                     alt="Placeholder sponsor"
                                                     style="width: 100%; height: 120px; object-fit: cover;">
                                            <?php endif; ?>
                                            
                                            <h6 class="card-title"><?php echo htmlspecialchars($sponsor['title']); ?></h6>
                                            <p class="card-text text-success fw-bold">
                                                €<?php echo number_format($sponsor['budget'], 2); ?>
                                            </p>
                                            <a href="/influencers/sponsors/view.php?id=<?php echo $sponsor['id']; ?>" 
                                               class="btn btn-outline-primary btn-sm">
                                                Visualizza dettagli
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Azioni Rapide -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Azioni Rapide</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="edit-profile.php" class="btn btn-outline-primary w-100 mb-2">
                                ✏️ Modifica Profilo
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="campaigns/list.php" class="btn btn-outline-success w-100 mb-2">
                                🔍 Scopri Campagne
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="applications/list.php" class="btn btn-outline-info w-100 mb-2">
                                📋 Le Mie Candidature
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="settings.php" class="btn btn-outline-secondary w-100 mb-2">
                                ⚙️ Impostazioni
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sezione Candidature -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Le Mie Candidature</h5>
                    <a href="campaigns/list.php" class="btn btn-sm btn-outline-primary">
                        Scopri Nuove Campagne
                    </a>
                </div>
                <div class="card-body">
                    <!-- Statistiche Candidature -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card text-white bg-primary">
                                <div class="card-body text-center py-3">
                                    <h5 class="card-title"><?php echo $application_stats['total_applications']; ?></h5>
                                    <p class="card-text small">Candidature Totali</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-white bg-success">
                                <div class="card-body text-center py-3">
                                    <h5 class="card-title"><?php echo $application_stats['accepted_applications']; ?></h5>
                                    <p class="card-text small">Accettate</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card text-white bg-warning">
                                <div class="card-body text-center py-3">
                                    <h5 class="card-title"><?php echo $application_stats['pending_applications']; ?></h5>
                                    <p class="card-text small">In Attesa</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lista Candidature Recenti -->
                    <?php if (empty($applications)): ?>
                        <div class="text-center py-4">
                            <h6>Nessuna candidatura inviata</h6>
                            <p class="text-muted small">
                                Inizia a candidarti alle campagne pubbliche per trovare collaborazioni
                            </p>
                            <a href="campaigns/list.php" class="btn btn-primary btn-sm">
                                Scopri Campagne
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Campagna</th>
                                        <th>Brand</th>
                                        <th>Budget</th>
                                        <th>Stato</th>
                                        <th>Data</th>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $app): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($app['campaign_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars_decode(htmlspecialchars($app['company_name']), ENT_QUOTES); ?></td>
                                            <td>€<?php echo number_format($app['budget'], 2); ?></td>
                                            <td>
                                                <?php
                                                $status_badges = [
                                                    'pending' => 'warning',
                                                    'accepted' => 'success',
                                                    'rejected' => 'danger'
                                                ];
                                                $badge_class = $status_badges[$app['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($app['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('d/m/Y', strtotime($app['application_date'])); ?></small>
                                            </td>
                                            <td>
                                                <a href="campaigns/view.php?id=<?php echo $app['campaign_id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    Dettagli
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="applications/list.php" class="btn btn-outline-secondary btn-sm">
                                Vedi Tutte le Candidature
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// =============================================
// INCLUSIONE FOOTER CON PERCORSO ASSOLUTO
// =============================================
$footer_file = dirname(__DIR__) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>