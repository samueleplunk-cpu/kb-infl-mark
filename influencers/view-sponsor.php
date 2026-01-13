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
// VERIFICA PARAMETRO ID
// =============================================
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: sponsors.php");
    exit();
}

$sponsor_id = intval($_GET['id']);

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
// RECUPERO DETTAGLI SPONSOR CON VERIFICA PROPRIETÀ
// =============================================
$sponsor = null;

try {
    $stmt = $pdo->prepare("
        SELECT 
            s.*
        FROM sponsors s
        WHERE s.id = ? AND s.influencer_id = ? AND s.deleted_at IS NULL
    ");
    $stmt->execute([$sponsor_id, $influencer['id']]);
    $sponsor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sponsor) {
        $error = "Sponsor non trovato o accesso negato.";
    }
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento dei dettagli sponsor: " . $e->getMessage();
}

// =============================================
// RECUPERO NOME INFLUENCER DALLA TABELLA USERS
// =============================================
$influencer_name = '';
$influencer_username = '';

if ($sponsor) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.username, u.first_name, u.last_name 
            FROM users u 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $influencer_username = $user['username'];
            // Crea il nome completo se disponibile, altrimenti usa lo username
            if (!empty($user['first_name']) && !empty($user['last_name'])) {
                $influencer_name = $user['first_name'] . ' ' . $user['last_name'];
            } else {
                $influencer_name = $user['username'];
            }
        }
        
    } catch (PDOException $e) {
        // Non blocchiamo l'intera pagina se fallisce il recupero del nome
        $influencer_name = 'Influencer';
        $influencer_username = 'user';
    }
}

// =============================================
// MAPPA CATEGORIE UNIFICATE
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
// MAPPA STATI
// =============================================
$status_labels = [
    'draft' => 'Bozza',
    'active' => 'Attivo',
    'completed' => 'Completato',
    'cancelled' => 'Cancellato'
];

