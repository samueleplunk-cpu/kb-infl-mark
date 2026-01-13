<?php
require_once '../includes/admin_header.php';
require_once '../includes/functions.php';
require_once '../includes/communication_functions.php';

$page_title = "Gestione Comunicazioni";
$active_menu = "communications";

// Gestione form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_communication') {
        $user_type = $_POST['user_type'] ?? '';
        $message = trim($_POST['message'] ?? '');
        $link = trim($_POST['link'] ?? '');
        
        if (empty($user_type) || empty($message)) {
            $_SESSION['error_message'] = 'Tutti i campi obbligatori devono essere compilati.';
        } else {
            if (add_admin_communication($pdo, $user_type, $message, $link)) {
                $_SESSION['success_message'] = 'Comunicazione aggiunta con successo!';
            } else {
                $_SESSION['error_message'] = 'Errore nell\'aggiunta della comunicazione.';
            }
        }
    }
    elseif ($action === 'delete_communication') {
        $id = $_POST['id'] ?? '';
        if ($id && delete_admin_communication($pdo, $id)) {
            $_SESSION['success_message'] = 'Comunicazione eliminata con successo!';
        } else {
            $_SESSION['error_message'] = 'Errore nell\'eliminazione della comunicazione.';
        }
    }
    elseif ($action === 'toggle_communication') {
        $id = $_POST['id'] ?? '';
        $is_active = isset($_POST['is_active']) ? (bool)$_POST['is_active'] : false;
        
        if ($id && toggle_admin_communication($pdo, $id, $is_active)) {
            $_SESSION['success_message'] = 'Stato comunicazione aggiornato!';
        } else {
            $_SESSION['error_message'] = 'Errore nell\'aggiornamento dello stato.';
        }
    }
    
    // Redirect per evitare reinvio form
    header("Location: communications.php");
    exit;
}

// Recupera tutte le comunicazioni (incluse quelle inattive)
$all_influencer_comms = get_admin_communications($pdo, 'influencer', true);
$all_brand_comms = get_admin_communications($pdo, 'brand', true);

