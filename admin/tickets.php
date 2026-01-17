<?php
require_once dirname(__DIR__) . '/includes/admin_header.php';

// Include le funzioni ticket
$ticket_functions_file = dirname(__DIR__) . '/ticket/includes/ticket_functions.php';
if (file_exists($ticket_functions_file)) {
    require_once $ticket_functions_file;
} else {
    die("File funzioni ticket non trovato.");
}

// Imposta titolo pagina
$page_title = "Gestione Ticket di Supporto";

// Parametri per filtri e paginazione
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 20;
$offset = ($page - 1) * $items_per_page;

// Elabora cambio stato (se richiesto)
if (isset($_POST['change_status']) && isset($_POST['ticket_id']) && isset($_POST['new_status'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $new_status = $_POST['new_status'];
    
    if (update_ticket_status($ticket_id, $new_status)) {
        $_SESSION['success_message'] = "Stato del ticket aggiornato con successo.";
    } else {
        $_SESSION['error_message'] = "Errore nell'aggiornamento dello stato.";
    }
    
    // Redirect per evitare resubmission
    header("Location: tickets.php?" . http_build_query($_GET));
    exit();
}

// Funzione time_ago se non esiste (aggiunta qui per sicurezza)
if (!function_exists('time_ago')) {
    function time_ago($timestamp) {
        if (empty($timestamp)) {
            return 'mai';
        }
        
        $now = new DateTime();
        $past = new DateTime($timestamp);
        $diff = $now->diff($past);
        
        if ($diff->y > 0) {
            return $diff->y == 1 ? '1 anno fa' : $diff->y . ' anni fa';
        } elseif ($diff->m > 0) {
            return $diff->m == 1 ? '1 mese fa' : $diff->m . ' mesi fa';
        } elseif ($diff->d > 0) {
            return $diff->d == 1 ? '1 giorno fa' : $diff->d . ' giorni fa';
        } elseif ($diff->h > 0) {
            return $diff->h == 1 ? '1 ora fa' : $diff->h . ' ore fa';
        } elseif ($diff->i > 0) {
            return $diff->i == 1 ? '1 minuto fa' : $diff->i . ' minuti fa';
        } else {
            return 'pochi secondi fa';
        }
    }
}

// Ottieni tutti i ticket con filtri
try {
    $sql = "
        SELECT t.*, 
               CASE 
                 WHEN t.user_type = 'brand' THEN b.company_name
                 WHEN t.user_type = 'influencer' THEN i.full_name
                 ELSE 'Utente'
               END as user_name,
               u.email as user_email,
               COUNT(DISTINCT tm.id) as message_count,
               MAX(tm.created_at) as last_message_date
        FROM tickets t
        LEFT JOIN brands b ON t.user_id = b.user_id AND t.user_type = 'brand'
        LEFT JOIN influencers i ON t.user_id = i.user_id AND t.user_type = 'influencer'
        LEFT JOIN users u ON (t.user_type = 'brand' AND b.user_id = u.id) OR (t.user_type = 'influencer' AND i.user_id = u.id)
        LEFT JOIN ticket_messages tm ON t.id = tm.ticket_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Applica filtro stato
    if ($status_filter && $status_filter !== 'all') {
        if ($status_filter === 'open') {
            $sql .= " AND (t.status = 'open' OR t.status = 'in_progress')";
        } elseif ($status_filter === 'closed') {
            $sql .= " AND (t.status = 'closed' OR t.status = 'resolved')";
        } else {
            $sql .= " AND t.status = ?";
            $params[] = $status_filter;
        }
    }
    
    // Applica ricerca
    if (!empty($search_query)) {
        if (is_numeric($search_query)) {
            $sql .= " AND t.id = ?";
            $params[] = intval($search_query);
        } else {
            // MODIFICA: ricerca estesa per includere username e email
            $sql .= " AND (t.subject LIKE ? OR t.message LIKE ? OR b.company_name LIKE ? OR i.full_name LIKE ? OR u.email LIKE ?)";
            $search_param = "%" . $search_query . "%";
            $params[] = $search_param; // subject
            $params[] = $search_param; // message
            $params[] = $search_param; // company_name (brand)
            $params[] = $search_param; // full_name (influencer)
            $params[] = $search_param; // email
        }
    }
    
    $sql .= " GROUP BY t.id ORDER BY t.updated_at DESC";
    
    // Query per il conteggio totale (per paginazione)
    // Usa una subquery corretta
    $count_sql = "SELECT COUNT(*) as total FROM (
        SELECT DISTINCT t.id 
        FROM tickets t
        LEFT JOIN brands b ON t.user_id = b.user_id AND t.user_type = 'brand'
        LEFT JOIN influencers i ON t.user_id = i.user_id AND t.user_type = 'influencer'
        LEFT JOIN users u ON (t.user_type = 'brand' AND b.user_id = u.id) OR (t.user_type = 'influencer' AND i.user_id = u.id)
        WHERE 1=1
    ";
    
    // Aggiungi le stesse condizioni WHERE al count
    $count_params = [];
    if ($status_filter && $status_filter !== 'all') {
        if ($status_filter === 'open') {
            $count_sql .= " AND (t.status = 'open' OR t.status = 'in_progress')";
        } elseif ($status_filter === 'closed') {
            $count_sql .= " AND (t.status = 'closed' OR t.status = 'resolved')";
        } else {
            $count_sql .= " AND t.status = ?";
            $count_params[] = $status_filter;
        }
    }
    
    if (!empty($search_query)) {
        if (is_numeric($search_query)) {
            $count_sql .= " AND t.id = ?";
            $count_params[] = intval($search_query);
        } else {
            // MODIFICA: ricerca estesa per includere username e email nel count
            $count_sql .= " AND (t.subject LIKE ? OR t.message LIKE ? OR b.company_name LIKE ? OR i.full_name LIKE ? OR u.email LIKE ?)";
            $search_param = "%" . $search_query . "%";
            $count_params[] = $search_param; // subject
            $count_params[] = $search_param; // message
            $count_params[] = $search_param; // company_name (brand)
            $count_params[] = $search_param; // full_name (influencer)
            $count_params[] = $search_param; // email
        }
    }
    
    $count_sql .= ") as filtered_tickets";
    
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($count_params);
    $result = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $total_items = $result ? $result['total'] : 0;
    
    // Aggiungi limit e offset per la paginazione
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $items_per_page;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_pages = ceil($total_items / $items_per_page);
    
} catch (PDOException $e) {
    error_log("Errore nel recupero dei ticket: " . $e->getMessage());
    error_log("SQL Error: " . $e->getMessage());
    $tickets = [];
    $total_pages = 1;
    $total_items = 0;
}

// MODIFICA: Funzione per testo priorità (senza badge)
function get_priority_text($priority) {
    $priority_names = [
        'low' => 'Bassa',
        'medium' => 'Media',
        'high' => 'Alta',
        'urgent' => 'Urgente'
    ];
    return $priority_names[$priority] ?? $priority;
}

// Funzione per badge stato
function get_status_badge($status) {
    $status_names = [
        'open' => 'Aperto',
        'in_progress' => 'In elaborazione',
        'resolved' => 'Risolto',
        'closed' => 'Chiuso'
    ];
    
    $colors = [
        'open' => 'success',
        'in_progress' => 'warning',
        'resolved' => 'info',
        'closed' => 'secondary'
    ];
    
    $status_text = $status_names[$status] ?? $status;
    $color = $colors[$status] ?? 'secondary';
    
    return '<span class="badge bg-' . $color . '">' . $status_text . '</span>';
}

// CALCOLA i contatori per le card
try {
    $stmt_total = $pdo->prepare("SELECT COUNT(*) as count FROM tickets");
    $stmt_total->execute();
    $result_total = $stmt_total->fetch(PDO::FETCH_ASSOC);
    $total_tickets_count = $result_total ? $result_total['count'] : 0;
    
    $stmt_open = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE status IN ('open', 'in_progress')");
    $stmt_open->execute();
    $result_open = $stmt_open->fetch(PDO::FETCH_ASSOC);
    $open_tickets_count = $result_open ? $result_open['count'] : 0;
    
    $stmt_resolved = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE status = 'resolved'");
    $stmt_resolved->execute();
    $result_resolved = $stmt_resolved->fetch(PDO::FETCH_ASSOC);
    $resolved_tickets_count = $result_resolved ? $result_resolved['count'] : 0;
    
    $stmt_closed = $pdo->prepare("SELECT COUNT(*) as count FROM tickets WHERE status = 'closed'");
    $stmt_closed->execute();
    $result_closed = $stmt_closed->fetch(PDO::FETCH_ASSOC);
    $closed_tickets_count = $result_closed ? $result_closed['count'] : 0;
} catch (PDOException $e) {
    $total_tickets_count = 0;
    $open_tickets_count = 0;
    $resolved_tickets_count = 0;
    $closed_tickets_count = 0;
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    Ticket di Supporto
                </h1>
                <div>
                    
                    
                </div>
            </div>

            <!-- Contatori ticket - SPOSTATI IN ALTO -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Ticket Totali</h6>
                                    <h3><?php echo $total_tickets_count; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-ticket-perforated fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Aperti</h6>
                                    <h3><?php echo $open_tickets_count; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-folder-open fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Risolti</h6>
                                    <h3><?php echo $resolved_tickets_count; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Chiusi</h6>
                                    <h3><?php echo $closed_tickets_count; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-archive fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtri e ricerca -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <label for="status" class="form-label">Filtra per stato</label>
                            <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>Tutti i ticket</option>
                                <option value="open" <?php echo $status_filter == 'open' ? 'selected' : ''; ?>>Aperti</option>
                                <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In elaborazione</option>
                                <option value="resolved" <?php echo $status_filter == 'resolved' ? 'selected' : ''; ?>>Risolti</option>
                                <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Chiusi</option>
                            </select>
                        </div>
                        
                        <div class="col-md-5">
                            <label for="search" class="form-label">Cerca ticket</label>
                            <div class="input-group">
                                <input type="text" 
                                       name="search" 
                                       id="search" 
                                       class="form-control" 
                                       placeholder="Cerca per ID ticket, oggetto, username o email..." 
                                       value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-outline-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="tickets.php?status=<?php echo $status_filter; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <a href="tickets.php" class="btn btn-secondary w-100">
                                Reset filtri
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabella ticket -->
            <div class="card">
                <div class="card-body p-0">
                    <?php if (empty($tickets)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <h4 class="mt-3">Nessun ticket trovato</h4>
                            <p class="text-muted">Non ci sono ticket che corrispondono ai criteri di ricerca.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center">ID</th>
                                        <th class="text-center">Oggetto</th>
                                        <th class="text-center">Creato da</th>
                                        <th class="text-center">Stato</th>
                                        <th class="text-center">Priorità</th>
                                        <th class="text-center">Data creazione</th>
                                        <th class="text-center">Messaggi</th>
                                        <th class="text-center">Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $ticket): 
                                        $last_message_time = !empty($ticket['last_message_date']) 
                                            ? time_ago($ticket['last_message_date']) 
                                            : '-';
                                    ?>
                                        <tr>
                                            <td class="text-center">
                                                <span class="badge bg-dark">#<?php echo $ticket['id']; ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-truncate" title="<?php echo htmlspecialchars($ticket['subject']); ?>">
                                                    <?php echo htmlspecialchars($ticket['subject']); ?>
                                                </div>
                                                <div class="text-muted small text-truncate">
                                                    <?php echo htmlspecialchars(substr($ticket['message'], 0, 80)); ?>...
                                                </div>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($ticket['user_name']); ?></div>
                                                <small class="text-muted"><?php echo $ticket['user_type']; ?></small>
                                                <?php if (!empty($ticket['user_email'])): ?>
                                                    <br><small class="text-muted"><?php echo $ticket['user_email']; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php echo get_status_badge($ticket['status']); ?>
                                                <form method="POST" action="" class="mt-1">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                    <select name="new_status" class="form-select form-select-sm" onchange="if(confirm('Cambiare stato del ticket?')){this.form.submit();}">
                                                        <option value="open" <?php echo $ticket['status'] == 'open' ? 'selected' : ''; ?>>Aperto</option>
                                                        <option value="in_progress" <?php echo $ticket['status'] == 'in_progress' ? 'selected' : ''; ?>>In elaborazione</option>
                                                        <option value="resolved" <?php echo $ticket['status'] == 'resolved' ? 'selected' : ''; ?>>Risolto</option>
                                                        <option value="closed" <?php echo $ticket['status'] == 'closed' ? 'selected' : ''; ?>>Chiuso</option>
                                                    </select>
                                                    <input type="hidden" name="change_status" value="1">
                                                </form>
                                            </td>
                                            <td class="text-center">
                                                <!-- MODIFICA: Mostra solo testo senza badge -->
                                                <div class="fw-normal">
                                                    <?php echo get_priority_text($ticket['priority']); ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="text-nowrap"><?php echo date('d/m/Y - H:i', strtotime($ticket['created_at'])); ?></div>
                                                <small class="text-muted d-block mt-1"><?php echo time_ago($ticket['created_at']); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info rounded-pill"><?php echo $ticket['message_count']; ?></span>
                                                <?php if ($ticket['last_message_date']): ?>
                                                    <div class="text-muted small">Ultimo: <?php echo $last_message_time; ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
    <div class="d-flex gap-2 justify-content-center">
        <a href="/admin/view_ticket.php?id=<?php echo $ticket['id']; ?>" 
           class="btn btn-primary btn-sm" 
           title="Visualizza e rispondi">
            <i class="fas fa-eye me-1"></i> Visualizza
        </a>
        
        <a href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>&page=<?php echo $page; ?>" 
           class="btn btn-warning btn-sm" 
           title="Aggiorna">
            <i class="fas fa-sync-alt"></i>
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
                            <nav class="p-3 border-top">
                                <ul class="pagination justify-content-center mb-0">
                                    <li class="page-item <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    if ($start_page > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?status=' . $status_filter . '&search=' . urlencode($search_query) . '&page=1">1</a></li>';
                                        if ($start_page > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">';
                                        echo '<a class="page-link" href="?status=' . $status_filter . '&search=' . urlencode($search_query) . '&page=' . $i . '">' . $i . '</a>';
                                        echo '</li>';
                                    }
                                    
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?status=' . $status_filter . '&search=' . urlencode($search_query) . '&page=' . $total_pages . '">' . $total_pages . '</a></li>';
                                    }
                                    ?>
                                    
                                    <li class="page-item <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                                <p class="text-center text-muted small mt-2">
                                    Pagina <?php echo $page; ?> di <?php echo $total_pages; ?> - 
                                    Visualizzati <?php echo count($tickets); ?> di <?php echo $total_items; ?> ticket
                                </p>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tooltip per i pulsanti
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-submit per cambio stato con conferma
    var statusSelects = document.querySelectorAll('select[name="new_status"]');
    statusSelects.forEach(function(select) {
        var originalValue = select.value;
        select.addEventListener('change', function() {
            if (confirm('Sei sicuro di voler cambiare lo stato di questo ticket?')) {
                this.form.submit();
            } else {
                this.value = originalValue;
            }
        });
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>