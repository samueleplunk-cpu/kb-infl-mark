<?php
require_once '../includes/config.php';
require_once '../includes/notification_functions.php';

// Verifica che l'utente sia un influencer loggato
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'influencer') {
    header("Location: /auth/login.php");
    exit;
}

// Determinazione della sezione attiva
$action = isset($_GET['action']) ? $_GET['action'] : 'notifications';
$valid_actions = ['notifications', 'personal-data', 'delete-account'];

// Validazione dell'azione
if (!in_array($action, $valid_actions)) {
    $action = 'notifications'; // Default
}

// Gestione salvataggio preferenze notifiche
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preferences'])) {
    $preferences = [];
    
    foreach ($_POST['preferences'] as $type_id => $settings) {
        $preferences[$type_id] = [
            'enabled' => isset($settings['enabled']),
            'email_enabled' => isset($settings['email_enabled'])
        ];
    }
    
    if (update_notification_preferences($pdo, $_SESSION['user_id'], 'influencer', $preferences)) {
        $_SESSION['success_message'] = "Preferenze notifiche aggiornate con successo";
    } else {
        $_SESSION['error_message'] = "Errore durante l'aggiornamento delle preferenze";
    }
    
    header("Location: settings.php?action=notifications");
    exit;
}

// Gestione aggiornamento dati personali
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_personal_data'])) {
    $new_email = trim($_POST['email'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    
    // Recupera l'email attuale
    $stmt = $pdo->prepare("SELECT email, password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_email = $user_data['email'] ?? '';
    $current_hashed_password = $user_data['password'] ?? '';
    
    $personal_data_error = '';
    $personal_data_success = '';
    
    // Validazione email
    if (empty($new_email)) {
        $personal_data_error = "L'email è obbligatoria.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $personal_data_error = "Inserisci un'email valida.";
    } else {
        // Verifica se l'email è già in uso da un altro utente
        if ($new_email !== $current_email) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$new_email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $personal_data_error = "Questa email è già associata a un altro account.";
            }
        }
    }
    
    // Validazione password (se fornita)
    $password_changed = false;
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $personal_data_error = "La nuova password deve avere almeno 6 caratteri.";
        } elseif ($new_password !== $confirm_password) {
            $personal_data_error = "Le password non corrispondono.";
        } elseif (empty($current_password)) {
            $personal_data_error = "Devi inserire la password attuale per cambiare la password.";
        } else {
            // Verifica password corrente
            if (!password_verify($current_password, $current_hashed_password)) {
                $personal_data_error = "La password attuale non è corretta.";
            } else {
                $password_changed = true;
            }
        }
    }
    
    // Se non ci sono errori, aggiorna i dati
    if (empty($personal_data_error)) {
        try {
            $pdo->beginTransaction();
            
            // Aggiorna email
            $stmt = $pdo->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_email, $_SESSION['user_id']]);
            
            // Aggiorna password se necessario
            if ($password_changed) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            }
            
            // Aggiorna la sessione se l'email è cambiata
            if ($new_email !== $current_email) {
                $_SESSION['user_email'] = $new_email;
            }
            
            $pdo->commit();
            
            $personal_data_success = "Dati personali aggiornati con successo!";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $personal_data_error = "Errore durante l'aggiornamento dei dati: " . $e->getMessage();
        }
    }
    
    // Salva i messaggi nella sessione per mostrarli dopo il redirect
    if ($personal_data_error) {
        $_SESSION['error_message'] = $personal_data_error;
    } elseif ($personal_data_success) {
        $_SESSION['success_message'] = $personal_data_success;
    }
    
    header("Location: settings.php?action=personal-data");
    exit;
}

