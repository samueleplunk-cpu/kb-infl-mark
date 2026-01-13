<?php
require_once '../includes/admin_header.php';

// Verifica che l'ID conversazione sia fornito
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID conversazione non valido";
    header("Location: messages.php");
    exit();
}

$conversation_id = intval($_GET['id']);

// INIZIO MODIFICA: Ottieni informazioni sullo stato di eliminazione degli utenti
$brand_deleted = false;
$influencer_deleted = false;

// Ottieni informazioni sulla conversazione
try {
    $sql = "
        SELECT 
            c.*,
            
            -- Brand info - MODIFICATO per corrispondere a messages.php
            COALESCE(
                (SELECT b.company_name FROM brands b WHERE b.id = c.brand_id LIMIT 1),
                (SELECT u.name FROM users u JOIN brands b ON u.id = b.user_id WHERE b.id = c.brand_id LIMIT 1),
                (SELECT u.company_name FROM users u JOIN brands b ON u.id = b.user_id WHERE b.id = c.brand_id LIMIT 1),
                CONCAT('Brand #', c.brand_id)
            ) as brand_name,
            
            (SELECT u.id FROM users u JOIN brands b ON u.id = b.user_id WHERE b.id = c.brand_id LIMIT 1) as brand_user_id,
            
            -- Influencer info
            COALESCE(
                (SELECT i.full_name FROM influencers i WHERE i.id = c.influencer_id LIMIT 1),
                (SELECT u.name FROM users u JOIN influencers i ON u.id = i.user_id WHERE i.id = c.influencer_id LIMIT 1),
                CONCAT('Influencer #', c.influencer_id)
            ) as influencer_name,
            
            (SELECT u.id FROM users u JOIN influencers i ON u.id = i.user_id WHERE i.id = c.influencer_id LIMIT 1) as influencer_user_id,
            
            -- Campagna (se esiste)
            (SELECT name FROM campaigns WHERE id = c.campaign_id LIMIT 1) as campaign_name
            
        FROM conversations c
        WHERE c.id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$conversation_id]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        $_SESSION['error_message'] = "Conversazione non trovata";
        header("Location: messages.php");
        exit();
    }
    
    // MODIFICA: Verifica se il brand è stato eliminato
    if (!empty($conversation['brand_user_id'])) {
        $stmt = $pdo->prepare("
            SELECT is_deleted, deleted_at 
            FROM users 
            WHERE id = ? AND user_type = 'brand'
        ");
        $stmt->execute([$conversation['brand_user_id']]);
        $brand_status = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($brand_status) {
            $brand_deleted = ($brand_status['is_deleted'] == 1 || !empty($brand_status['deleted_at']));
        }
    }
    
    // MODIFICA: Verifica se l'influencer è stato eliminato
    if (!empty($conversation['influencer_user_id'])) {
        $stmt = $pdo->prepare("
            SELECT is_deleted, deleted_at 
            FROM users 
            WHERE id = ? AND user_type = 'influencer'
        ");
        $stmt->execute([$conversation['influencer_user_id']]);
        $influencer_status = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($influencer_status) {
            $influencer_deleted = ($influencer_status['is_deleted'] == 1 || !empty($influencer_status['deleted_at']));
        }
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Errore nel recupero della conversazione: " . $e->getMessage();
    header("Location: messages.php");
    exit();
}

// Ottieni i messaggi della conversazione
try {
    $sql = "
        SELECT 
            m.*,
            CASE 
                WHEN m.sender_type = 'brand' THEN 
                    COALESCE(
                        (SELECT u.company_name FROM users u JOIN brands b ON u.id = b.user_id WHERE b.id = m.sender_id LIMIT 1),
                        (SELECT u.name FROM users u JOIN brands b ON u.id = b.user_id WHERE b.id = m.sender_id LIMIT 1),
                        CONCAT('Brand #', m.sender_id)
                    )
                WHEN m.sender_type = 'influencer' THEN 
                    COALESCE(
                        (SELECT i.full_name FROM influencers i WHERE i.id = m.sender_id LIMIT 1),
                        (SELECT u.name FROM users u JOIN influencers i ON u.id = i.user_id WHERE i.id = m.sender_id LIMIT 1),
                        CONCAT('Influencer #', m.sender_id)
                    )
            END as sender_display_name,
            m.sender_type
        FROM messages m
        WHERE m.conversation_id = ?
        ORDER BY m.sent_at ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$conversation_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Conta statistiche
    $total_messages = count($messages);
    $unread_messages = 0;
    foreach ($messages as $msg) {
        if (!$msg['is_read']) {
            $unread_messages++;
        }
    }
    
} catch (PDOException $e) {
    $messages = [];
    $total_messages = 0;
    $unread_messages = 0;
    error_log("Errore recupero messaggi: " . $e->getMessage());
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2">Conversazione</h1>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="/admin/messages.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Torna alla lista
        </a>
    </div>
</div>

<!-- Informazioni conversazione -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    Informazioni conversazione
                </h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
    <div class="col-6">
        <h6 class="text-muted mb-2">
            Brand:
        </h6>
        <p class="mb-0">
            <span class="fw-bold">
                <?php echo htmlspecialchars_decode(htmlspecialchars($conversation['brand_name']), ENT_QUOTES); ?>
                <?php if ($brand_deleted): ?>
                    <span class="text-black">(Account eliminato)</span>
                <?php endif; ?>
            </span>
        </p>
    </div>
    <div class="col-6">
        <h6 class="text-muted mb-2">
            Influencer:
        </h6>
        <p class="mb-0">
            <span class="fw-bold">
                <?php echo htmlspecialchars_decode(htmlspecialchars($conversation['influencer_name']), ENT_QUOTES); ?>
                <?php if ($influencer_deleted): ?>
                    <span class="text-black">(Account eliminato)</span>
                <?php endif; ?>
            </span>
        </p>
    </div>
</div>
                
                <?php if ($conversation['campaign_name']): ?>
<div class="row mb-3">
    <div class="col-12">
        <h6 class="text-muted mb-2">
            Campagna:
        </h6>
        <p class="mb-0">
            <?php echo htmlspecialchars($conversation['campaign_name']); ?>
        </p>
    </div>
</div>
<?php endif; ?>
                
                <div class="row">
    <div class="col-6">
        <h6 class="text-muted mb-2">
            Data inizio:
        </h6>
        <p class="mb-0">
            <?php echo date('d/m/Y - H:i', strtotime($conversation['created_at'])); ?>
        </p>
    </div>
    <div class="col-6">
        <h6 class="text-muted mb-2">
            Ultimo aggiornamento:
        </h6>
        <p class="mb-0">
            <?php echo date('d/m/Y - H:i', strtotime($conversation['updated_at'])); ?>
        </p>
    </div>
</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    Statistiche
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="stat-card p-3 bg-primary bg-opacity-10 rounded">
                            <h3 class="text-primary mb-2">
                                <i class="fas fa-envelope"></i>
                            </h3>
                            <h4 class="mb-1"><?php echo $total_messages; ?></h4>
                            <p class="text-muted mb-0">Messaggi totali</p>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="stat-card p-3 bg-<?php echo $unread_messages > 0 ? 'warning' : 'success'; ?> bg-opacity-10 rounded">
                            <h3 class="text-<?php echo $unread_messages > 0 ? 'warning' : 'success'; ?> mb-2">
                                <i class="fas fa-<?php echo $unread_messages > 0 ? 'bell' : 'check'; ?>"></i>
                            </h3>
                            <h4 class="mb-1"><?php echo $unread_messages; ?></h4>
                            <p class="text-muted mb-0">Messaggi non letti</p>
                        </div>
                    </div>
                </div>
                <!-- Aggiunta: Riepilogo conversazione spostato qui -->
                <div class="row mt-4 pt-3 border-top">
                    <div class="col-12">
                        <h6 class="text-muted mb-2">
                            Riepilogo conversazione
                        </h6>
                        <?php if (!empty($messages)): ?>
                            <?php 
                            $brand_messages = array_filter($messages, function($msg) {
                                return $msg['sender_type'] == 'brand';
                            });
                            $influencer_messages = array_filter($messages, function($msg) {
                                return $msg['sender_type'] == 'influencer';
                            });
                            ?>
                            <ul class="list-unstyled mb-0">
                                <li class="mb-1">
                                    <strong>Messaggi Brand:</strong> 
                                    <?php echo count($brand_messages); ?>
                                </li>
                                <li>
                                    <strong>Messaggi Influencer:</strong> 
                                    <?php echo count($influencer_messages); ?>
                                </li>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">Nessun messaggio nella conversazione</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chat -->
<div class="card">
    <div class="card-header bg-light">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                Conversazione
            </h5>
            <!-- Rimossi i badge numerici -->
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($messages)): ?>
            <div class="text-center py-5">
                <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                <h4>Nessun messaggio</h4>
                <p class="text-muted">La conversazione è vuota.</p>
            </div>
        <?php else: ?>
            <div class="chat-container" style="max-height: 600px; overflow-y: auto; padding: 20px;">
                <?php 
                $last_date = null;
                foreach ($messages as $message): 
                    $current_date = date('Y-m-d', strtotime($message['sent_at']));
                    
                    // Mostra separatore di data se cambia
                    if ($current_date != $last_date):
                        $last_date = $current_date;
                ?>
                    <div class="text-center my-4">
                        <span class="badge bg-secondary px-3 py-2">
                            <i class="far fa-calendar me-1"></i>
                            <?php 
                            $display_date = date('d F Y', strtotime($current_date));
                            $italian_months = [
                                'January' => 'Gennaio', 'February' => 'Febbraio', 'March' => 'Marzo',
                                'April' => 'Aprile', 'May' => 'Maggio', 'June' => 'Giugno',
                                'July' => 'Luglio', 'August' => 'Agosto', 'September' => 'Settembre',
                                'October' => 'Ottobre', 'November' => 'Novembre', 'December' => 'Dicembre'
                            ];
                            $display_date = str_replace(
                                array_keys($italian_months),
                                array_values($italian_months),
                                $display_date
                            );
                            echo $display_date;
                            ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <div class="message mb-4 <?php echo $message['sender_type'] == 'brand' ? 'text-end' : 'text-start'; ?>">
                    <div class="d-flex <?php echo $message['sender_type'] == 'brand' ? 'justify-content-end' : 'justify-content-start'; ?>">
                        <div class="message-bubble p-3 rounded" 
                             style="max-width: 75%; 
                                    background-color: <?php echo $message['sender_type'] == 'brand' ? '#e3f2fd' : '#f5f5f5'; ?>; 
                                    border: 1px solid <?php echo $message['sender_type'] == 'brand' ? '#bbdefb' : '#e0e0e0'; ?>;">
                            
                            <!-- Header messaggio -->
<div class="message-header mb-2 d-flex justify-content-between align-items-start">
    <div>
        <strong class="<?php echo $message['sender_type'] == 'brand' ? 'text-primary' : 'text-success'; ?>">
            <i class="fas fa-<?php echo $message['sender_type'] == 'brand' ? 'building' : 'user-check'; ?> me-1"></i>
            <?php echo htmlspecialchars($message['sender_display_name']); ?>
            <?php if (($message['sender_type'] == 'brand' && $brand_deleted) || ($message['sender_type'] == 'influencer' && $influencer_deleted)): ?>
                <span class="text-black">(Account eliminato)</span>
            <?php endif; ?>
        </strong>
        <span class="badge bg-<?php echo $message['sender_type'] == 'brand' ? 'primary' : 'success'; ?> bg-opacity-25 text-<?php echo $message['sender_type'] == 'brand' ? 'primary' : 'success'; ?> ms-2">
            <?php echo $message['sender_type'] == 'brand' ? 'Brand' : 'Influencer'; ?>
        </span>
    </div>
    <div class="text-end">
        <?php if (!$message['is_read']): ?>
            <span class="badge bg-warning text-dark">
                Non letto
            </span>
        <?php else: ?>
            <span class="badge bg-success">
                Letto
            </span>
        <?php endif; ?>
    </div>
</div>

<!-- Contenuto messaggio -->
<div class="message-content" style="line-height: 1.6;">
    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
</div>

<!-- Footer messaggio -->
<div class="message-footer mt-2 pt-2 border-top border-opacity-25">
    <small class="text-muted">
        Inviato: <?php echo date('d/m/Y - H:i', strtotime($message['sent_at'])); ?>
    </small>
</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.chat-container {
    scrollbar-width: thin;
    scrollbar-color: #adb5bd #f8f9fa;
}

.chat-container::-webkit-scrollbar {
    width: 8px;
}

.chat-container::-webkit-scrollbar-track {
    background: #f8f9fa;
    border-radius: 4px;
}

.chat-container::-webkit-scrollbar-thumb {
    background: #adb5bd;
    border-radius: 4px;
}

.message-bubble {
    position: relative;
    word-wrap: break-word;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.message-bubble::after {
    content: '';
    position: absolute;
    top: 15px;
    width: 0;
    height: 0;
    border-style: solid;
}

.message.text-start .message-bubble::after {
    left: -10px;
    border-width: 10px 10px 10px 0;
    border-color: transparent #f5f5f5 transparent transparent;
}

.message.text-end .message-bubble::after {
    right: -10px;
    border-width: 10px 0 10px 10px;
    border-color: transparent transparent transparent #e3f2fd;
}

.stat-card {
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}
</style>

<?php require_once '../includes/admin_footer.php'; ?>