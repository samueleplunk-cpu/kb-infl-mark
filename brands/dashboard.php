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
    // Reindirizza al login se non autenticato
    header("Location: /auth/login.php");
    exit();
}

// Verifica che l'utente sia un brand
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    die("Accesso negato: Questa area è riservata ai brand.");
}

// =============================================
// INCLUSIONE HEADER CON PERCORSO ASSOLUTO
// =============================================
$header_file = dirname(__DIR__) . '/includes/header.php';
if (!file_exists($header_file)) {
    die("Errore: File header non trovato in: " . $header_file);
}
require_once $header_file;

// =============================================
// RECUPERO DATI BRAND DAL DATABASE
// =============================================
$brand = null;
$error = '';
$success = '';

try {
    // Prepara e esegui query per recuperare i dati del brand (INCLUSO IL LOGO)
    $stmt = $pdo->prepare("
        SELECT * FROM brands 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Errore nel caricamento del profilo brand: " . $e->getMessage();
}

// =============================================
// FUNZIONE PER VERIFICARE IL LOGO
// =============================================
function getLogoFilename($logo_path) {
    if (empty($logo_path)) {
        return null;
    }
    
    // Se il percorso contiene "uploads/brands/", estrai solo il nome file
    if (strpos($logo_path, 'uploads/brands/') !== false) {
        return basename($logo_path);
    }
    
    // Se è già solo il nome file, restituiscilo direttamente
    return $logo_path;
}

function getLogoPath($logo_filename) {
    if (empty($logo_filename)) {
        return null;
    }
    
    // Percorso assoluto corretto
    $path = $_SERVER['DOCUMENT_ROOT'] . '/uploads/brands/' . $logo_filename;
    
    if (file_exists($path)) {
        return $path;
    }
    
    return null;
}

function getLogoUrl($logo_filename) {
    if (empty($logo_filename)) {
        return null;
    }
    
    // URL relativo corretto per il browser
    return '/uploads/brands/' . $logo_filename;
}

// Verifica del logo per il brand corrente
$logo_exists = false;
$logo_url = null;
$logo_filename = null;

if (!empty($brand['logo'])) {
    $logo_filename = getLogoFilename($brand['logo']);
    $logo_path = getLogoPath($logo_filename);
    $logo_url = getLogoUrl($logo_filename);
    $logo_exists = ($logo_path !== null);
}

// =============================================
// CONTENUTO PRINCIPALE DELLA DASHBOARD
// =============================================
?>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Dashboard Brand</h2>
            <?php if (!$brand): ?>
                <a href="create-profile.php" class="btn btn-primary">
                    Completa Profilo Aziendale
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

// Recupera le comunicazioni per brand
$communications = get_admin_communications($pdo, 'brand');

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
                Profilo brand creato con successo!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Sezione: Profilo Mancante -->
        <?php if (!$brand): ?>
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h4 class="card-title text-warning">Profilo Brand Non Completato</h4>
                    <p class="card-text">
                        Per iniziare a cercare influencer e creare campagne, 
                        completa il profilo della tua azienda.
                    </p>
                    <a href="create-profile.php" class="btn btn-warning btn-lg">
                        Completa Profilo Aziendale
                    </a>
                </div>
            </div>

        <!-- Sezione: Profilo Esistente -->
        <?php else: ?>
            <div class="row">
                <!-- Riepilogo Profilo Brand -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">Profilo Aziendale</h5>
                        </div>
                        <div class="card-body">
                            <!-- Logo del Brand -->
                            <div class="text-center mb-4">
                                <?php 
                                // Determina quale immagine mostrare
                                $display_logo_url = '/uploads/placeholder/brand_admin_edit.png'; // Placeholder predefinito
                                
                                if (!empty($brand['logo'])) {
                                    if ($logo_exists) {
                                        // Logo personalizzato caricato dall'utente
                                        $display_logo_url = $logo_url;
                                        $logo_status = 'personalizzato';
                                    } else {
                                        // Logo nel database ma file non trovato - mostra placeholder
                                        $logo_status = 'non trovato';
                                    }
                                } else {
                                    // Nessun logo nel database - mostra placeholder
                                    $logo_status = 'non caricato';
                                }
                                ?>
                                
                                <img src="<?php echo htmlspecialchars($display_logo_url); ?>" 
                                     alt="Logo <?php echo htmlspecialchars($brand['company_name'] ?? 'Azienda'); ?>" 
                                     class="img-fluid rounded brand-logo"
                                     style="max-height: 120px; max-width: 200px;">
                            </div>
                            
                            <div class="mb-3">
                                <strong>Azienda:</strong>
<span class="float-end"><?php echo htmlspecialchars_decode($brand['company_name'] ?? 'Non specificato'); ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>Settore:</strong>
<span class="float-end badge bg-info"><?php echo htmlspecialchars_decode($brand['industry'] ?? 'Non specificato'); ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>Nazione:</strong>
                                <span class="float-end"><?php echo htmlspecialchars($brand['nationality'] ?? 'Non specificato'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistiche e Azioni -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Statistiche</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Profilo Completato:</strong>
                                <span class="float-end">
                                    <?php 
                                    $completed = 0;
                                    $total = 5;
                                    $missing_fields = [];
                                    
                                    if (!empty($brand['company_name'])) {
                                        $completed++;
                                    } else {
                                        $missing_fields[] = 'Nome Azienda';
                                    }
                                    
                                    if (!empty($brand['industry'])) {
                                        $completed++;
                                    } else {
                                        $missing_fields[] = 'Settore';
                                    }
                                    
                                    if (!empty($brand['description'])) {
                                        $completed++;
                                    } else {
                                        $missing_fields[] = 'Descrizione Brand';
                                    }
                                    
                                    if (!empty($brand['nationality'])) {
                                        $completed++;
                                    } else {
                                        $missing_fields[] = 'Nazione';
                                    }
                                    
                                    if (!empty($brand['logo']) && $logo_exists) {
                                        $completed++;
                                    } else {
                                        $missing_fields[] = 'Logo';
                                    }
                                    
                                    // Aggiunta: Verifica campo Sito Web se esiste nella tabella brands
                                    // Nota: Verificare se il campo 'website' esiste nella struttura del database
                                    // Se esiste, scommentare queste righe:
                                    /*
                                    if (!empty($brand['website'])) {
                                        $completed++;
                                        $total++;
                                    } else {
                                        $missing_fields[] = 'Sito Web';
                                        $total++;
                                    }
                                    */
                                    
                                    echo $completed . '/' . $total;
                                    ?>
                                </span>
                            </div>
                            <div class="progress mb-3">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo ($completed/$total)*100; ?>%">
                                    <?php echo round(($completed/$total)*100); ?>%
                                </div>
                            </div>
                            
                            <!-- Sezione: Dati Mancanti -->
                            <?php if ($completed < $total && !empty($missing_fields)): ?>
                                <div class="mt-3">
                                    <small class="text-muted d-block mb-2">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Per completare il profilo, inserisci:
                                    </small>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($missing_fields as $field): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                                <span><?php echo htmlspecialchars($field); ?></span>
                                                <a href="edit-profile.php" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-plus"></i> Aggiungi
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <div class="mt-3">
                                    <small class="text-success">
                                        <i class="fas fa-check-circle me-1"></i>
                                        Profilo completo! Ottima visibilità per gli influencer
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Descrizione Azienda -->
            <?php if (!empty($brand['description'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Descrizione Azienda</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($brand['description'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Azioni Rapide Brand -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Azioni Rapide</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="edit-profile.php" class="btn btn-outline-primary w-100 mb-2">
                                <i class="fas fa-edit"></i> Modifica Profilo
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="search-influencers.php" class="btn btn-outline-success w-100 mb-2">
                                <i class="fas fa-search"></i> Cerca Influencer
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="create-campaign.php" class="btn btn-outline-info w-100 mb-2">
                                <i class="fas fa-plus"></i> Crea Campagna
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="campaigns.php" class="btn btn-outline-warning w-100 mb-2">
                                <i class="fas fa-list"></i> Le Mie Campagne
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<style>
.brand-logo {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #dee2e6;
}

.brand-logo-placeholder {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.brand-logo-placeholder:hover {
    border-color: #007bff;
    background: #e9f7fe;
}

.progress-bar {
    background-color: #28a745;
}
</style>

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