// Gestione eliminazione account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $password = $_POST['password'] ?? '';
    $confirm = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === '1';
    
    if (!$confirm) {
        $_SESSION['error_message'] = "Devi confermare di voler eliminare il tuo account spuntando la checkbox.";
        header("Location: settings.php?action=delete-account");
        exit;
    }
    
    if (empty($password)) {
        $_SESSION['error_message'] = "Devi inserire la tua password per confermare l'eliminazione.";
        header("Location: settings.php?action=delete-account");
        exit;
    }
    
    // Verifica password corrente
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($password, $user['password'])) {
        $_SESSION['error_message'] = "Password non corretta. Inserisci la tua password attuale.";
        header("Location: settings.php?action=delete-account");
        exit;
    }
    
    // Importa funzioni admin per eliminazione
    require_once '../includes/admin_functions.php';
    
    // Esegui eliminazione completa
    if (deleteInfluencerCompletely($_SESSION['user_id'])) {
        // Logout e distruzione sessione
        session_destroy();
        header("Location: /auth/login.php?account_deleted=1");
        exit;
    } else {
        $_SESSION['error_message'] = "Si è verificato un errore durante l'eliminazione del tuo account. Riprova più tardi.";
        header("Location: settings.php?action=delete-account");
        exit;
    }
}

// Ottieni le preferenze notifiche (solo se nella sezione notifiche)
if ($action === 'notifications') {
    $preferences = get_notification_preferences($pdo, $_SESSION['user_id'], 'influencer');
}

// Ottieni i dati personali (solo se nella sezione personal-data)
if ($action === 'personal-data') {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_email = $user_data['email'] ?? '';
}

