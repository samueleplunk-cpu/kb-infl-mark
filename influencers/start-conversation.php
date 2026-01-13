<?php
// =============================================
// CONFIGURAZIONE ERRORI E SICUREZZA
// =============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// =============================================
// INCLUSIONE CONFIG CON PERCORSO ASSOLUTO
// =============================================
$config_file = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($config_file)) {
    die("Errore: File di configurazione non trovato in: " . $config_file);
}
require_once $config_file;

// =============================================
// INCLUSIONE FUNZIONI CON PERCORSO ASSOLUTO
// =============================================
$functions_file = dirname(__DIR__) . '/includes/functions.php';
if (!file_exists($functions_file)) {
    die("Errore: File funzioni non trovato in: " . $functions_file);
}
require_once $functions_file;

// =============================================
// VERIFICA AUTENTICAZIONE UTENTE
// =============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

// Verifica che l'utente sia un influencer
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'influencer') {
    die("Accesso negato: Questa area è riservata agli influencer.");
}

// =============================================
// VERIFICA PARAMETRI
// =============================================
if (!isset($_POST['brand_id']) || !is_numeric($_POST['brand_id'])) {
    die("ID brand non valido");
}

// =============================================
// RECUPERO INFLUENCER_ID
// =============================================
$influencer_id = null;
$stmt = $pdo->prepare("SELECT id FROM influencers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$influencer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$influencer) {
    die("Profilo influencer non trovato. Completa prima il profilo influencer.");
}
$influencer_id = $influencer['id'];

// =============================================
// ELABORAZIONE PARAMETRI
// =============================================
$brand_id = intval($_POST['brand_id']);
$campaign_id = !empty($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null;

// Gestione messaggio: priorità a custom_message, poi initial_message, poi default
if (!empty($_POST['custom_message'])) {
    $initial_message = trim($_POST['custom_message']);
} elseif (!empty($_POST['initial_message'])) {
    $initial_message = trim($_POST['initial_message']);
} else {
    $initial_message = "Ciao, sono interessato a collaborare con voi!";
}

// Validazione del messaggio
if (empty($initial_message)) {
    die("Il messaggio non può essere vuoto.");
}

if (strlen($initial_message) > 1000) {
    die("Il messaggio è troppo lungo (max 1000 caratteri).");
}

// =============================================
// VERIFICA ESISTENZA BRAND
// =============================================
$stmt = $pdo->prepare("SELECT company_name FROM brands WHERE id = ?");
$stmt->execute([$brand_id]);
$brand = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$brand) {
    die("Brand non trovato");
}

// =============================================
// GESTIONE CONVERSAZIONE ESISTENTE O NUOVA
// =============================================
$conversation_id = null;

// Se è stato passato un ID conversazione esistente (da pulsante "Nuovo Messaggio")
if (!empty($_POST['existing_conversation_id']) && is_numeric($_POST['existing_conversation_id'])) {
    $existing_conversation_id = intval($_POST['existing_conversation_id']);
    
    // Verifica che la conversazione esista e appartenga all'influencer
    $stmt = $pdo->prepare("
        SELECT id FROM conversations 
        WHERE id = ? AND brand_id = ? AND influencer_id = ?
    ");
    $stmt->execute([$existing_conversation_id, $brand_id, $influencer_id]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conversation) {
        // Usa la conversazione esistente
        $conversation_id = $conversation['id'];
        
        // Aggiungi il nuovo messaggio alla conversazione esistente
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (conversation_id, sender_id, sender_type, message, sent_at) 
                VALUES (?, ?, 'influencer', ?, NOW())
            ");
            $stmt->execute([$conversation_id, $influencer_id, $initial_message]);
            
            // Aggiorna la data di modifica della conversazione
            $stmt = $pdo->prepare("
                UPDATE conversations SET updated_at = NOW() WHERE id = ?
            ");
            $stmt->execute([$conversation_id]);
            
        } catch (PDOException $e) {
            error_log("Errore aggiunta messaggio a conversazione esistente: " . $e->getMessage());
            die("Errore nell'aggiunta del messaggio alla conversazione");
        }
    } else {
        // Se la conversazione non esiste o non appartiene all'influencer, creane una nuova
        $conversation_id = startConversationInfluencer($pdo, $brand_id, $influencer_id, $campaign_id, $initial_message);
    }
} else {
    // Caso standard: crea nuova conversazione o recupera esistente
    $conversation_id = startConversationInfluencer($pdo, $brand_id, $influencer_id, $campaign_id, $initial_message);
}

// =============================================
// REINDIRIZZAMENTO
// =============================================
if ($conversation_id) {
    // Reindirizza alla conversazione
    header("Location: messages/conversation.php?id=" . $conversation_id);
    exit();
} else {
    die("Errore nella gestione della conversazione");
}

// =============================================
// FUNZIONE PER AVVIARE CONVERSAZIONE (INFLUENCER)
// =============================================
function startConversationInfluencer($pdo, $brand_id, $influencer_id, $campaign_id = null, $initial_message = "") {
    // Prima verifica se esiste già una conversazione tra questo brand e influencer senza campaign_id
    $stmt = $pdo->prepare("
        SELECT id FROM conversations 
        WHERE brand_id = ? AND influencer_id = ? AND campaign_id IS NULL
    ");
    $stmt->execute([$brand_id, $influencer_id]);
    $existing_conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_conversation) {
        // Usa la conversazione esistente
        $conversation_id = $existing_conversation['id'];
        
        // Aggiungi il messaggio iniziale
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (conversation_id, sender_id, sender_type, message, sent_at) 
                VALUES (?, ?, 'influencer', ?, NOW())
            ");
            $stmt->execute([$conversation_id, $influencer_id, $initial_message]);
            
            // Aggiorna la data di modifica della conversazione
            $stmt = $pdo->prepare("
                UPDATE conversations SET updated_at = NOW() WHERE id = ?
            ");
            $stmt->execute([$conversation_id]);
            
            return $conversation_id;
            
        } catch (PDOException $e) {
            error_log("Errore nell'aggiunta del messaggio: " . $e->getMessage());
            return false;
        }
    } else {
        // Crea una nuova conversazione
        try {
            $pdo->beginTransaction();
            
            // Inserisci la conversazione
            $stmt = $pdo->prepare("
                INSERT INTO conversations (brand_id, influencer_id, campaign_id, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$brand_id, $influencer_id, $campaign_id]);
            $conversation_id = $pdo->lastInsertId();
            
            // Inserisci il messaggio iniziale
            $stmt = $pdo->prepare("
                INSERT INTO messages (conversation_id, sender_id, sender_type, message, sent_at) 
                VALUES (?, ?, 'influencer', ?, NOW())
            ");
            $stmt->execute([$conversation_id, $influencer_id, $initial_message]);
            
            $pdo->commit();
            return $conversation_id;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Errore nella creazione della conversazione: " . $e->getMessage());
            return false;
        }
    }
}
?>