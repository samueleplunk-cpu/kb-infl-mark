<?php
// saved-brands.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once dirname(__DIR__) . '/includes/config.php';

// Verifica autenticazione
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'influencer') {
    header("Location: /auth/login.php");
    exit;
}

// Recupera influencer_id
$stmt = $pdo->prepare("SELECT id FROM influencers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$influencer_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$influencer_data) {
    die("Profilo influencer non trovato. Completa il tuo profilo influencer prima di accedere a questa pagina.");
}

$influencer_id = $influencer_data['id'];

// Gestione rimozione preferito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_favorite'])) {
    $brand_id = intval($_POST['brand_id']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM favorite_brands WHERE influencer_id = ? AND brand_id = ?");
        $stmt->execute([$influencer_id, $brand_id]);
        $_SESSION['success_message'] = "Brand rimosso dai preferiti con successo";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Errore durante la rimozione: " . $e->getMessage();
    }
    
    header("Location: saved-brands.php");
    exit;
}

// Recupera brand preferiti
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Query per conteggio totale
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM favorite_brands fb
    JOIN brands b ON fb.brand_id = b.id
    JOIN users u ON b.user_id = u.id
    WHERE fb.influencer_id = ?
");
$count_stmt->execute([$influencer_id]);
$total_results = $count_stmt->fetchColumn();
$total_pages = ceil($total_results / $limit);

