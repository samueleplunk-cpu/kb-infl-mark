<?php
require_once '../includes/admin_header.php';
require_once '../includes/notification_functions.php';

// Gestione azioni
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_templates'])) {
        // Aggiorna template
        foreach ($_POST['templates'] as $type_id => $template_data) {
            $stmt = $pdo->prepare("
                UPDATE notification_types 
                SET default_title_template = ?, default_message_template = ?,
                    default_enabled = ?, default_email_enabled = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $template_data['title'],
                $template_data['message'],
                isset($template_data['enabled']) ? 1 : 0,
                isset($template_data['email_enabled']) ? 1 : 0,
                $type_id
            ]);
        }
        $_SESSION['success_message'] = "Template notifiche aggiornati con successo";
        header("Location: notifications.php");
        exit;
    }
}

// Ottieni tutti i tipi di notifica
$stmt = $pdo->prepare("SELECT * FROM notification_types ORDER BY name");
$stmt->execute();
$notification_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Gestione Notifiche</h1>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Configurazione Template Notifiche</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php foreach ($notification_types as $type): ?>
                        <div class="row mb-4 border-bottom pb-3">
                            <div class="col-12">
                                <h6 class="text-primary"><?php echo htmlspecialchars($type['name']); ?></h6>
                                <p class="text-muted small"><?php echo htmlspecialchars($type['description']); ?></p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Titolo Template</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="templates[<?php echo $type['id']; ?>][title]"
                                       value="<?php echo htmlspecialchars($type['default_title_template']); ?>"
                                       required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Messaggio Template</label>
                                <textarea class="form-control" 
                                          name="templates[<?php echo $type['id']; ?>][message]"
                                          rows="2" required><?php echo htmlspecialchars($type['default_message_template']); ?></textarea>
                                <div class="form-text">
                                    Placeholders disponibili: {{campaign_name}}, {{sender_name}}, {{message}}
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="templates[<?php echo $type['id']; ?>][enabled]"
                                           id="enabled_<?php echo $type['id']; ?>"
                                           <?php echo $type['default_enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enabled_<?php echo $type['id']; ?>">
                                        Notifica abilitata di default
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="templates[<?php echo $type['id']; ?>][email_enabled]"
                                           id="email_enabled_<?php echo $type['id']; ?>"
                                           <?php echo $type['default_email_enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="email_enabled_<?php echo $type['id']; ?>">
                                        Email abilitata di default
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" name="update_templates" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Salva Modifiche
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_footer.php'; ?>