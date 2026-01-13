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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = "Inserisci la tua email";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email non valida";
    } else {
        try {
            // Verifica se l'email esiste nel sistema
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Genera token sicuro
                $token = bin2hex(random_bytes(50));
                $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Invalida eventuali token precedenti per questa email
                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ?");
                $stmt->execute([$email]);
                
                // Salva il nuovo token
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expires_at]);
                
                // Invia email di reset
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/auth/reset-password.php?token=" . $token;
                
                // Includi le funzioni email
                $email_functions_file = dirname(__DIR__) . '/includes/email_functions.php';
                if (file_exists($email_functions_file)) {
                    require_once $email_functions_file;
                    if (send_password_reset_email($email, $reset_link)) {
                        $message = "Ti abbiamo inviato un'email con le istruzioni per reimpostare la password.";
                    } else {
                        $error = "Errore nell'invio dell'email. Riprova più tardi.";
                    }
                } else {
                    // Fallback: mostra il link (per sviluppo)
                    $message = "Link di reset: <a href='$reset_link'>$reset_link</a> (in sviluppo - normalmente verrebbe inviato via email)";
                }
            }
            
            // Mostra sempre lo stesso messaggio per privacy
            if (!$error) {
                $message = "Se l'email esiste nel nostro sistema, ti abbiamo inviato le istruzioni per reimpostare la password.";
            }
            
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
    <title>Recupera Password - Influencer Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center min-vh-100 align-items-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Recupera Password</h2>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <p class="text-muted mb-4">Inserisci la tua email e ti invieremo le istruzioni per reimpostare la password.</p>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Invia Istruzioni</button>
                        </form>
                        
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