// Query per brand preferiti (senza conteggio campagne attive)
$stmt = $pdo->prepare("
    SELECT b.*, u.email, u.created_at as user_created_at, fb.created_at as saved_at
    FROM favorite_brands fb
    JOIN brands b ON fb.brand_id = b.id
    JOIN users u ON b.user_id = u.id
    WHERE fb.influencer_id = ?
    ORDER BY fb.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $influencer_id, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$saved_brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recupera categorie per mappatura
require_once dirname(__DIR__) . '/includes/category_functions.php';
$active_categories = get_active_categories($pdo);

// Crea mappatura per visualizzazione categorie
$category_mapping = [];
foreach ($active_categories as $category) {
    $category_mapping[$category['slug']] = $category['name'];
}

// Includi header
$header_file = dirname(__DIR__) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Brand Preferiti</h2>
    <div>
        <a href="/influencers/settings.php" class="btn btn-outline-secondary">
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
                                <h6 class="card-title">Attivi Recentemente</h6>
                                <h2 class="mb-0">
                                    <?php 
                                    $recent_active = 0;
                                    $one_month_ago = date('Y-m-d', strtotime('-1 month'));
                                    foreach ($saved_brands as $brand) {
                                        if (strtotime($brand['updated_at'] ?? $brand['created_at']) >= strtotime($one_month_ago)) {
                                            $recent_active++;
                                        }
                                    }
                                    echo $recent_active;
                                    ?>
                                </h2>
                            </div>
                            <i class="fas fa-briefcase fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Categorie Diverse</h6>
                                <h2 class="mb-0">
                                    <?php 
                                    $categories = [];
                                    foreach ($saved_brands as $brand) {
                                        if (!empty($brand['industry']) && !in_array($brand['industry'], $categories)) {
                                            $categories[] = $brand['industry'];
                                        }
                                    }
                                    echo count($categories);
                                    ?>
                                </h2>
                            </div>
                            <i class="fas fa-tags fa-3x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista Brand Preferiti -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Lista Brand preferiti
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($saved_brands)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-heart-broken fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Nessun brand salvato</h4>
                        <p class="text-muted">Inizia a salvare i brand che ti interessano durante la ricerca!</p>
                        <a href="/influencers/search-brands.php" class="btn btn-primary mt-2">
                            <i class="fas fa-search me-2"></i>Cerca Brand
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Tabella per dispositivi grandi -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Brand</th>
                                    <th>Categoria</th>
                                    <th>Salvato il</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saved_brands as $brand): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                $logo_path = '';
                                                if (!empty($brand['logo'])) {
                                                    if (strpos($brand['logo'], 'uploads/brands/') !== false) {
                                                        $logo_path = '/' . $brand['logo'];
                                                    } else {
                                                        $logo_path = '/uploads/brands/' . htmlspecialchars(basename($brand['logo']));
                                                    }
                                                } else {
                                                    $logo_path = '/uploads/placeholder/sponsor_influencer_dashboard.png';
                                                }
                                                ?>
                                                <img src="<?php echo $logo_path; ?>" 
                                                     class="rounded-circle me-3" 
                                                     alt="<?php echo htmlspecialchars($brand['company_name']); ?>"
                                                     style="width: 50px; height: 50px; object-fit: contain; background-color: #f8f9fa;">
                                                <div>
                                                    <a href="/brands/profile.php?id=<?php echo $brand['id']; ?>" 
                                                       class="text-decoration-none text-dark fw-bold">
                                                        <?php echo htmlspecialchars($brand['company_name']); ?>
                                                    </a>
                                                    <?php if (!empty($brand['contact_person'])): ?>
                                                        <div>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($brand['contact_person']); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="align-middle">
                                            <?php if (!empty($brand['industry'])): ?>
                                                <?php 
                                                $display_industry = $category_mapping[$brand['industry']] ?? $brand['industry'];
                                                echo htmlspecialchars($display_industry);
                                                ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle">
                                            <?php echo date('d/m/Y', strtotime($brand['saved_at'])); ?>
                                        </td>
                                        <td class="align-middle">
                                            <div class="btn-group" role="group">
                                                <a href="/brands/profile.php?id=<?php echo $brand['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary me-2" title="Vedi profilo">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Rimuovere questo brand dai preferiti?');">
                                                    <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
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
                        <?php foreach ($saved_brands as $brand): ?>
                            <div class="col-12 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                $logo_path = '';
                                                if (!empty($brand['logo'])) {
                                                    if (strpos($brand['logo'], 'uploads/brands/') !== false) {
                                                        $logo_path = '/' . $brand['logo'];
                                                    } else {
                                                        $logo_path = '/uploads/brands/' . htmlspecialchars(basename($brand['logo']));
                                                    }
                                                } else {
                                                    $logo_path = '/uploads/placeholder/sponsor_influencer_dashboard.png';
                                                }
                                                ?>
                                                <img src="<?php echo $logo_path; ?>" 
                                                     class="rounded-circle me-3" 
                                                     alt="<?php echo htmlspecialchars($brand['company_name']); ?>"
                                                     style="width: 60px; height: 60px; object-fit: contain; background-color: #f8f9fa;">
                                                <div>
                                                    <a href="/brands/profile.php?id=<?php echo $brand['id']; ?>" 
                                                       class="text-decoration-none text-dark">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($brand['company_name']); ?></h6>
                                                    </a>
                                                    <?php if (!empty($brand['industry'])): ?>
                                                        <?php 
                                                        $display_industry = $category_mapping[$brand['industry']] ?? $brand['industry'];
                                                        echo htmlspecialchars($display_industry);
                                                        ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <form method="POST" onsubmit="return confirm('Rimuovere dai preferiti?');">
                                                <input type="hidden" name="brand_id" value="<?php echo $brand['id']; ?>">
                                                <button type="submit" name="remove_favorite" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-6 d-flex flex-column align-items-center">
                                                <small class="text-muted d-block mb-1">Salvato il</small>
                                                <div class="text-center">
                                                    <?php echo date('d/m/Y', strtotime($brand['saved_at'])); ?>
                                                </div>
                                            </div>
                                            <div class="col-6 d-flex flex-column align-items-center">
                                                <small class="text-muted d-block mb-1">Azioni</small>
                                                <div class="text-center">
                                                    <a href="/brands/profile.php?id=<?php echo $brand['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary me-2" title="Vedi profilo">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="/brands/profile.php?id=<?php echo $brand['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-external-link-alt"></i> Dettagli
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
$footer_file = dirname(__DIR__) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>