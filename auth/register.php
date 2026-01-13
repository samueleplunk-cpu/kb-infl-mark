<?php

// PRIMA config.php (che gestisce sessioni e database)
require_once '../includes/config.php';

// POI functions.php (che usa le sessioni e il database)
require_once '../includes/functions.php';

// DEFINISCI BASE_URL SE NON ESISTE
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Se l'utente è già loggato, reindirizza
if (is_logged_in()) {
    if (is_influencer()) {
        header("Location: /influencers/dashboard.php");
    } else {
        header("Location: /brands/dashboard.php");
    }
    exit();
}

$error = '';
$success = '';
$user_type = $_GET['type'] ?? 'influencer';

$valid_types = ['influencer', 'brand'];
if (!in_array($user_type, $valid_types)) {
    $user_type = 'influencer';
}

// Gestione del form di registrazione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gestisci nome/company_name correttamente
    if ($user_type === 'brand') {
        $name = clean_input($_POST['company_name'] ?? '');
    } else {
        $name = clean_input($_POST['name'] ?? '');
    }
    
    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type_form = clean_input($_POST['user_type'] ?? 'influencer');
    
    // Campi aggiuntivi
    $influencer_type = clean_input($_POST['influencer_type'] ?? '');
    $industry = clean_input($_POST['industry'] ?? '');
    $terms = isset($_POST['terms']) && $_POST['terms'] == 'on';
    
    // Validazione
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Tutti i campi sono obbligatori';
    } elseif (!$terms) {
        $error = 'Devi accettare i termini di servizio';
    } elseif ($password !== $confirm_password) {
        $error = 'Le password non coincidono';
    } elseif (strlen($password) < 6) {
        $error = 'La password deve essere di almeno 6 caratteri';
    } elseif (!validate_email($email)) {
        $error = 'Email non valida';
    } else {
        try {
            // VERIFICA SE L'EMAIL ESISTE GIÀ NELLA TABELLA USERS
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Questa email è già registrata';
            } else {
                // Hash della password
                $password_hash = hash_password($password);
                
                // INIZIA TRANSACTION
                $pdo->beginTransaction();
                
                try {
                    // 1. INSERISCI NELLA TABELLA USERS
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, user_type, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
                    $stmt->execute([$email, $password_hash, $user_type_form]);
                    $user_id = $pdo->lastInsertId();
                    
                    // 2. INSERISCI NELLA TABELLA SPECIFICA (influencers O brands)
                    if ($user_type_form === 'influencer') {
                        $stmt = $pdo->prepare("INSERT INTO influencers (user_id, full_name, niche, created_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$user_id, $name, $influencer_type]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO brands (user_id, company_name, industry, created_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$user_id, $name, $industry]);
                    }
                    
                    // COMMIT TRANSACTION
                    $pdo->commit();
                    
                    $success = 'Registrazione completata con successo! Ora puoi effettuare il login.';
                    
                    // Reindirizza al login dopo 3 secondi
                    header("refresh:3;url=".BASE_URL."/auth/login.php?registered=1");
                    
                } catch (Exception $e) {
                    // ROLLBACK IN CASO DI ERRORE
                    $pdo->rollBack();
                    throw $e;
                }
            }
            
        } catch (PDOException $e) {
            $error = 'Errore durante la registrazione: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4>Registrati come <?php echo ucfirst($user_type); ?></h4>
                <p class="mb-0">Compila il form per creare il tuo account</p>
            </div>
            <div class="card-body">
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="user_type" value="<?php echo $user_type; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">
                                    <?php echo $user_type === 'brand' ? 'Nome Azienda *' : 'Nome Completo *'; ?>
                                </label>
                                <input type="text" class="form-control" id="name" name="<?php echo $user_type === 'brand' ? 'company_name' : 'name'; ?>" 
       value="<?php echo isset($_POST[$user_type === 'brand' ? 'company_name' : 'name']) ? htmlspecialchars($_POST[$user_type === 'brand' ? 'company_name' : 'name']) : ''; ?>" 
       required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       minlength="6" required>
                                <div class="form-text">Minimo 6 caratteri</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Conferma Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       minlength="6" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campi specifici per Influencer -->
<?php if ($user_type === 'influencer'): ?>
<div class="mb-3">
    <label for="influencer_type" class="form-label">Tipologia di Influencer *</label>
    <select class="form-select" id="influencer_type" name="influencer_type" required>
        <option value="">Seleziona la tua tipologia</option>
        <?php
        // Recupera le categorie attive dal database
        try {
            $categories_stmt = $pdo->prepare("
                SELECT id, name 
                FROM categories 
                WHERE is_active = TRUE 
                ORDER BY display_order ASC, name ASC
            ");
            $categories_stmt->execute();
            $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($categories as $category) {
                $selected = (isset($_POST['influencer_type']) && $_POST['influencer_type'] === $category['name']) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($category['name']) . '" ' . $selected . '>' . $category['name'] . '</option>';
            }
        } catch (PDOException $e) {
            // Fallback a categorie predefinite in caso di errore
            $fallback_categories = [
                'Fashion', 'Lifestyle', 'Beauty & Makeup', 'Food', 'Travel', 
                'Gaming', 'Fitness & Wellness', 'Entertainment', 'Tech', 
                'Finance & Business', 'Pet', 'Education'
            ];
            
            foreach ($fallback_categories as $cat) {
                $selected = (isset($_POST['influencer_type']) && $_POST['influencer_type'] === $cat) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($cat) . '" ' . $selected . '>' . $cat . '</option>';
            }
        }
        ?>
    </select>
</div>
<?php endif; ?>
                    
                    <!-- Campi specifici per Brand -->
<?php if ($user_type === 'brand'): ?>
<div class="mb-3">
    <label for="industry" class="form-label">Settore *</label>
    <select class="form-select" id="industry" name="industry" required>
        <option value="">Seleziona il settore</option>
        <?php
        // Recupera le categorie attive dal database
        try {
            $categories_stmt = $pdo->prepare("
                SELECT id, name 
                FROM categories 
                WHERE is_active = TRUE 
                ORDER BY display_order ASC, name ASC
            ");
            $categories_stmt->execute();
            $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($categories as $category) {
                $selected = (isset($_POST['industry']) && $_POST['industry'] === $category['name']) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($category['name']) . '" ' . $selected . '>' . $category['name'] . '</option>';
            }
        } catch (PDOException $e) {
            // Fallback a categorie predefinite in caso di errore
            $fallback_categories = [
                'Fashion', 'Lifestyle', 'Beauty & Makeup', 'Food', 'Travel', 
                'Gaming', 'Fitness & Wellness', 'Entertainment', 'Tech', 
                'Finance & Business', 'Pet', 'Education'
            ];
            
            foreach ($fallback_categories as $cat) {
                $selected = (isset($_POST['industry']) && $_POST['industry'] === $cat) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($cat) . '" ' . $selected . '>' . $cat . '</option>';
            }
        }
        ?>
    </select>
</div>
<?php endif; ?>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="terms">
                            Accetto i <a href="<?php echo BASE_URL; ?>/terms.php" target="_blank">Termini di Servizio</a> 
                            e l'<a href="<?php echo BASE_URL; ?>/privacy.php" target="_blank">Informativa Privacy</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Crea Account</button>
                </form>
                
                <div class="mt-4 text-center">
                    <p>Hai già un account? 
                        <a href="<?php echo BASE_URL; ?>/auth/login.php" class="text-decoration-none">
                            Accedi qui
                        </a>
                    </p>
                    
                    <div class="mt-3">
                        <p>Oppure registrati come:</p>
                        <div class="d-flex gap-2 justify-content-center" id="switcher-buttons">
                            <?php if ($user_type === 'influencer'): ?>
                                <a href="?type=brand" class="btn btn-outline-success">Brand</a>
                            <?php else: ?>
                                <a href="?type=influencer" class="btn btn-outline-primary">Influencer</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Funzione per aggiornare i pulsanti dello switcher
    function updateSwitcherButtons(currentType) {
        const switcherContainer = $('#switcher-buttons');
        
        if (currentType === 'influencer') {
            // Se siamo su influencer, mostra solo brand
            switcherContainer.html('<a href="?type=brand" class="btn btn-outline-success">Brand</a>');
        } else {
            // Se siamo su brand, mostra solo influencer
            switcherContainer.html('<a href="?type=influencer" class="btn btn-outline-primary">Influencer</a>');
        }
    }
    
    // Inizializza con il tipo corrente
    updateSwitcherButtons('<?php echo $user_type; ?>');
    
    // Gestisce il click sui pulsanti (per mantenere la funzionalità)
    $(document).on('click', '#switcher-buttons a', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        window.location.href = url;
    });
});
</script>