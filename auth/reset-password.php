<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Percorso assoluto a config
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Config file not found");
}
require_once $config_file;

// Se già loggato, reindirizza alla dashboard
if (is_logged_in()) {
    if (is_influencer()) {
        header("Location: /influencers/dashboard.php");
    } else {
        header("Location: /brands/dashboard.php");
    }
    exit();
}

$message = '';
$error = '';
$valid_token = false;
$token = $_GET['token'] ?? '';

// Verifica il token
if ($token) {
    try {
        $stmt = $pdo->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        $reset_request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reset_request) {
            if ($reset_request['used'] == 1) {
                $error = "Questo link di reset è già stato utilizzato.";
            } elseif (strtotime($reset_request['expires_at']) < time()) {
                $error = "Questo link di reset è scaduto. Richiedine uno nuovo.";
            } else {
                $valid_token = true;
                $email = $reset_request['email'];
            }
        } else {
            $error = "Link di reset non valido.";
        }
    } catch (PDOException $e) {
        $error = "Errore di sistema: " . $e->getMessage();
    }
} else {
    $error = "Nessun token di reset specificato.";
}

// Gestione del form di reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Inserisci e conferma la nuova password";
    } elseif (strlen($password) < 8) {
        $error = "La password deve essere di almeno 8 caratteri";
    } elseif ($password !== $confirm_password) {
        $error = "Le password non coincidono";
    } else {
        try {
            // Hash della nuova password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Aggiorna la password dell'utente
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
            $stmt->execute([$hashed_password, $email]);
            
            // Marca il token come utilizzato
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            // Invia email di conferma
            $email_functions_file = dirname(__DIR__) . '/includes/email_functions.php';
            if (file_exists($email_functions_file)) {
                require_once $email_functions_file;
                send_password_changed_email($email);
            }
            
            $message = "Password reimpostata con successo! <a href='login.php'>Accedi ora</a>";
            $valid_token = false; // Disabilita il form dopo il successo
            
        } catch (PDOException $e) {
            $error = "Errore di sistema: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reimposta Password - Influencer Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center min-vh-100 align-items-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Reimposta Password</h2>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($valid_token): ?>
                            <p class="text-muted mb-4">Inserisci la tua nuova password.</p>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Nuova Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required 
                                           minlength="8" placeholder="Almeno 8 caratteri">
                                    <div class="form-text">La password deve essere di almeno 8 caratteri.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Conferma Nuova Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">Reimposta Password</button>
                            </form>
                        <?php else: ?>
                            <div class="text-center">
                                <a href="forgot-password.php" class="btn btn-primary">Richiedi Nuovo Link</a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-3">
                            <a href="login.php">Torna al Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>