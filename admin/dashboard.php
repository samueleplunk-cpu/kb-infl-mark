<?php
require_once '../includes/admin_header.php';
// require_admin_login(); // Già incluso in admin_header.php

// Includi functions.php per avere accesso a cleanup_soft_deleted_users()
require_once '../includes/functions.php';

// Includi funzioni notifica
require_once '../includes/notification_functions.php';

// Usa la funzione corretta da admin_functions.php
$stats = get_admin_platform_stats();

// Esegui pulizia automatica soft delete
cleanup_soft_deleted_users();
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <span class="text-muted"><?php echo date('d/m/Y H:i'); ?></span>
    </div>
</div>

<!-- Statistiche -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Utenti Totali</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $stats['total_users']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Influencer</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $stats['total_influencers']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Brands</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $stats['total_brands']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-building fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Nuovi Oggi</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $stats['new_today']; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Azioni Rapide -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Azioni Rapide</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="influencers.php?action=add" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Nuovo Influencer
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="brands.php?action=add" class="btn btn-success w-100">
                            <i class="fas fa-plus me-2"></i>Nuovo Brand
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="influencers.php" class="btn btn-info w-100">
                            <i class="fas fa-list me-2"></i>Gestisci Influencer
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="settings.php" class="btn btn-warning w-100">
                            <i class="fas fa-cog me-2"></i>Impostazioni
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>