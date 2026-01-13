<?php
// =============================================
// INCLUSIONE CONFIG CON PERCORSO ASSOLUTO CORRETTO
// =============================================
$config_file = dirname(dirname(dirname(__FILE__))) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

// =============================================
// VERIFICA AUTENTICAZIONE
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'influencer') {
    die("Accesso negato: Questa area è riservata agli influencer.");
}

// =============================================
// RECUPERO DATI INFLUENCER
// =============================================
$influencer = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM influencers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $influencer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$influencer) {
        header("Location: ../create-profile.php");
        exit();
    }
} catch (PDOException $e) {
    die("Errore nel caricamento del profilo: " . $e->getMessage());
}

// =============================================
// PARAMETRI FILTRI E PAGINAZIONE
// =============================================
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$applications_per_page = 10;
$offset = ($current_page - 1) * $applications_per_page;

// =============================================
// QUERY CANDIDATURE
// =============================================
$applications = [];
$total_applications = 0;
$total_pages = 0;

try {
    $query = "
        SELECT ca.*, c.name as campaign_name, c.budget, c.niche as campaign_niche,
               b.company_name, b.website as brand_website,
               ca.status, ca.created_at as application_date, ca.updated_at
        FROM campaign_applications ca
        JOIN campaigns c ON ca.campaign_id = c.id
        JOIN brands b ON c.brand_id = b.id
        WHERE ca.influencer_id = ?
        AND c.deleted_at IS NULL
    ";
    
    $count_query = "
        SELECT COUNT(*)
        FROM campaign_applications ca
        JOIN campaigns c ON ca.campaign_id = c.id
        JOIN brands b ON c.brand_id = b.id
        WHERE ca.influencer_id = ?
        AND c.deleted_at IS NULL
    ";
    
    $params = [$influencer['id']];
    $count_params = [$influencer['id']];
    
    // Applica filtri
    if (!empty($status_filter)) {
        $query .= " AND ca.status = ?";
        $count_query .= " AND ca.status = ?";
        $params[] = $status_filter;
        $count_params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $query .= " AND (c.name LIKE ? OR b.company_name LIKE ?)";
        $count_query .= " AND (c.name LIKE ? OR b.company_name LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $count_params[] = $search_term;
        $count_params[] = $search_term;
    }
    
    // Conteggio totale
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($count_params);
    $total_applications = $stmt->fetchColumn();
    $total_pages = ceil($total_applications / $applications_per_page);
    
    // Query con ordinamento e paginazione
    $query .= " ORDER BY ca.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $applications_per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Errore nel caricamento delle candidature: " . $e->getMessage());
}

// =============================================
// STATISTICHE (TUTTE LE CANDIDATURE)
// =============================================
try {
    $stats_stmt = $pdo->prepare("
        SELECT COUNT(*) as total,
               COUNT(CASE WHEN ca.status = 'accepted' THEN 1 END) as accepted,
               COUNT(CASE WHEN ca.status = 'pending' THEN 1 END) as pending,
               COUNT(CASE WHEN ca.status = 'rejected' THEN 1 END) as rejected
        FROM campaign_applications ca
        JOIN campaigns c ON ca.campaign_id = c.id
        WHERE ca.influencer_id = ?
        AND c.deleted_at IS NULL
    ");
    $stats_stmt->execute([$influencer['id']]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total' => 0, 'accepted' => 0, 'pending' => 0, 'rejected' => 0];
}

// =============================================
// INCLUSIONE HEADER
// =============================================
$header_file = dirname(dirname(dirname(__FILE__))) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Le Mie Candidature</h2>
            <div>
                <a href="../dashboard.php" class="btn btn-outline-secondary">
                    ← Dashboard
                </a>
                <a href="../campaigns/list.php" class="btn btn-primary">
                    Scopri Nuove Campagne
                </a>
            </div>
        </div>

        <!-- Filtri -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Cerca</label>
                        <input type="text" name="search" class="form-control" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Cerca per campagna o brand...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Stato</label>
                        <select name="status" class="form-select">
                            <option value="">Tutti gli stati</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>In Attesa</option>
                            <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accettate</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rifiutate</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtra</button>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <a href="list.php" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistiche -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center py-3">
                        <h5 class="card-title"><?php echo $stats['total']; ?></h5>
                        <p class="card-text">Totale candidature</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body text-center py-3">
                        <h5 class="card-title"><?php echo $stats['accepted']; ?></h5>
                        <p class="card-text">Accettate</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body text-center py-3">
                        <h5 class="card-title"><?php echo $stats['pending']; ?></h5>
                        <p class="card-text">In attesa</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger">
                    <div class="card-body text-center py-3">
                        <h5 class="card-title"><?php echo $stats['rejected']; ?></h5>
                        <p class="card-text">Rifiutate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lista Candidature -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Tutte le Candidature</h5>
                <small class="text-muted">
                    <?php echo $total_applications; ?> risultati totali (campagne attive)
                </small>
            </div>
            <div class="card-body">
                <?php if (empty($applications)): ?>
                    <div class="text-center py-5">
                        <h5>Nessuna candidatura trovata</h5>
                        <p class="text-muted">
                            <?php echo $stats['total'] > 0 ? 'Candidati subito ed inizia a collaborare con i nostri Brand' : 'Non hai ancora inviato candidature.'; ?>
                        </p>
                        <?php if ($stats['total'] === 0): ?>
                            <a href="../campaigns/list.php" class="btn btn-primary">
                                Scopri Campagne
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Campagna</th>
                                    <th>Brand</th>
                                    <th>Niche</th>
                                    <th>Budget</th>
                                    <th>Stato</th>
                                    <th>Data Candidatura</th>
                                    <th>Ultimo Aggiornamento</th>
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
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($app['campaign_niche']); ?></span>
                                        </td>
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
                                            <?php echo date('d/m/Y H:i', strtotime($app['application_date'])); ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y H:i', strtotime($app['updated_at'])); ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="../campaigns/view.php?id=<?php echo $app['campaign_id']; ?>" 
                                                   class="btn btn-outline-primary">
                                                    Dettagli
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginazione -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Paginazione candidature">
                            <ul class="pagination justify-content-center mt-4">
                                <!-- Previous Page -->
                                <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>">
                                        ← Precedente
                                    </a>
                                </li>
                                
                                <!-- Page Numbers -->
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                        <a class="page-link" 
                                           href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <!-- Next Page -->
                                <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>">
                                        Successiva →
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
// =============================================
// INCLUSIONE FOOTER
// =============================================
$footer_file = dirname(dirname(dirname(__FILE__))) . '/includes/footer.php';
if (file_exists($footer_file)) {
    require_once $footer_file;
} else {
    echo '<!-- Footer non trovato -->';
}
?>