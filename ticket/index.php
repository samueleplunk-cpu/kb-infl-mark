<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once __DIR__ . '/includes/ticket_functions.php';

// Verifica login
if (!is_logged_in()) {
    header("Location: /auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Parametri di filtro
$status = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Ottieni ticket
$tickets = get_user_tickets($user_id, $user_type, $status, 1000);

// Paginazione
$total_tickets = count($tickets);
$total_pages = ceil($total_tickets / $limit);
$current_tickets = array_slice($tickets, $offset, $limit);

// Conta per stati
$open_count = count(array_filter($tickets, fn($t) => in_array($t['status'], ['open', 'in_progress'])));
$closed_count = count(array_filter($tickets, fn($t) => in_array($t['status'], ['closed', 'resolved'])));

// Notifiche non lette (sistema ticket) - Mantenuta per compatibilità con altre funzioni
$unread_ticket_notifications = get_unread_ticket_notifications($user_id, $user_type);
?>
<?php include dirname(__DIR__) . '/includes/header.php'; ?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col">
            <h1 class="h2">Ticket di Supporto</h1>
            
        </div>
        <div class="col-auto">
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nuovo Ticket
            </a>
        </div>
    </div>
    
    
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Cronologia</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Aperti:</span>
                        <strong><?php echo $open_count; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Chiusi:</span>
                        <strong><?php echo $closed_count; ?></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">I Tuoi Ticket</h5>
                        <div class="btn-group" role="group">
                            <a href="?status=all" class="btn btn-sm <?php echo $status === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">Tutti</a>
                            <a href="?status=open" class="btn btn-sm <?php echo $status === 'open' ? 'btn-primary' : 'btn-outline-primary'; ?>">Aperti</a>
                            <a href="?status=closed" class="btn btn-sm <?php echo $status === 'closed' ? 'btn-primary' : 'btn-outline-primary'; ?>">Chiusi</a>
                        </div>
                    </div>
                    
                    <?php if (empty($current_tickets)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-ticket-detailed display-1 text-muted"></i>
                            <h4 class="mt-3">Nessun ticket trovato</h4>
                            <p class="text-muted">Non hai ancora creato ticket di supporto</p>
                            <a href="create.php" class="btn btn-primary">Crea il tuo primo ticket</a>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($current_tickets as $ticket): ?>
                                <a href="view.php?id=<?php echo $ticket['id']; ?>" 
                                   class="list-group-item list-group-item-action ticket-card <?php echo $ticket['status']; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($ticket['subject']); ?></h6>
                                        <small>
                                            <span class="ticket-status status-<?php echo $ticket['status']; ?>">
                                                <?php 
                                                    $status_names = [
                                                        'open' => 'Aperto',
                                                        'in_progress' => 'In Elaborazione',
                                                        'closed' => 'Chiuso',
                                                        'resolved' => 'Risolto'
                                                    ];
                                                    echo $status_names[$ticket['status']] ?? $ticket['status'];
                                                ?>
                                            </span>
                                        </small>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">
                                                <span class="priority-badge priority-<?php echo $ticket['priority']; ?> me-2">
                                                    <?php 
                                                        $priority_names = [
                                                            'low' => 'Bassa',
                                                            'medium' => 'Media',
                                                            'high' => 'Alta',
                                                            'urgent' => 'Urgente'
                                                        ];
                                                        echo $priority_names[$ticket['priority']] ?? $ticket['priority'];
                                                    ?>
                                                </span>
                                                Creato il <?php echo date('d/m/Y - H:i', strtotime($ticket['created_at'])); ?>
                                                <?php if ($ticket['message_count'] > 1): ?>
                                                    • <?php echo $ticket['message_count']; ?> messaggi
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div>
                                            <small class="text-muted">
                                                Aggiornato <?php echo time_ago($ticket['updated_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status; ?>">Precedente</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status; ?>">Successiva</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

<!-- Fix per i dropdown delle notifiche e profilo -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inizializza tutti i dropdown di Bootstrap manualmente
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
    var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
        return new bootstrap.Dropdown(dropdownToggleEl)
    });
    
    // Gestione specifica per i dropdown nell'header
    document.querySelectorAll('.nav-link[href="#"]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            // Se è un dropdown toggle, previeni il comportamento predefinito
            if (this.classList.contains('dropdown-toggle')) {
                e.preventDefault();
                
                // Trova il dropdown corrispondente e aprilo/chiudilo
                var dropdownElement = bootstrap.Dropdown.getInstance(this);
                if (dropdownElement) {
                    dropdownElement.toggle();
                }
            }
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function time_ago(timestamp) {
        // Funzione helper per tempo trascorso
        const now = new Date();
        const past = new Date(timestamp);
        const diff = Math.floor((now - past) / 1000);
        
        if (diff < 60) return 'pochi secondi fa';
        if (diff < 3600) return Math.floor(diff / 60) + ' minuti fa';
        if (diff < 86400) return Math.floor(diff / 3600) + ' ore fa';
        return Math.floor(diff / 86400) + ' giorni fa';
    }
</script>

<style>
    .ticket-status {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
    }
    .status-open { background-color: #d1ecf1; color: #0c5460; }
    .status-in_progress { background-color: #fff3cd; color: #856404; }
    .status-resolved { background-color: #d4edda; color: #155724; }
    .status-closed { background-color: #f8d7da; color: #721c24; }
    
    .priority-badge {
        font-size: 0.75rem;
        padding: 0.2rem 0.4rem;
        border-radius: 0.2rem;
    }
    .priority-low { background-color: #e8f5e8; color: #2e7d32; }
    .priority-medium { background-color: #fff3e0; color: #ef6c00; }
    .priority-high { background-color: #ffebee; color: #c62828; }
    .priority-urgent { background-color: #fce4ec; color: #ad1457; }
    
    .ticket-card {
        transition: all 0.2s;
        border-left: 4px solid transparent;
    }
    .ticket-card:hover {
        /* Effetto hover rimosso */
    }
    .ticket-card.open { border-left-color: #17a2b8; }
    .ticket-card.in_progress { border-left-color: #ffc107; }
    .ticket-card.resolved { border-left-color: #28a745; }
    .ticket-card.closed { border-left-color: #dc3545; }
    
    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
    }
</style>
</body>
</html>