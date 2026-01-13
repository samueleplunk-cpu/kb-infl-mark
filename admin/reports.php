<?php
require_once '../includes/admin_header.php';

// Determina quale tab mostrare (default: influencer segnalati)
$active_tab = $_GET['tab'] ?? 'users';

// Query per recuperare le segnalazioni UTENTI
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Inizializza variabili per evitare warning
$total_reports = 0;
$total_pages = 0;
$reports = [];

if ($active_tab === 'users') {
    // Costruisci la query per segnalazioni utenti con filtri
    $where_conditions = [];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "(ur.reason LIKE ? OR reporter.email LIKE ? OR reported.email LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($status_filter) && in_array($status_filter, ['pending', 'reviewed', 'resolved'])) {
        $where_conditions[] = "ur.status = ?";
        $params[] = $status_filter;
    }

    // MOSTRA SOLO SEGNALAZIONI DI INFLUENCER (dove il segnalato è un influencer)
    $where_conditions[] = "i.id IS NOT NULL"; // L'utente segnalato è un influencer
    $where_conditions[] = "b_reporter.id IS NOT NULL"; // Il segnalante è un brand

    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Conta totale segnalazioni utenti
    $count_query = "
        SELECT COUNT(*) as total 
        FROM user_reports ur
        JOIN users reporter ON ur.reporter_id = reporter.id
        JOIN users reported ON ur.reported_user_id = reported.id
        LEFT JOIN brands b_reporter ON reporter.id = b_reporter.user_id
        LEFT JOIN brands b_reported ON reported.id = b_reported.user_id
        LEFT JOIN influencers i ON reported.id = i.user_id
        {$where_sql}
    ";

    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_reports = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_reports / $limit);

    // Recupera segnalazioni utenti con paginazione
    $reports_query = "
        SELECT 
            ur.id,
            ur.reason,
            ur.status,
            ur.created_at,
            ur.reviewed_at,
            ur.notes,
            reporter.id as reporter_user_id,
            reporter.email as reporter_email,
            reported.id as reported_user_id,
            reported.email as reported_email,
            b_reporter.company_name as reporter_company,
            b_reported.company_name as reported_company_name,
            i.full_name as reported_influencer_name
        FROM user_reports ur
        JOIN users reporter ON ur.reporter_id = reporter.id
        JOIN users reported ON ur.reported_user_id = reported.id
        LEFT JOIN brands b_reporter ON reporter.id = b_reporter.user_id
        LEFT JOIN brands b_reported ON reported.id = b_reported.user_id
        LEFT JOIN influencers i ON reported.id = i.user_id
        {$where_sql}
        ORDER BY ur.created_at DESC, ur.status ASC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($reports_query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($active_tab === 'brands') {
    // NUOVO TAB: SEGNALAZIONI BRAND
    // Costruisci la query per segnalazioni brand con filtri
    $where_conditions = [];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "(ur.reason LIKE ? OR reporter.email LIKE ? OR reported.email LIKE ? OR b_reported.company_name LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($status_filter) && in_array($status_filter, ['pending', 'reviewed', 'resolved'])) {
        $where_conditions[] = "ur.status = ?";
        $params[] = $status_filter;
    }

    // MOSTRA SOLO SEGNALAZIONI DI BRAND (dove il segnalato è un brand)
    $where_conditions[] = "b_reported.id IS NOT NULL"; // L'utente segnalato è un brand
    $where_conditions[] = "i_reporter.id IS NOT NULL"; // Il segnalante è un influencer

    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Conta totale segnalazioni brand
    $count_query = "
        SELECT COUNT(*) as total 
        FROM user_reports ur
        JOIN users reporter ON ur.reporter_id = reporter.id
        JOIN users reported ON ur.reported_user_id = reported.id
        LEFT JOIN influencers i_reporter ON reporter.id = i_reporter.user_id
        LEFT JOIN brands b_reported ON reported.id = b_reported.user_id
        {$where_sql}
    ";

    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_reports = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_reports / $limit);

    // Recupera segnalazioni brand con paginazione
    $reports_query = "
        SELECT 
            ur.id,
            ur.reason,
            ur.status,
            ur.created_at,
            ur.reviewed_at,
            ur.notes,
            reporter.id as reporter_user_id,
            reporter.email as reporter_email,
            reported.id as reported_user_id,
            reported.email as reported_email,
            i_reporter.full_name as reporter_influencer_name,
            b_reported.company_name as reported_company_name
        FROM user_reports ur
        JOIN users reporter ON ur.reporter_id = reporter.id
        JOIN users reported ON ur.reported_user_id = reported.id
        LEFT JOIN influencers i_reporter ON reporter.id = i_reporter.user_id
        LEFT JOIN brands b_reported ON reported.id = b_reported.user_id
        {$where_sql}
        ORDER BY ur.created_at DESC, ur.status ASC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($reports_query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

} elseif ($active_tab === 'sponsors') {
    // NUOVO TAB: SEGNALAZIONI SPONSOR
    // Costruisci la query per segnalazioni sponsor con filtri
    $where_conditions = [];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "(sr.reason LIKE ? OR s.title LIKE ? OR i.full_name LIKE ? OR b.company_name LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($status_filter) && in_array($status_filter, ['pending', 'reviewed', 'resolved'])) {
        $where_conditions[] = "sr.status = ?";
        $params[] = $status_filter;
    }

    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Conta totale segnalazioni sponsor
    $count_query = "
        SELECT COUNT(*) as total 
        FROM sponsor_reports sr
        JOIN sponsors s ON sr.sponsor_id = s.id
        JOIN influencers i ON s.influencer_id = i.id
        JOIN brands b ON sr.reporter_brand_id = b.id
        JOIN users u ON sr.reporter_user_id = u.id
        {$where_sql}
    ";

    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_reports = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_reports / $limit);

    // Recupera segnalazioni sponsor con paginazione - MODIFICATO per ottenere l'email dell'influencer
    $reports_query = "
        SELECT 
            sr.id,
            sr.reason,
            sr.status,
            sr.created_at,
            sr.reviewed_at,
            sr.notes,
            sr.sponsor_id,
            sr.reporter_brand_id,
            sr.reporter_user_id,
            s.title as sponsor_title,
            s.budget,
            s.category,
            i.full_name as influencer_name,
            i.user_id as influencer_user_id,  -- Aggiunto per ottenere l'user_id dell'influencer
            b.company_name as reporter_company_name,
            u.email as reporter_email
        FROM sponsor_reports sr
        JOIN sponsors s ON sr.sponsor_id = s.id
        JOIN influencers i ON s.influencer_id = i.id
        JOIN brands b ON sr.reporter_brand_id = b.id
        JOIN users u ON sr.reporter_user_id = u.id
        {$where_sql}
        ORDER BY sr.created_at DESC, sr.status ASC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($reports_query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recupera le email degli influencer - Aggiunta query separata per efficienza
    $influencer_emails = [];
    if (!empty($reports)) {
        $influencer_user_ids = array_filter(array_column($reports, 'influencer_user_id'));
        if (!empty($influencer_user_ids)) {
            $placeholders = str_repeat('?,', count($influencer_user_ids) - 1) . '?';
            $email_query = "SELECT id, email FROM users WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($email_query);
            $stmt->execute($influencer_user_ids);
            $email_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($email_results as $row) {
                $influencer_emails[$row['id']] = $row['email'];
            }
        }
    }

} elseif ($active_tab === 'campaigns') {
    // COSTRUISCI QUERY PER SEGNALAZIONI CAMPAGNE (CODICE ORIGINALE)
    $where_conditions = [];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "(cr.reason LIKE ? OR c.name LIKE ? OR b.company_name LIKE ? OR i.full_name LIKE ?)";
        $search_term = "%{$search}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($status_filter) && in_array($status_filter, ['pending', 'reviewed', 'resolved'])) {
        $where_conditions[] = "cr.status = ?";
        $params[] = $status_filter;
    }

    $where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Conta totale segnalazioni campagne
    $count_query = "
        SELECT COUNT(*) as total 
        FROM campaign_reports cr
        JOIN campaigns c ON cr.campaign_id = c.id
        JOIN brands b ON c.brand_id = b.id
        JOIN influencers i ON cr.reporter_influencer_id = i.id
        JOIN users u ON cr.reporter_user_id = u.id
        {$where_sql}
    ";

    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_reports = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_reports / $limit);

    // Recupera segnalazioni campagne con paginazione
    $reports_query = "
        SELECT 
            cr.id,
            cr.reason,
            cr.status,
            cr.created_at,
            cr.reviewed_at,
            cr.notes,
            cr.campaign_id,
            cr.reporter_influencer_id,
            cr.reporter_user_id,
            c.name as campaign_name,
            b.company_name as brand_name,
            u_brand.email as brand_email,
            i.full_name as reporter_influencer_name,
            u.email as reporter_email
        FROM campaign_reports cr
        JOIN campaigns c ON cr.campaign_id = c.id
        JOIN brands b ON c.brand_id = b.id
        JOIN users u_brand ON b.user_id = u_brand.id
        JOIN influencers i ON cr.reporter_influencer_id = i.id
        JOIN users u ON cr.reporter_user_id = u.id
        {$where_sql}
        ORDER BY cr.created_at DESC, cr.status ASC
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($reports_query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Gestione azioni (comuni per entrambe le tab)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? 'user'; // 'user', 'campaign', o 'sponsor'
    $report_id = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
    
    if (isset($_POST['update_status'])) {
        $new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
        $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
        
        if ($report_id && in_array($new_status, ['pending', 'reviewed', 'resolved'])) {
            try {
                // Determina la tabella in base al tipo di segnalazione
                $table_name = 'user_reports';
                if ($report_type === 'campaign') {
                    $table_name = 'campaign_reports';
                } elseif ($report_type === 'sponsor') {
                    $table_name = 'sponsor_reports'; // NUOVA TABELLA
                }
                
                $stmt = $pdo->prepare("
                    UPDATE {$table_name} 
                    SET status = ?, 
                        notes = ?,
                        reviewed_at = CASE 
                            WHEN ? != 'pending' AND reviewed_at IS NULL THEN NOW()
                            ELSE reviewed_at 
                        END
                    WHERE id = ?
                ");
                $stmt->execute([$new_status, $notes, $new_status, $report_id]);
                
                $_SESSION['success_message'] = "Stato segnalazione aggiornato con successo!";
                header("Location: reports.php?tab=" . $active_tab);
                exit();
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Errore durante l'aggiornamento: " . $e->getMessage();
            }
        }
    }
    
    // Gestione eliminazione segnalazione
    if (isset($_POST['delete_report'])) {
        $report_type = $_POST['report_type'] ?? 'user';
        $report_id = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
        
        if ($report_id) {
            try {
                // Determina la tabella in base al tipo di segnalazione
                $table_name = 'user_reports';
                if ($report_type === 'campaign') {
                    $table_name = 'campaign_reports';
                } elseif ($report_type === 'sponsor') {
                    $table_name = 'sponsor_reports'; // NUOVA TABELLA
                }
                
                $stmt = $pdo->prepare("DELETE FROM {$table_name} WHERE id = ?");
                $stmt->execute([$report_id]);
                
                $_SESSION['success_message'] = "Segnalazione eliminata definitivamente!";
                header("Location: reports.php?tab=" . $active_tab);
                exit();
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Errore durante l'eliminazione: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        Segnalazioni
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <?php if ($active_tab === 'users'): ?>
                <span class="badge bg-info fs-6"><?php echo $total_reports; ?> segnalazioni influencer</span>
            <?php elseif ($active_tab === 'brands'): ?>
                <span class="badge bg-danger fs-6"><?php echo $total_reports; ?> segnalazioni brand</span>
            <?php elseif ($active_tab === 'sponsors'): ?>
                <span class="badge bg-warning fs-6"><?php echo $total_reports; ?> segnalazioni sponsor</span>
            <?php else: ?>
                <span class="badge bg-warning fs-6"><?php echo $total_reports; ?> segnalazioni campagne</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Nav Tabs -->
<ul class="nav nav-tabs mb-4" id="reportsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a href="?tab=users" class="nav-link <?php echo $active_tab === 'users' ? 'active' : ''; ?>" 
           role="tab">
            <i class="fas fa-users me-2"></i>Influencer segnalati
            <?php 
            // Conta solo segnalazioni influencer in pending
            $pending_users_stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM user_reports ur
                JOIN users reported ON ur.reported_user_id = reported.id
                JOIN influencers i ON reported.id = i.user_id
                WHERE ur.status = 'pending'
            ");
            $pending_users_stmt->execute();
            $pending_users = $pending_users_stmt->fetchColumn();
            if ($pending_users > 0): ?>
                <span class="badge bg-danger ms-1"><?php echo $pending_users; ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a href="?tab=brands" class="nav-link <?php echo $active_tab === 'brands' ? 'active' : ''; ?>" 
           role="tab">
            <i class="fas fa-building me-2"></i>Brand segnalati
            <?php 
            // Conta solo segnalazioni brand in pending
            $pending_brands_stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM user_reports ur
                JOIN users reported ON ur.reported_user_id = reported.id
                JOIN brands b ON reported.id = b.user_id
                WHERE ur.status = 'pending'
            ");
            $pending_brands_stmt->execute();
            $pending_brands = $pending_brands_stmt->fetchColumn();
            if ($pending_brands > 0): ?>
                <span class="badge bg-danger ms-1"><?php echo $pending_brands; ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a href="?tab=campaigns" class="nav-link <?php echo $active_tab === 'campaigns' ? 'active' : ''; ?>" 
           role="tab">
            <i class="fas fa-flag me-2"></i>Campagne segnalate
            <?php 
            $pending_campaigns = $pdo->query("SELECT COUNT(*) FROM campaign_reports WHERE status = 'pending'")->fetchColumn();
            if ($pending_campaigns > 0): ?>
                <span class="badge bg-warning ms-1"><?php echo $pending_campaigns; ?></span>
            <?php endif; ?>
        </a>
    </li>
    <!-- NUOVO TAB: Sponsor segnalati -->
    <li class="nav-item" role="presentation">
        <a href="?tab=sponsors" class="nav-link <?php echo $active_tab === 'sponsors' ? 'active' : ''; ?>" 
           role="tab">
            <i class="fas fa-star me-2"></i>Sponsor segnalati
            <?php 
            $pending_sponsors = $pdo->query("SELECT COUNT(*) FROM sponsor_reports WHERE status = 'pending'")->fetchColumn();
            if ($pending_sponsors > 0): ?>
                <span class="badge bg-warning ms-1"><?php echo $pending_sponsors; ?></span>
            <?php endif; ?>
        </a>
    </li>
</ul>

<!-- Tab Panes -->
<?php if ($active_tab === 'users'): ?>
    <!-- Tab: Influencer segnalati -->
    <div class="tab-pane show active" id="users-pane" role="tabpanel" tabindex="0">
        
        <!-- Statistiche per segnalazioni utenti -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    In Attesa</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $pending_users_count = $pdo->query("
                                        SELECT COUNT(*) 
                                        FROM user_reports ur
                                        JOIN users reported ON ur.reported_user_id = reported.id
                                        JOIN influencers i ON reported.id = i.user_id
                                        WHERE ur.status = 'pending'
                                    ")->fetchColumn();
                                    echo $pending_users_count;
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Oggi</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $today_users = $pdo->query("
                                        SELECT COUNT(*) 
                                        FROM user_reports ur
                                        JOIN users reported ON ur.reported_user_id = reported.id
                                        JOIN influencers i ON reported.id = i.user_id
                                        WHERE DATE(ur.created_at) = CURDATE()
                                    ")->fetchColumn();
                                    echo $today_users;
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Ultimi 7 giorni</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $last7_users = $pdo->query("
                                        SELECT COUNT(*) 
                                        FROM user_reports ur
                                        JOIN users reported ON ur.reported_user_id = reported.id
                                        JOIN influencers i ON reported.id = i.user_id
                                        WHERE ur.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                    ")->fetchColumn();
                                    echo $last7_users;
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtri -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter me-2"></i>Filtri
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="tab" value="users">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Cerca</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Motivazione o email...">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Stato</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tutti</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>In attesa</option>
                            <option value="reviewed" <?php echo $status_filter == 'reviewed' ? 'selected' : ''; ?>>In lavorazione</option>
                            <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Risolto</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            Applica Filtri
                        </button>
                        <a href="reports.php?tab=users" class="btn btn-outline-secondary ms-2">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabella Segnalazioni -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    Lista segnalazioni influencer
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($reports)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Nessuna segnalazione influencer trovata.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Segnalante (Brand)</th>
                                    <th>Segnalato (Influencer)</th>
                                    <th>Motivazione</th>
                                    <th>Stato</th>
                                    <th>Data Segnalazione</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                    <?php 
                                    // Calcola se il testo è più lungo di 150 caratteri
                                    $is_long_reason = strlen($report['reason']) > 150;
                                    ?>
                                    <tr>
                                        <td>#<?php echo $report['id']; ?></td>
                                        <td>
                                            <div><strong><?php echo htmlspecialchars($report['reporter_company'] ?: 'N/A'); ?></strong></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($report['reporter_email']); ?></small>
                                        </td>
                                        <td>
                                            <div><strong><?php echo htmlspecialchars($report['reported_influencer_name'] ?: 'N/A'); ?></strong></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($report['reported_email']); ?></small>
                                        </td>
                                        <td style="max-width: 300px;">
                                            <div class="report-reason">
                                                <div class="reason-content reason-preview" 
                                                     style="max-height: 4.5em; overflow: hidden;"
                                                     id="reason-<?php echo $report['id']; ?>">
                                                    <?php echo nl2br(htmlspecialchars($report['reason'])); ?>
                                                </div>
                                                <?php if ($is_long_reason): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-link p-0 mt-1 toggle-reason-btn" 
                                                            data-target="reason-<?php echo $report['id']; ?>">
                                                        Mostra di più
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!empty($report['notes'])): ?>
                                                    <hr class="my-1">
                                                    <small class="text-muted">
                                                        <strong>Note admin:</strong> <?php echo htmlspecialchars($report['notes']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'pending' => 'warning',
                                                'reviewed' => 'info',
                                                'resolved' => 'success'
                                            ];
                                            $badge_class = $status_badges[$report['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php 
                                                $status_labels = [
                                                    'pending' => 'In attesa',
                                                    'reviewed' => 'In lavorazione',
                                                    'resolved' => 'Risolto'
                                                ];
                                                echo $status_labels[$report['status']] ?? $report['status']; 
                                                ?>
                                            </span>
                                            <?php if ($report['reviewed_at']): ?>
                                                <div class="small text-muted">
                                                    <?php echo date('d/m/Y - H:i', strtotime($report['reviewed_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y - H:i', strtotime($report['created_at'])); ?>
                                        </td>
                                        <td>
                                            <!-- Bottone Modifica -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal_user_<?php echo $report['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <!-- Bottone Elimina -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal_user_<?php echo $report['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- Modal per modificare lo stato -->
                                    <div class="modal fade" id="editModal_user_<?php echo $report['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="report_type" value="user">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Gestisci Segnalazione #<?php echo $report['id']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Stato</label>
                                                            <select class="form-select" name="status" required>
                                                                <option value="pending" <?php echo $report['status'] == 'pending' ? 'selected' : ''; ?>>In attesa</option>
                                                                <option value="reviewed" <?php echo $report['status'] == 'reviewed' ? 'selected' : ''; ?>>In lavorazione</option>
                                                                <option value="resolved" <?php echo $report['status'] == 'resolved' ? 'selected' : ''; ?>>Risolto</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Note (visibili solo agli admin)</label>
                                                            <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($report['notes'] ?? ''); ?></textarea>
                                                            <div class="form-text">Aggiungi note interne sulla gestione di questa segnalazione.</div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                        <button type="submit" name="update_status" class="btn btn-primary">Salva</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Modal per conferma eliminazione -->
                                    <div class="modal fade" id="deleteModal_user_<?php echo $report['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="report_type" value="user">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title text-danger">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            Eliminare Segnalazione #<?php echo $report['id']; ?>?
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="alert alert-danger">
                                                            <strong>Attenzione:</strong> Questa azione è irreversibile!
                                                        </div>
                                                        <p>Sei sicuro di voler eliminare definitivamente questa segnalazione?</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                        <button type="submit" name="delete_report" class="btn btn-danger">Conferma Eliminazione</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginazione -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Paginazione">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?tab=users&page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ($active_tab === 'brands'): ?>
    <!-- NUOVO TAB: Brand segnalati -->
    <div class="tab-pane show active" id="brands-pane" role="tabpanel" tabindex="0">
        
        <!-- Statistiche per segnalazioni brand -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                    In Attesa</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $pending_brands_count = $pdo->query("
                                        SELECT COUNT(*) 
                                        FROM user_reports ur
                                        JOIN users reported ON ur.reported_user_id = reported.id
                                        JOIN brands b ON reported.id = b.user_id
                                        WHERE ur.status = 'pending'
                                    ")->fetchColumn();
                                    echo $pending_brands_count;
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Oggi</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $today_brands = $pdo->query("
                                        SELECT COUNT(*) 
                                        FROM user_reports ur
                                        JOIN users reported ON ur.reported_user_id = reported.id
                                        JOIN brands b ON reported.id = b.user_id
                                        WHERE DATE(ur.created_at) = CURDATE()
                                    ")->fetchColumn();
                                    echo $today_brands;
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Ultimi 7 giorni</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $last7_brands = $pdo->query("
                                        SELECT COUNT(*) 
                                        FROM user_reports ur
                                        JOIN users reported ON ur.reported_user_id = reported.id
                                        JOIN brands b ON reported.id = b.user_id
                                        WHERE ur.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                    ")->fetchColumn();
                                    echo $last7_brands;
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtri -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter me-2"></i>Filtri
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="tab" value="brands">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Cerca</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Motivazione, email o nome brand...">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Stato</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tutti</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>In attesa</option>
                            <option value="reviewed" <?php echo $status_filter == 'reviewed' ? 'selected' : ''; ?>>In lavorazione</option>
                            <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Risolto</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            Applica Filtri
                        </button>
                        <a href="reports.php?tab=brands" class="btn btn-outline-secondary ms-2">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabella Segnalazioni -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    Lista segnalazioni brand
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($reports)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Nessuna segnalazione brand trovata.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Segnalante (Influencer)</th>
                                    <th>Segnalato (Brand)</th>
                                    <th>Motivazione</th>
                                    <th>Stato</th>
                                    <th>Data Segnalazione</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                    <?php 
                                    // Calcola se il testo è più lungo di 150 caratteri
                                    $is_long_reason = strlen($report['reason']) > 150;
                                    ?>
                                    <tr>
                                        <td>#<?php echo $report['id']; ?></td>
                                        <td>
                                            <div><strong><?php echo htmlspecialchars($report['reporter_influencer_name'] ?: 'N/A'); ?></strong></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($report['reporter_email']); ?></small>
                                        </td>
                                        <td>
                                            <div><strong><?php echo htmlspecialchars($report['reported_company_name'] ?: 'N/A'); ?></strong></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($report['reported_email']); ?></small>
                                        </td>
                                        <td style="max-width: 300px;">
                                            <div class="report-reason">
                                                <div class="reason-content reason-preview" 
                                                     style="max-height: 4.5em; overflow: hidden;"
                                                     id="reason-<?php echo $report['id']; ?>">
                                                    <?php echo nl2br(htmlspecialchars($report['reason'])); ?>
                                                </div>
                                                <?php if ($is_long_reason): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-link p-0 mt-1 toggle-reason-btn" 
                                                            data-target="reason-<?php echo $report['id']; ?>">
                                                        Mostra di più
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!empty($report['notes'])): ?>
                                                    <hr class="my-1">
                                                    <small class="text-muted">
                                                        <strong>Note admin:</strong> <?php echo htmlspecialchars($report['notes']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'pending' => 'warning',
                                                'reviewed' => 'info',
                                                'resolved' => 'success'
                                            ];
                                            $badge_class = $status_badges[$report['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php 
                                                $status_labels = [
                                                    'pending' => 'In attesa',
                                                    'reviewed' => 'In lavorazione',
                                                    'resolved' => 'Risolto'
                                                ];
                                                echo $status_labels[$report['status']] ?? $report['status']; 
                                                ?>
                                            </span>
                                            <?php if ($report['reviewed_at']): ?>
                                                <div class="small text-muted">
                                                    <?php echo date('d/m/Y - H:i', strtotime($report['reviewed_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y - H:i', strtotime($report['created_at'])); ?>
                                        </td>
                                        <td>
                                            <!-- Bottone Modifica -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal_user_<?php echo $report['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <!-- Bottone Elimina -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal_user_<?php echo $report['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- Modal per modificare lo stato -->
                                    <div class="modal fade" id="editModal_user_<?php echo $report['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="report_type" value="user">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Gestisci Segnalazione #<?php echo $report['id']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Stato</label>
                                                            <select class="form-select" name="status" required>
                                                                <option value="pending" <?php echo $report['status'] == 'pending' ? 'selected' : ''; ?>>In attesa</option>
                                                                <option value="reviewed" <?php echo $report['status'] == 'reviewed' ? 'selected' : ''; ?>>In lavorazione</option>
                                                                <option value="resolved" <?php echo $report['status'] == 'resolved' ? 'selected' : ''; ?>>Risolto</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Note (visibili solo agli admin)</label>
                                                            <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($report['notes'] ?? ''); ?></textarea>
                                                            <div class="form-text">Aggiungi note interne sulla gestione di questa segnalazione.</div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                        <button type="submit" name="update_status" class="btn btn-primary">Salva</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Modal per conferma eliminazione -->
                                    <div class="modal fade" id="deleteModal_user_<?php echo $report['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="report_type" value="user">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title text-danger">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            Eliminare Segnalazione #<?php echo $report['id']; ?>?
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="alert alert-danger">
                                                            <strong>Attenzione:</strong> Questa azione è irreversibile!
                                                        </div>
                                                        <p>Sei sicuro di voler eliminare definitivamente questa segnalazione?</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                        <button type="submit" name="delete_report" class="btn btn-danger">Conferma Eliminazione</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginazione -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Paginazione">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?tab=brands&page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ($active_tab === 'sponsors'): ?>
    <!-- NUOVO TAB: Sponsor segnalati -->
    <div class="tab-pane show active" id="sponsors-pane" role="tabpanel" tabindex="0">
        
        <!-- Statistiche per segnalazioni sponsor -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    In Attesa</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $pending_sponsors_count = $pdo->query("SELECT COUNT(*) FROM sponsor_reports WHERE status = 'pending'")->fetchColumn();
                                    echo $pending_sponsors_count;
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Oggi</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $today_sponsors = $pdo->query("SELECT COUNT(*) FROM sponsor_reports WHERE DATE(created_at) = CURDATE()")->fetchColumn();
                                    echo $today_sponsors;
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Ultimi 7 giorni</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $last7_sponsors = $pdo->query("SELECT COUNT(*) FROM sponsor_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
                                    echo $last7_sponsors;
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtri -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter me-2"></i>Filtri
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="tab" value="sponsors">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Cerca</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Motivazione, titolo sponsor o nome influencer...">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Stato</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tutti</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>In attesa</option>
                            <option value="reviewed" <?php echo $status_filter == 'reviewed' ? 'selected' : ''; ?>>In lavorazione</option>
                            <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Risolto</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            Applica Filtri
                        </button>
                        <a href="reports.php?tab=sponsors" class="btn btn-outline-secondary ms-2">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabella Segnalazioni -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    Lista segnalazioni sponsor
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($reports)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Nessuna segnalazione sponsor trovata.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Sponsor</th>
                                    <th>Influencer</th>
                                    <th>Segnalante (Brand)</th>
                                    <th>Motivazione</th>
                                    <th>Stato</th>
                                    <th>Data Segnalazione</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                    <?php 
                                    // Calcola se il testo è più lungo di 150 caratteri
                                    $is_long_reason = strlen($report['reason']) > 150;
                                    
                                    // Ottieni l'email dell'influencer
                                    $influencer_email = $influencer_emails[$report['influencer_user_id']] ?? 'N/A';
                                    ?>
                                    <tr>
                                        <td>#<?php echo $report['id']; ?></td>
                                        <td>
                                            <!-- MODIFICATO: Rimossi Budget e Categoria -->
                                            <div><strong><?php echo htmlspecialchars($report['sponsor_title']); ?></strong></div>
                                        </td>
                                        <td>
                                            <!-- MODIFICATO: Aggiunta email dell'influencer -->
                                            <div><strong><?php echo htmlspecialchars($report['influencer_name']); ?></strong></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($influencer_email); ?></small>
                                        </td>
                                        <td>
                                            <div><strong><?php echo htmlspecialchars($report['reporter_company_name'] ?: 'N/A'); ?></strong></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($report['reporter_email']); ?></small>
                                        </td>
                                        <td style="max-width: 300px;">
                                            <div class="report-reason">
                                                <div class="reason-content reason-preview" 
                                                     style="max-height: 4.5em; overflow: hidden;"
                                                     id="reason-<?php echo $report['id']; ?>">
                                                    <?php echo nl2br(htmlspecialchars($report['reason'])); ?>
                                                </div>
                                                <?php if ($is_long_reason): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-link p-0 mt-1 toggle-reason-btn" 
                                                            data-target="reason-<?php echo $report['id']; ?>">
                                                        Mostra di più
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!empty($report['notes'])): ?>
                                                    <hr class="my-1">
                                                    <small class="text-muted">
                                                        <strong>Note admin:</strong> <?php echo htmlspecialchars($report['notes']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'pending' => 'warning',
                                                'reviewed' => 'info',
                                                'resolved' => 'success'
                                            ];
                                            $badge_class = $status_badges[$report['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php 
                                                $status_labels = [
                                                    'pending' => 'In attesa',
                                                    'reviewed' => 'In lavorazione',
                                                    'resolved' => 'Risolto'
                                                ];
                                                echo $status_labels[$report['status']] ?? $report['status']; 
                                                ?>
                                            </span>
                                            <?php if ($report['reviewed_at']): ?>
                                                <div class="small text-muted">
                                                    <?php echo date('d/m/Y - H:i', strtotime($report['reviewed_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y - H:i', strtotime($report['created_at'])); ?>
                                        </td>
                                        <td>
                                            <!-- Bottone Modifica -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal_sponsor_<?php echo $report['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <!-- Bottone Elimina -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal_sponsor_<?php echo $report['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- Modal per modificare lo stato -->
                                    <div class="modal fade" id="editModal_sponsor_<?php echo $report['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="report_type" value="sponsor">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Gestisci Segnalazione #<?php echo $report['id']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Stato</label>
                                                            <select class="form-select" name="status" required>
                                                                <option value="pending" <?php echo $report['status'] == 'pending' ? 'selected' : ''; ?>>In attesa</option>
                                                                <option value="reviewed" <?php echo $report['status'] == 'reviewed' ? 'selected' : ''; ?>>In lavorazione</option>
                                                                <option value="resolved" <?php echo $report['status'] == 'resolved' ? 'selected' : ''; ?>>Risolto</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Note (visibili solo agli admin)</label>
                                                            <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($report['notes'] ?? ''); ?></textarea>
                                                            <div class="form-text">Aggiungi note interne sulla gestione di questa segnalazione.</div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                        <button type="submit" name="update_status" class="btn btn-primary">Salva</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Modal per conferma eliminazione -->
                                    <div class="modal fade" id="deleteModal_sponsor_<?php echo $report['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="report_type" value="sponsor">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title text-danger">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            Eliminare Segnalazione #<?php echo $report['id']; ?>?
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="alert alert-danger">
                                                            <strong>Attenzione:</strong> Questa azione è irreversibile!
                                                        </div>
                                                        <p>Sei sicuro di voler eliminare definitivamente questa segnalazione?</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                        <button type="submit" name="delete_report" class="btn btn-danger">Conferma Eliminazione</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginazione -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Paginazione">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?tab=sponsors&page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ($active_tab === 'campaigns'): ?>
    <!-- Tab: Campagne segnalate -->
    <div class="tab-pane show active" id="campaigns-pane" role="tabpanel" tabindex="0">
        
        <!-- Statistiche per segnalazioni campagne -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    In Attesa</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $pdo->query("SELECT COUNT(*) FROM campaign_reports WHERE status = 'pending'")->fetchColumn(); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Oggi</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $pdo->query("SELECT COUNT(*) FROM campaign_reports WHERE DATE(created_at) = CURDATE()")->fetchColumn(); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Ultimi 7 giorni</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $pdo->query("SELECT COUNT(*) FROM campaign_reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtri -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-filter me-2"></i>Filtri
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="tab" value="campaigns">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Cerca</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Motivazione, nome campagna o brand...">
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Stato</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tutti</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>In attesa</option>
                            <option value="reviewed" <?php echo $status_filter == 'reviewed' ? 'selected' : ''; ?>>In lavorazione</option>
                            <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Risolto</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            Applica Filtri
                        </button>
                        <a href="reports.php?tab=campaigns" class="btn btn-outline-secondary ms-2">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabella Segnalazioni -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    Lista segnalazioni campagne
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($reports)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Nessuna segnalazione trovata.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Campagna</th>
                                    <th>Brand</th>
                                    <th>Segnalante (Influencer)</th>
                                    <th>Motivazione</th>
                                    <th>Stato</th>
                                    <th>Data Segnalazione</th>
                                    <th>Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                    <?php 
                                    // Calcola se il testo è più lungo di 150 caratteri
                                    $is_long_reason = strlen($report['reason']) > 150;
                                    ?>
                                    <tr>
                                        <!-- CAMPAIGN REPORTS -->
                                        <td>#<?php echo $report['id']; ?></td>
                                        <td>
                                            <div><strong><?php echo htmlspecialchars($report['campaign_name']); ?></strong></div>
                                        </td>
                                        <td>
                                            <div><strong><?php echo htmlspecialchars($report['brand_name']); ?></strong></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($report['brand_email']); ?></small>
                                        </td>
                                        <td>
                                            <div><strong><?php echo htmlspecialchars($report['reporter_influencer_name']); ?></strong></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($report['reporter_email']); ?></small>
                                        </td>
                                        <td style="max-width: 300px;">
                                            <div class="report-reason">
                                                <div class="reason-content reason-preview" 
                                                     style="max-height: 4.5em; overflow: hidden;"
                                                     id="reason-<?php echo $report['id']; ?>">
                                                    <?php echo nl2br(htmlspecialchars($report['reason'])); ?>
                                                </div>
                                                <?php if ($is_long_reason): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-link p-0 mt-1 toggle-reason-btn" 
                                                            data-target="reason-<?php echo $report['id']; ?>">
                                                        Mostra di più
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!empty($report['notes'])): ?>
                                                    <hr class="my-1">
                                                    <small class="text-muted">
                                                        <strong>Note admin:</strong> <?php echo htmlspecialchars($report['notes']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'pending' => 'warning',
                                                'reviewed' => 'info',
                                                'resolved' => 'success'
                                            ];
                                            $badge_class = $status_badges[$report['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge_class; ?>">
                                                <?php 
                                                $status_labels = [
                                                    'pending' => 'In attesa',
                                                    'reviewed' => 'In lavorazione',
                                                    'resolved' => 'Risolto'
                                                ];
                                                echo $status_labels[$report['status']] ?? $report['status']; 
                                                ?>
                                            </span>
                                            <?php if ($report['reviewed_at']): ?>
                                                <div class="small text-muted">
                                                    <?php echo date('d/m/Y - H:i', strtotime($report['reviewed_at'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y - H:i', strtotime($report['created_at'])); ?>
                                        </td>
                                        <td>
                                            <!-- Bottone Modifica -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal_campaign_<?php echo $report['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <!-- Bottone Elimina -->
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal_campaign_<?php echo $report['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- Modal per modificare lo stato -->
                                    <div class="modal fade" id="editModal_campaign_<?php echo $report['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="report_type" value="campaign">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Gestisci Segnalazione #<?php echo $report['id']; ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Stato</label>
                                                            <select class="form-select" name="status" required>
                                                                <option value="pending" <?php echo $report['status'] == 'pending' ? 'selected' : ''; ?>>In attesa</option>
                                                                <option value="reviewed" <?php echo $report['status'] == 'reviewed' ? 'selected' : ''; ?>>In lavorazione</option>
                                                                <option value="resolved" <?php echo $report['status'] == 'resolved' ? 'selected' : ''; ?>>Risolto</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Note (visibili solo agli admin)</label>
                                                            <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($report['notes'] ?? ''); ?></textarea>
                                                            <div class="form-text">Aggiungi note interne sulla gestione di questa segnalazione.</div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                        <button type="submit" name="update_status" class="btn btn-primary">Salva</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Modal per conferma eliminazione -->
                                    <div class="modal fade" id="deleteModal_campaign_<?php echo $report['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <input type="hidden" name="report_type" value="campaign">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title text-danger">
                                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                                            Eliminare Segnalazione #<?php echo $report['id']; ?>?
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="alert alert-danger">
                                                            <strong>Attenzione:</strong> Questa azione è irreversibile!
                                                        </div>
                                                        <p>Sei sicuro di voler eliminare definitivamente questa segnalazione?</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                                        <button type="submit" name="delete_report" class="btn btn-danger">Conferma Eliminazione</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginazione -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Paginazione">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?tab=campaigns&page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- JavaScript per toggle motivo -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Trovare tutti i pulsanti "Mostra di più"
    const toggleButtons = document.querySelectorAll('.toggle-reason-btn');
    
    // Aggiungere evento click a ciascun pulsante
    toggleButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const content = document.getElementById(targetId);
            
            if (content) {
                if (content.style.maxHeight && content.style.maxHeight !== 'none') {
                    // Espandi
                    content.style.maxHeight = 'none';
                    content.style.overflow = 'visible';
                    this.textContent = 'Mostra meno';
                } else {
                    // Riduci
                    content.style.maxHeight = '4.5em';
                    content.style.overflow = 'hidden';
                    this.textContent = 'Mostra di più';
                }
            }
        });
    });
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>