$status_badges = [
    'draft' => 'warning',
    'active' => 'success',
    'completed' => 'info',
    'cancelled' => 'danger'
];

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
        <!-- Pulsante Torna Indietro -->
        <div class="mb-4">
            <a href="sponsors.php" class="btn btn-outline-secondary">
                ← Torna alla Lista Sponsor
            </a>
        </div>

        <!-- Messaggi di errore -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($sponsor): ?>
            <!-- Header Dettaglio Sponsor -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Dettagli Sponsor</h4>
                        <span class="badge bg-light text-dark fs-6">
                            ID: <?php echo htmlspecialchars($sponsor['id']); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h2 class="card-title"><?php echo htmlspecialchars($sponsor['title']); ?></h2>
                            <p class="text-muted mb-3">
                                Creato il <?php echo date('d/m/Y H:i', strtotime($sponsor['created_at'])); ?>
                                <?php if ($sponsor['updated_at'] && $sponsor['updated_at'] != $sponsor['created_at']): ?>
                                    • Aggiornato il <?php echo date('d/m/Y H:i', strtotime($sponsor['updated_at'])); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php
                            $badge_class = $status_badges[$sponsor['status']] ?? 'secondary';
                            $status_label = $status_labels[$sponsor['status']] ?? ucfirst($sponsor['status']);
                            ?>
                            <span class="badge bg-<?php echo $badge_class; ?> fs-6 p-2">
                                <?php echo htmlspecialchars($status_label); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Colonna Sinistra: Immagine e Dettagli Principali -->
                <div class="col-md-4">
                    <!-- Card Immagine -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Immagine Sponsor</h5>
                        </div>
                        <div class="card-body text-center">
                            <?php 
                            // Definisci il percorso del placeholder
                            $placeholder_path = '/uploads/placeholder/sponsor_influencer_dashboard.png';
                            
                            if (!empty($sponsor['image_url'])) {
                                // Se esiste un'immagine caricata dall'influencer, mostra quella
                                $image_path = '/uploads/sponsor/' . htmlspecialchars($sponsor['image_url']);
                                ?>
                                <img src="<?php echo $image_path; ?>" 
                                     alt="<?php echo htmlspecialchars($sponsor['title']); ?>" 
                                     class="img-fluid rounded" 
                                     style="max-height: 300px; object-fit: cover;"
                                     onerror="this.onerror=null; this.src='<?php echo $placeholder_path; ?>';">
                            <?php } else { 
                                // Se NON esiste un'immagine caricata, mostra il placeholder
                                ?>
                                <img src="<?php echo $placeholder_path; ?>" 
                                     alt="Placeholder - <?php echo htmlspecialchars($sponsor['title']); ?>" 
                                     class="img-fluid rounded" 
                                     style="max-height: 300px; object-fit: cover;"
                                     title="Immagine di copertina non caricata">
                            <?php } ?>
                        </div>
                    </div>

                    <!-- Card Dettagli Rapidi -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Dettagli</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Budget:</strong>
                                <div class="fs-4 text-success fw-bold">
                                    €<?php echo number_format($sponsor['budget'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Categoria:</strong>
                                <div>
                                    <?php 
                                    $display_category = $sponsor['category'];
                                    if (isset($category_mapping[$sponsor['category']])) {
                                        $display_category = $category_mapping[$sponsor['category']];
                                    }
                                    ?>
                                    <span class="badge bg-info fs-6"><?php echo htmlspecialchars($display_category); ?></span>
                                </div>
                            </div>

                            <?php if (!empty($sponsor['platforms'])): ?>
                                <div class="mb-3">
                                    <strong>Piattaforme:</strong>
                                    <div>
                                        <?php
                                        $platforms = json_decode($sponsor['platforms'], true) ?: [];
                                        foreach ($platforms as $platform): 
                                        ?>
                                            <span class="badge bg-secondary me-1 mb-1"><?php echo htmlspecialchars($platform); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($sponsor['target_audience'])): ?>
                                <div class="mb-3">
                                    <strong>Target Audience:</strong>
                                    <div class="text-muted">
                                        <?php echo htmlspecialchars($sponsor['target_audience']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card Azioni -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Azioni</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="edit-sponsor.php?id=<?php echo $sponsor['id']; ?>" 
                                   class="btn btn-warning">
                                    ✏️ Modifica Sponsor
                                </a>
                                <a href="sponsors.php" 
                                   class="btn btn-outline-secondary">
                                    ← Torna alla Lista
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Colonna Destra: Descrizione e Dettagli Estesi -->
                <div class="col-md-8">
                    <!-- Card Descrizione -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Descrizione</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($sponsor['description'])): ?>
                                <div class="description-content">
                                    <?php echo nl2br(htmlspecialchars($sponsor['description'])); ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted fst-italic">Nessuna descrizione fornita.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card Requisiti -->
                    <?php if (!empty($sponsor['requirements'])): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Requisiti del Brand</h5>
                            </div>
                            <div class="card-body">
                                <div class="requirements-content">
                                    <?php echo nl2br(htmlspecialchars($sponsor['requirements'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Card Informazioni Aggiuntive -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Informazioni Aggiuntive</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <strong>Influencer:</strong>
                                        <div class="text-muted">
                                            <?php echo htmlspecialchars($influencer_name); ?>
                                            <?php if (!empty($influencer_username)): ?>
                                                (@<?php echo htmlspecialchars($influencer_username); ?>)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <strong>Stato:</strong>
                                        <div>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($status_label); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <strong>Data Creazione:</strong>
                                        <div class="text-muted">
                                            <?php echo date('d/m/Y H:i', strtotime($sponsor['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <?php if ($sponsor['updated_at'] && $sponsor['updated_at'] != $sponsor['created_at']): ?>
                                        <div class="mb-3">
                                            <strong>Ultimo Aggiornamento:</strong>
                                            <div class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($sponsor['updated_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Messaggio sponsor non trovato -->
            <div class="text-center py-5">
                <div class="card">
                    <div class="card-body py-5">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h3>Sponsor non trovato</h3>
                        <p class="text-muted mb-4">
                            Lo sponsor che stai cercando non esiste o non hai i permessi per visualizzarlo.
                        </p>
                        <a href="sponsors.php" class="btn btn-primary">
                            ← Torna ai Miei Sponsor
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

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