<?php
// saved-influencers.php
session_start();
require_once dirname(__DIR__) . '/includes/config.php';

// Verifica autenticazione
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    header("Location: /auth/login.php");
    exit;
}

// Recupera brand_id
$stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$brand_data) {
    die("Profilo brand non trovato. Completa il tuo profilo brand prima di accedere a questa pagina.");
}

$brand_id = $brand_data['id'];

// Gestione rimozione preferito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_favorite'])) {
    $influencer_id = intval($_POST['influencer_id']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM favorite_influencers WHERE brand_id = ? AND influencer_id = ?");
        $stmt->execute([$brand_id, $influencer_id]);
        $_SESSION['success_message'] = "Influencer rimosso dai preferiti con successo";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Errore durante la rimozione: " . $e->getMessage();
    }
    
    header("Location: saved-influencers.php");
    exit;
}

// Recupera influencer preferiti
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Query per conteggio totale
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM favorite_influencers fi
    JOIN influencers i ON fi.influencer_id = i.id
    WHERE fi.brand_id = ?
");
$count_stmt->execute([$brand_id]);
$total_results = $count_stmt->fetchColumn();
$total_pages = ceil($total_results / $limit);

// Query per influencer preferiti
$stmt = $pdo->prepare("
    SELECT i.*, fi.created_at as saved_at 
    FROM favorite_influencers fi
    JOIN influencers i ON fi.influencer_id = i.id
    WHERE fi.brand_id = ?
    ORDER BY fi.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $brand_id, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$saved_influencers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Includi header
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Influencer Preferiti</h2>
            <div>
                <a href="/brands/search-influencers.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-search"></i> Cerca Influencer
                </a>
                <a href="/brands/settings.php" class="btn btn-outline-secondary">
                    <i class="fas fa-cog"></i> Impostazioni
                </a>
            </div>
        </div>

        <!-- Messaggi di successo/errore -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistiche -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Totali Salvati</h6>
                                <h2 class="mb-0"><?php echo $total_results; ?></h2>
                            </div>
                            <i class="fas fa-heart fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Contattabili</h6>
                                <h2 class="mb-0">
                                    <?php 
                                    $contactable = 0;
                                    foreach ($saved_influencers as $inf) {
                                        if (!empty($inf['rate'])) $contactable++;
                                    }
                                    echo $contactable;
                                    ?>
                                </h2>
                            </div>
                            <i class="fas fa-envelope fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Media Rating</h6>
                                <h2 class="mb-0">
                                    <?php 
                                    $total_rating = 0;
                                    $count_with_rating = 0;
                                    foreach ($saved_influencers as $inf) {
                                        if (!empty($inf['rating']) && $inf['rating'] > 0) {
                                            $total_rating += $inf['rating'];
                                            $count_with_rating++;
                                        }
                                    }
                                    echo $count_with_rating > 0 ? number_format($total_rating / $count_with_rating, 1) : 'N/A';
                                    ?>
                                </h2>
                            </div>
                            <i class="fas fa-star fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista Influencer Preferiti -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Lista Influencer preferiti
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($saved_influencers)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-heart-broken fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Nessun influencer salvato</h4>
                        <p class="text-muted">Inizia a salvare gli influencer che ti interessano durante la ricerca!</p>
                        <a href="/brands/search-influencers.php" class="btn btn-primary mt-2">
                            <i class="fas fa-search me-2"></i>Cerca Influencer
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Tabella per dispositivi grandi -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Influencer</th>
                                    <th>Categoria</th>
                                    <th>Piattaforme</th>
                                    <th>Tariffa</th>
                                    <th>Rating</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saved_influencers as $influencer): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                $profile_image = !empty($influencer['profile_image']) 
                                                    ? '/uploads/' . htmlspecialchars($influencer['profile_image'])
                                                    : '/uploads/placeholder/sponsor_influencer_dashboard.png';
                                                ?>
                                                <img src="<?php echo $profile_image; ?>" 
                                                     class="rounded-circle me-3" 
                                                     alt="<?php echo htmlspecialchars($influencer['full_name']); ?>"
                                                     style="width: 50px; height: 50px; object-fit: cover;">
                                                <div>
                                                    <a href="/influencers/profile.php?id=<?php echo $influencer['id']; ?>" 
                                                       class="text-decoration-none text-dark fw-bold">
                                                        <?php echo htmlspecialchars($influencer['full_name']); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="align-middle">
                                            <?php if (!empty($influencer['niche'])): ?>
                                                <?php echo ucfirst(htmlspecialchars($influencer['niche'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle">
                                            <?php
                                            $platforms = [];
                                            if (!empty($influencer['instagram_handle'])) $platforms[] = '<i class="fab fa-instagram text-danger me-1"></i>';
                                            if (!empty($influencer['tiktok_handle'])) $platforms[] = '<i class="fab fa-tiktok me-1"></i>';
                                            if (!empty($influencer['youtube_handle'])) $platforms[] = '<i class="fab fa-youtube text-danger me-1"></i>';
                                            echo implode(' ', $platforms);
                                            ?>
                                        </td>
                                        <td class="align-middle">
                                            <?php if (!empty($influencer['rate'])): ?>
                                                €<?php echo number_format($influencer['rate'], 2); ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/D</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle">
                                            <?php if (!empty($influencer['rating'])): ?>
                                                <span class="badge bg-warning text-dark">
                                                    ★ <?php echo number_format($influencer['rating'], 1); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle">
                                            <div class="btn-group" role="group">
                                                <a href="/influencers/profile.php?id=<?php echo $influencer['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary me-2" title="Vedi profilo">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Rimuovere questo influencer dai preferiti?');">
                                                    <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                    <button type="submit" name="remove_favorite" class="btn btn-sm btn-outline-danger" title="Rimuovi">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Griglia per dispositivi mobili -->
                    <div class="row d-md-none">
                        <?php foreach ($saved_influencers as $influencer): ?>
                            <div class="col-12 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                $profile_image = !empty($influencer['profile_image']) 
                                                    ? '/uploads/' . htmlspecialchars($influencer['profile_image'])
                                                    : '/uploads/placeholder/sponsor_influencer_dashboard.png';
                                                ?>
                                                <img src="<?php echo $profile_image; ?>" 
                                                     class="rounded-circle me-3" 
                                                     alt="<?php echo htmlspecialchars($influencer['full_name']); ?>"
                                                     style="width: 60px; height: 60px; object-fit: cover;">
                                                <div>
                                                    <a href="/influencers/profile.php?id=<?php echo $influencer['id']; ?>" 
                                                       class="text-decoration-none text-dark">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($influencer['full_name']); ?></h6>
                                                    </a>
                                                    <?php if (!empty($influencer['niche'])): ?>
                                                        <?php echo ucfirst(htmlspecialchars($influencer['niche'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <form method="POST" onsubmit="return confirm('Rimuovere dai preferiti?');">
                                                <input type="hidden" name="influencer_id" value="<?php echo $influencer['id']; ?>">
                                                <button type="submit" name="remove_favorite" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-3 d-flex flex-column align-items-center">
                                                <small class="text-muted d-block mb-1">Piattaforme</small>
                                                <div class="text-center">
                                                    <?php
                                                    if (!empty($influencer['instagram_handle'])) echo '<i class="fab fa-instagram text-danger me-1"></i>';
                                                    if (!empty($influencer['tiktok_handle'])) echo '<i class="fab fa-tiktok me-1"></i>';
                                                    if (!empty($influencer['youtube_handle'])) echo '<i class="fab fa-youtube text-danger me-1"></i>';
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="col-3 d-flex flex-column align-items-center">
                                                <small class="text-muted d-block mb-1">Tariffa</small>
                                                <?php if (!empty($influencer['rate'])): ?>
                                                    <div class="text-center">€<?php echo number_format($influencer['rate'], 2); ?></div>
                                                <?php else: ?>
                                                    <div class="text-center text-muted">N/D</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-3 d-flex flex-column align-items-center">
                                                <small class="text-muted d-block mb-1">Rating</small>
                                                <?php if (!empty($influencer['rating'])): ?>
                                                    <div class="text-center">
                                                        <span class="badge bg-warning text-dark">★ <?php echo number_format($influencer['rating'], 1); ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center text-muted">N/A</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-3 d-flex flex-column align-items-center">
                                                <small class="text-muted d-block mb-1">Azioni</small>
                                                <div class="text-center">
                                                    <a href="/influencers/profile.php?id=<?php echo $influencer['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Vedi profilo">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Paginazione -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Paginazione preferiti">
                            <ul class="pagination justify-content-center mt-4">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                        <i class="fas fa-chevron-left"></i> Precedente
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                        Successiva <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Includi footer
require_once dirname(__DIR__) . '/includes/footer.php';
?>