// Titolo in base alla sezione
$page_titles = [
    'notifications' => 'Preferenze Notifiche',
    'personal-data' => 'Dati Personali',
    'delete-account' => 'Elimina Account'
];
$page_title = $page_titles[$action] ?? 'Impostazioni Influencer';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - Influencer Marketplace'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .delete-warning {
            border-left: 4px solid #dc3545;
            background-color: #f8f9fa;
        }
        .data-list {
            list-style-type: none;
            padding-left: 0;
        }
        .data-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .data-list li:last-child {
            border-bottom: none;
        }
        .confirm-checkbox {
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="h2 mb-4"><?php echo $page_title; ?></h1>
            </div>
        </div>

        <div class="row">
           <div class="col-md-3">
    <div class="list-group">
        <a href="settings.php?action=notifications" class="list-group-item list-group-item-action <?php echo $action === 'notifications' ? 'active' : ''; ?>">
            Preferenze notifiche
        </a>
        <a href="settings.php?action=personal-data" class="list-group-item list-group-item-action <?php echo $action === 'personal-data' ? 'active' : ''; ?>">
            Dati personali
        </a>
        <a href="campaigns/saved-campaigns.php" class="list-group-item list-group-item-action">
            Campagne salvate
        </a>
        <a href="saved-brands.php" class="list-group-item list-group-item-action">
            Brand salvati
        </a>
        <a href="settings.php?action=delete-account" class="list-group-item list-group-item-action <?php echo $action === 'delete-account' ? 'active list-group-item-danger' : 'text-danger'; ?>">
            Elimina account
        </a>
    </div>
</div>
            
            <div class="col-md-9">
                <?php if ($action === 'notifications'): ?>
                    <!-- Sezione Notifiche -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-bell me-2"></i>Preferenze Notifiche
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Tipo Notifica</th>
                                                <th class="text-center">Notifica In-App</th>
                                                <th class="text-center">Email</th>
                                                <th>Descrizione</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($preferences as $pref): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($pref['name']); ?></strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="form-check form-switch d-inline-block">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   name="preferences[<?php echo $pref['id']; ?>][enabled]"
                                                                   id="enabled_<?php echo $pref['id']; ?>"
                                                                   <?php echo $pref['enabled'] ? 'checked' : ''; ?>>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="form-check form-switch d-inline-block">
                                                            <input class="form-check-input" 
                                                                   type="checkbox" 
                                                                   name="preferences[<?php echo $pref['id']; ?>][email_enabled]"
                                                                   id="email_enabled_<?php echo $pref['id']; ?>"
                                                                   <?php echo $pref['email_enabled'] ? 'checked' : ''; ?>>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted"><?php echo htmlspecialchars($pref['description']); ?></small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-4 d-flex justify-content-end">
                                    <button type="submit" name="update_preferences" class="btn btn-primary">
                                        Salva preferenze
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Ultime notifiche (solo in questa sezione) -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-history me-2"></i>Ultime Notifiche
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $recent_notifications = get_all_notifications($pdo, $_SESSION['user_id'], 'influencer', 5);
                            if (empty($recent_notifications)): ?>
                                <p class="text-muted">Nessuna notifica recente</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($recent_notifications as $notification): ?>
                                        <div class="list-group-item <?php echo !$notification['is_read'] ? 'list-group-item-warning' : ''; ?>">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                <small><?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <?php if (!$notification['is_read']): ?>
                                                <small class="text-warning">
                                                    <i class="fas fa-circle me-1"></i>Non letta
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php elseif ($action === 'personal-data'): ?>
                    <!-- Sezione Dati Personali -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user me-2"></i>Dati Personali
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="personalDataForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- Modifica Email -->
                                        <div class="mb-4">
                                            <h6><i class="fas fa-envelope me-2"></i>Modifica Email</h6>
                                            <div class="form-group mb-3">
                                                <label for="email" class="form-label">Email *</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($current_email); ?>" required>
                                                <div class="form-text">Il tuo indirizzo email per il login e le comunicazioni</div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <!-- Modifica Password -->
                                        <div class="mb-4">
                                            <h6><i class="fas fa-lock me-2"></i>Modifica Password</h6>
                                            <div class="form-group mb-3">
                                                <label for="password" class="form-label">Nuova Password</label>
                                                <input type="password" class="form-control" id="password" name="password">
                                                <div class="form-text">Lascia vuoto per mantenere la password attuale (minimo 6 caratteri)</div>
                                            </div>
                                            
                                            <div class="form-group mb-3">
                                                <label for="confirm_password" class="form-label">Conferma Nuova Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="current_password" class="form-label">Password Attuale *</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                                <div class="form-text">Inserisci la tua password attuale per confermare le modifiche</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Note importanti -->
                                <div class="alert alert-info mb-4">
                                    <h6><i class="fas fa-info-circle me-2"></i>Note importanti:</h6>
                                    <ul class="mb-0 small">
                                        <li>La modifica dell'email potrebbe richiedere una nuova verifica</li>
                                        <li>Assicurati di inserire correttamente la password attuale per confermare le modifiche</li>
                                        <li>Dopo aver cambiato email o password, dovrai utilizzare le nuove credenziali per il prossimo login</li>
                                    </ul>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" name="update_personal_data" class="btn btn-primary">
                                        Salva modifiche
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                <?php elseif ($action === 'delete-account'): ?>
                    <!-- Pagina di eliminazione account -->
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>Elimina Account Influencer
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Avviso di irreversibilità -->
                            <div class="alert alert-danger delete-warning mb-4">
                                <h5 class="alert-heading">
                                    <i class="fas fa-exclamation-circle me-2"></i>Attenzione: Operazione irreversibile
                                </h5>
                                <p class="mb-0">
                                    L'eliminazione del tuo account è un'operazione definitiva e irreversibile. 
                                    Una volta confermata, non sarà possibile recuperare i tuoi dati.
                                </p>
                            </div>
                            
                            <!-- Dati che verranno eliminati -->
                            <div class="mb-4">
                                <h5><i class="fas fa-list-alt me-2"></i>Cosa verrà eliminato:</h5>
                                <ul class="data-list mt-3">
                                    <li><i class="fas fa-user text-danger me-2"></i><strong>Account utente</strong> - Il tuo accesso alla piattaforma</li>
                                    <li><i class="fas fa-user-circle text-danger me-2"></i><strong>Profilo influencer</strong> - Dettagli personali e informazioni professionali</li>
                                    <li><i class="fas fa-bullhorn text-danger me-2"></i><strong>Proposte di sponsorizzazione</strong> - Tutte le proposte di sponsor create</li>
                                    <li><i class="fas fa-image text-danger me-2"></i><strong>Immagini del profilo e portfolio</strong> - Foto profilo e immagini del portfolio</li>
                                    <li><i class="fas fa-file text-danger me-2"></i><strong>Documenti</strong> - Documenti caricati per il profilo</li>
                                    <li><i class="fas fa-comments text-danger me-2"></i><strong>Conversazioni</strong> - Tutte le chat con i brand</li>
                                    <li><i class="fas fa-handshake text-danger me-2"></i><strong>Collaborazioni</strong> - Partecipazioni alle campagne</li>
                                    <li><i class="fas fa-bell text-danger me-2"></i><strong>Notifiche e preferenze</strong> - Storico notifiche e impostazioni</li>
                                    <li><i class="fas fa-history text-danger me-2"></i><strong>Storico attività</strong> - Tutte le attività registrate</li>
                                </ul>
                            </div>
                            
                            <!-- Form di conferma -->
                            <form method="POST" id="deleteAccountForm">
                                <div class="mb-4">
                                    <h5><i class="fas fa-shield-alt me-2"></i>Conferma di eliminazione</h5>
                                    
                                    <!-- Checkbox di conferma -->
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="confirm_delete" id="confirm_delete" value="1">
                                        <label class="form-check-label confirm-checkbox" for="confirm_delete">
                                            <i class="fas fa-check-circle me-1"></i>Confermo di voler eliminare definitivamente il mio account influencer e tutti i dati associati
                                        </label>
                                        <small class="form-text text-muted d-block">
                                            Spuntando questa casella, dichiari di essere consapevole che questa azione non può essere annullata.
                                        </small>
                                    </div>
                                    
                                    <!-- Campo password -->
                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-key me-1"></i>Inserisci la tua password per confermare
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <small class="form-text text-muted">
                                            Devi inserire la tua password corrente per verificare l'identità.
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Messaggi di errore/successo -->
                                <?php if (isset($_SESSION['error_message'])): ?>
                                    <div class="alert alert-danger">
                                        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Pulsanti di azione -->
                                <div class="d-flex justify-content-between">
                                    <a href="settings.php?action=notifications" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Torna alle Impostazioni
                                    </a>
                                    <button type="submit" name="delete_account" class="btn btn-danger" onclick="return confirmDelete()">
                                        <i class="fas fa-trash-alt me-2"></i>Elimina definitivamente account
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="card-footer text-muted small">
                            <i class="fas fa-info-circle me-1"></i>Dopo l'eliminazione, verrai reindirizzato alla pagina di login.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
    
    <?php if ($action === 'personal-data'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('personalDataForm');
            
            form.addEventListener('submit', function(e) {
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                const currentPassword = document.getElementById('current_password').value;
                
                // Validazione email
                if (!email) {
                    e.preventDefault();
                    alert('L\'email è obbligatoria');
                    document.getElementById('email').focus();
                    return false;
                }
                
                // Validazione formato email
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert('Inserisci un\'email valida');
                    document.getElementById('email').focus();
                    return false;
                }
                
                // Validazione password
                if (password) {
                    if (password.length < 6) {
                        e.preventDefault();
                        alert('La nuova password deve avere almeno 6 caratteri');
                        document.getElementById('password').focus();
                        return false;
                    }
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Le password non corrispondono');
                        document.getElementById('confirm_password').focus();
                        return false;
                    }
                }
                
                // Validazione password attuale
                if (!currentPassword) {
                    e.preventDefault();
                    alert('Devi inserire la password attuale per confermare le modifiche');
                    document.getElementById('current_password').focus();
                    return false;
                }
                
                return confirm('Sei sicuro di voler aggiornare i tuoi dati personali?');
            });
        });
    </script>
    <?php endif; ?>
    
    <?php if ($action === 'delete-account'): ?>
    <script>
        function confirmDelete() {
            if (!document.getElementById('confirm_delete').checked) {
                alert('Devi confermare di voler eliminare il tuo account spuntando la checkbox.');
                return false;
            }
            
            const password = document.getElementById('password').value;
            if (!password) {
                alert('Devi inserire la tua password per confermare l\'eliminazione.');
                return false;
            }
            
            return confirm('SEI ASSOLUTAMENTE SICURO?\n\nQuesta azione eliminerà PERMANENTEMENTE il tuo account e tutti i dati associati.\n\nQuesta operazione NON può essere annullata!');
        }
    </script>
    <?php endif; ?>
</body>
</html>