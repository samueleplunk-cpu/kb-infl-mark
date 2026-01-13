<?php
// influencers/create-profile.php - VERSIONE CORRETTA CON RATING SENZA 'S'

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_functions.php';

// Verifica che l'utente sia autenticato
if (!is_logged_in()) {
    header('Location: /auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Verifica che l'utente non abbia già un profilo influencer
try {
    $check_sql = "SELECT id FROM influencers WHERE user_id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$user_id]);
    
    if ($check_stmt->rowCount() > 0) {
        $_SESSION['error'] = "Hai già un profilo influencer!";
        header('Location: /influencers/dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Error checking existing profile: " . $e->getMessage());
    $error = "Errore di sistema. Riprova più tardi.";
}

$error = '';
$success = '';

// Gestione del form di creazione profilo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validazione e sanitizzazione dei dati con campi REALI del DB
    $full_name = trim($_POST['full_name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $niche = trim($_POST['niche'] ?? '');
    $instagram_handle = trim($_POST['instagram_handle'] ?? '');
    $tiktok_handle = trim($_POST['tiktok_handle'] ?? '');
    $youtube_handle = trim($_POST['youtube_handle'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $rate = floatval($_POST['rate'] ?? 0);
    
    // Validazione base
    if (empty($full_name) || empty($bio) || empty($niche)) {
        $error = "Nome, Bio e Niche sono campi obbligatori!";
    } elseif ($rate < 0) {
        $error = "La tariffa non può essere negativa!";
    } else {
        try {
            // Gestione upload immagine profilo (stessa logica di edit-profile.php)
            $profile_image = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/profiles/';
                
                // Verifica che la cartella esista
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    // Verifica dimensione file (max 5MB)
                    if ($_FILES['profile_image']['size'] > 5 * 1024 * 1024) {
                        $error = "L'immagine è troppo grande! Dimensione massima: 5MB";
                    } else {
                        // Genera nome file univoco
                        $filename = uniqid() . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                            $profile_image = 'profiles/' . $filename;
                        } else {
                            $error = "Errore nel caricamento dell'immagine!";
                        }
                    }
                } else {
                    $error = "Formato immagine non supportato! Usa JPG, PNG o GIF.";
                }
            }
            
            if (empty($error)) {
                // INSERIMENTO CORRETTO con RATING SENZA 'S'
                $sql = "INSERT INTO influencers (
                            user_id, full_name, bio, niche, 
                            instagram_handle, tiktok_handle, youtube_handle, 
                            website, rate, profile_image, profile_views, rating,
                            created_at, updated_at
                        ) VALUES (
                            :user_id, :full_name, :bio, :niche,
                            :instagram_handle, :tiktok_handle, :youtube_handle,
                            :website, :rate, :profile_image, 0, 0,
                            NOW(), NOW()
                        )";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    ':user_id' => $user_id,
                    ':full_name' => $full_name,
                    ':bio' => $bio,
                    ':niche' => $niche,
                    ':instagram_handle' => $instagram_handle,
                    ':tiktok_handle' => $tiktok_handle,
                    ':youtube_handle' => $youtube_handle,
                    ':website' => $website,
                    ':rate' => $rate,
                    ':profile_image' => $profile_image
                ]);
                
                if ($result) {
                    // Imposta user_type come influencer dopo la creazione profilo
                    $_SESSION['user_type'] = 'influencer';
                    $_SESSION['success'] = "Profilo influencer creato con successo!";
                    header('Location: /influencers/dashboard.php?success=profile_created');
                    exit();
                } else {
                    $error = "Errore nella creazione del profilo";
                }
            }
        } catch (PDOException $e) {
            error_log("Database error in create-profile: " . $e->getMessage());
            $error = "Errore di sistema durante la creazione del profilo. Riprova più tardi.";
        } catch (Exception $e) {
            error_log("General error in create-profile: " . $e->getMessage());
            $error = "Errore: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crea Profilo Influencer - Influencer Marketplace</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 800px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-error {
            background-color: #fee;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-success {
            background-color: #e8f5e8;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        input[type="text"],
        input[type="number"],
        textarea,
        input[type="file"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            text-decoration: none;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        .form-help {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .social-handles {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .social-handles h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Crea Profilo Influencer</h1>
            <p>Completa il tuo profilo per iniziare a collaborare con i brand</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <!-- Informazioni Base -->
            <div class="form-group">
                <label for="full_name">Nome Completo *</label>
                <input type="text" id="full_name" name="full_name" required 
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                       placeholder="Il tuo nome e cognome">
            </div>

            <div class="form-group">
                <label for="bio">Biografia *</label>
                <textarea id="bio" name="bio" required 
                          placeholder="Racconta la tua storia, i tuoi interessi, la tua expertise..."><?php echo htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
                <div class="form-help">Descriviti in modo autentico per attirare i brand giusti</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="niche">Niche *</label>
                    <input type="text" id="niche" name="niche" required 
                           value="<?php echo htmlspecialchars($_POST['niche'] ?? ''); ?>" 
                           placeholder="Es: Beauty, Gaming, Travel, Fashion...">
                </div>

                <div class="form-group">
                    <label for="rate">Tariffa (€) *</label>
                    <input type="number" id="rate" name="rate" step="0.01" min="0" required 
                           value="<?php echo htmlspecialchars($_POST['rate'] ?? ''); ?>"
                           placeholder="0.00">
                    <div class="form-help">Tariffa per collaborazione</div>
                </div>
            </div>

            <!-- Social Handles -->
            <div class="social-handles">
                <h3>📱 I Tuoi Social Media</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="instagram_handle">Instagram</label>
                        <input type="text" id="instagram_handle" name="instagram_handle" 
                               value="<?php echo htmlspecialchars($_POST['instagram_handle'] ?? ''); ?>" 
                               placeholder="@tuonome">
                    </div>
                    <div class="form-group">
                        <label for="tiktok_handle">TikTok</label>
                        <input type="text" id="tiktok_handle" name="tiktok_handle" 
                               value="<?php echo htmlspecialchars($_POST['tiktok_handle'] ?? ''); ?>" 
                               placeholder="@tuonome">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="youtube_handle">YouTube</label>
                        <input type="text" id="youtube_handle" name="youtube_handle" 
                               value="<?php echo htmlspecialchars($_POST['youtube_handle'] ?? ''); ?>" 
                               placeholder="@tuonome o Channel Name">
                    </div>
                    <div class="form-group">
                        <label for="website">Sito Web/Blog</label>
                        <input type="text" id="website" name="website" 
                               value="<?php echo htmlspecialchars($_POST['website'] ?? ''); ?>" 
                               placeholder="https://tuosito.com">
                    </div>
                </div>
            </div>

            <!-- Immagine Profilo -->
            <div class="form-group">
                <label for="profile_image">Immagine Profilo</label>
                <input type="file" id="profile_image" name="profile_image" 
                       accept="image/jpeg,image/png,image/gif">
                <div class="form-help">Formati supportati: JPG, PNG, GIF (max 5MB). Immagine quadrata consigliata.</div>
            </div>

            <button type="submit" class="btn btn-block">🚀 Crea Profilo Influencer</button>
        </form>

        <div class="back-link">
            <a href="/influencers/dashboard.php">← Torna alla Dashboard</a>
        </div>
    </div>
</body>
</html>