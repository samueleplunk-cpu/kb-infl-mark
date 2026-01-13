<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Percorso assoluto a config
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Config file not found");
}
require_once $config_file;

// Se già loggato, reindirizza alla dashboard appropriata
if (is_logged_in()) {
    if (is_influencer()) {
        header("Location: /influencers/dashboard.php");
    } else {
        header("Location: /brands/dashboard.php");
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        // Cerca utente nella tabella USERS con tutti i controlli di sicurezza
        $stmt = $pdo->prepare("SELECT id, email, password, user_type, is_suspended, is_blocked, suspension_end, deleted_at 
                               FROM users 
                               WHERE email = ? 
                               AND is_active = 1 
                               AND is_blocked = 0 
                               AND deleted_at IS NULL 
                               AND (is_suspended = 0 OR (is_suspended = 1 AND suspension_end < NOW()))");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Login successful - ora recupera i dettagli specifici in base al user_type
            $user_type = $user['user_type'];
            $name = '';
            
            if ($user_type === 'influencer') {
                $stmt_details = $pdo->prepare("SELECT full_name FROM influencers WHERE user_id = ?");
                $stmt_details->execute([$user['id']]);
                $details = $stmt_details->fetch(PDO::FETCH_ASSOC);
                $name = $details['full_name'] ?? '';
            } else if ($user_type === 'brand') {
                $stmt_details = $pdo->prepare("SELECT company_name FROM brands WHERE user_id = ?");
                $stmt_details->execute([$user['id']]);
                $details = $stmt_details->fetch(PDO::FETCH_ASSOC);
                $name = $details['company_name'] ?? '';
            } else {
                // Tipo utente non riconosciuto
                $error = "Tipo utente non valido";
                $user_type = null;
            }
            
            if ($user_type) {
                // MODIFICA: Chiamata semplificata senza remember_me
                login_user($user['id'], $user_type, $name);
                
                // Redirect to appropriate dashboard
                if ($user_type === 'influencer') {
                    header("Location: /influencers/dashboard.php");
                } else {
                    header("Location: /brands/dashboard.php");
                }
                exit();
            }
            
        } else {
            // Controllo più specifico per capire perché il login fallisce
            $stmt_check = $pdo->prepare("SELECT id, is_active, is_blocked, is_suspended, suspension_end, deleted_at 
                                        FROM users WHERE email = ?");
            $stmt_check->execute([$email]);
            $user_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if ($user_check) {
                if ($user_check['deleted_at']) {
                    $error = "Account eliminato. Contatta l'assistenza.";
                } elseif ($user_check['is_blocked']) {
                    $error = "Account bloccato permanentemente. Contatta l'assistenza.";
                } elseif ($user_check['is_suspended'] && $user_check['suspension_end'] && strtotime($user_check['suspension_end']) > time()) {
                    $suspension_date = date('d/m/Y H:i', strtotime($user_check['suspension_end']));
                    $error = "Account sospeso fino al $suspension_date.";
                } elseif (!$user_check['is_active']) {
                    $error = "Account non attivo. Contatta l'assistenza.";
                } else {
                    $error = "Email o password non validi";
                }
            } else {
                $error = "Email o password non validi";
            }
        }
        
    } catch (PDOException $e) {
        $error = "Errore di sistema: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Influencer Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .min-vh-100 {
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 15px;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            padding: 10px;
            font-weight: 500;
        }
        .form-control {
            padding: 12px;
            border-radius: 8px;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .alert {
            border-radius: 8px;
            border: none;
        }
        .links-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .links-container a {
            text-decoration: none;
            color: #6c757d;
            transition: color 0.2s;
        }
        .links-container a:hover {
            color: #007bff;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center min-vh-100 align-items-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Accedi</h2>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['timeout'])): ?>
                            <div class="alert alert-warning">Sessione scaduta. Effettua nuovamente il login.</div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['password_reset'])): ?>
                            <div class="alert alert-success">Password reimpostata con successo! Ora puoi accedere con la nuova password.</div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['logout'])): ?>
                            <div class="alert alert-info">Logout effettuato con successo.</div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       placeholder="Inserisci la tua email">
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required
                                       placeholder="Inserisci la tua password">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 py-2">Accedi</button>
                        </form>
                        
                        <div class="text-center mt-4 links-container">
                            <a href="register.php">Non hai un account? Registrati</a>
                            <a href="forgot-password.php">Password dimenticata?</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>