// Recupera solo le comunicazioni attive
$influencer_comms = get_admin_communications($pdo, 'influencer', false);
$brand_comms = get_admin_communications($pdo, 'brand', false);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestione Comunicazioni</h1>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <!-- Nav tabs -->
        <ul class="nav nav-tabs" id="communicationsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab" aria-controls="dashboard" aria-selected="true">
                    Dashboard
                </button>
            </li>
        </ul>

        <!-- Tab panes -->
        <div class="tab-content p-4 border border-top-0 rounded-bottom">
            <!-- Tab Dashboard -->
            <div class="tab-pane fade show active" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
                <!-- Sezione Aggiungi Nuova Comunicazione -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            Aggiungi comunicazione
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="addCommunicationForm">
                            <input type="hidden" name="action" value="add_communication">
                            
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Utente *</label>
                                    <select class="form-select" name="user_type" required>
                                        <option value="">Seleziona...</option>
                                        <option value="influencer">Influencer</option>
                                        <option value="brand">Brand</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Messaggio *</label>
                                    <textarea class="form-control" name="message" rows="2" 
                                              placeholder="Inserisci il messaggio da visualizzare nelle dashboard..." 
                                              required></textarea>
                                    <div class="form-text">Massimo 500 caratteri</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Link (opzionale)</label>
                                    <input type="url" class="form-control" name="link" 
                                           placeholder="https://...">
                                    <div class="form-text">URL per maggiori informazioni</div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                Aggiungi comunicazione
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Sezione Doppia: Influencer e Brand -->
                <div class="row">
                    <!-- Colonna Influencer -->
                    <div class="col-md-6">
                        <div class="card mb-4 h-100">
                            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    Comunicazioni per Influencer
                                </h5>
                                <span class="badge bg-light text-dark">
                                    <?php echo count($influencer_comms); ?> attive / <?php echo count($all_influencer_comms); ?> totali
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($all_influencer_comms)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-comment-slash fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">Nessuna comunicazione per gli influencer.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($all_influencer_comms as $comm): ?>
                                            <div class="list-group-item list-group-item-action mb-2">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div class="mb-1 flex-grow-1">
                                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($comm['message'])); ?></p>
                                                        <?php if (!empty($comm['link'])): ?>
                                                            <small>
                                                                <a href="<?php echo htmlspecialchars($comm['link']); ?>" 
                                                                   target="_blank" 
                                                                   class="text-decoration-none">
                                                                    <i class="fas fa-link me-1"></i><?php echo htmlspecialchars($comm['link']); ?>
                                                                </a>
                                                            </small>
                                                        <?php endif; ?>
                                                        <small class="text-muted d-block mt-1">
                                                            <i class="far fa-calendar me-1"></i>
                                                            <?php echo date('d/m/Y H:i', strtotime($comm['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <div class="d-flex flex-column ms-2">
                                                        <form method="POST" class="d-inline mb-1">
                                                            <input type="hidden" name="action" value="toggle_communication">
                                                            <input type="hidden" name="id" value="<?php echo $comm['id']; ?>">
                                                            <input type="hidden" name="is_active" value="<?php echo $comm['is_active'] ? '0' : '1'; ?>">
                                                            <button type="submit" class="btn btn-sm <?php echo $comm['is_active'] ? 'btn-warning' : 'btn-secondary'; ?>">
                                                                <i class="fas <?php echo $comm['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Sei sicuro di voler eliminare questa comunicazione?');">
                                                            <input type="hidden" name="action" value="delete_communication">
                                                            <input type="hidden" name="id" value="<?php echo $comm['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge <?php echo $comm['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $comm['is_active'] ? 'Attiva' : 'Disattivata'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Colonna Brand -->
                    <div class="col-md-6">
                        <div class="card mb-4 h-100">
                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    Comunicazioni per Brand
                                </h5>
                                <span class="badge bg-light text-dark">
                                    <?php echo count($brand_comms); ?> attive / <?php echo count($all_brand_comms); ?> totali
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($all_brand_comms)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-comment-slash fa-2x text-muted mb-3"></i>
                                        <p class="text-muted">Nessuna comunicazione per i brand.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($all_brand_comms as $comm): ?>
                                            <div class="list-group-item list-group-item-action mb-2">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div class="mb-1 flex-grow-1">
                                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($comm['message'])); ?></p>
                                                        <?php if (!empty($comm['link'])): ?>
                                                            <small>
                                                                <a href="<?php echo htmlspecialchars($comm['link']); ?>" 
                                                                   target="_blank" 
                                                                   class="text-decoration-none">
                                                                    <i class="fas fa-link me-1"></i><?php echo htmlspecialchars($comm['link']); ?>
                                                                </a>
                                                            </small>
                                                        <?php endif; ?>
                                                        <small class="text-muted d-block mt-1">
                                                            <i class="far fa-calendar me-1"></i>
                                                            <?php echo date('d/m/Y H:i', strtotime($comm['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <div class="d-flex flex-column ms-2">
                                                        <form method="POST" class="d-inline mb-1">
                                                            <input type="hidden" name="action" value="toggle_communication">
                                                            <input type="hidden" name="id" value="<?php echo $comm['id']; ?>">
                                                            <input type="hidden" name="is_active" value="<?php echo $comm['is_active'] ? '0' : '1'; ?>">
                                                            <button type="submit" class="btn btn-sm <?php echo $comm['is_active'] ? 'btn-warning' : 'btn-secondary'; ?>">
                                                                <i class="fas <?php echo $comm['is_active'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Sei sicuro di voler eliminare questa comunicazione?');">
                                                            <input type="hidden" name="action" value="delete_communication">
                                                            <input type="hidden" name="id" value="<?php echo $comm['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="badge <?php echo $comm['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                        <?php echo $comm['is_active'] ? 'Attiva' : 'Disattivata'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Limita il numero di caratteri nel messaggio
    const messageTextarea = document.querySelector('textarea[name="message"]');
    if (messageTextarea) {
        messageTextarea.addEventListener('input', function() {
            if (this.value.length > 500) {
                this.value = this.value.substring(0, 500);
                alert('Il messaggio non può superare i 500 caratteri.');
            }
        });
    }
});
</script>

<?php require_once '../includes/admin_footer.php'; ?>