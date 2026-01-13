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

// Verifica che l'utente sia un brand
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'brand') {
    die("Accesso negato: Questa area è riservata ai brand.");
}

// =============================================
// VERIFICA PARAMETRI
// =============================================
if (!isset($_POST['influencer_id']) || !is_numeric($_POST['influencer_id'])) {
    die("ID influencer non valido");
}

// =============================================
// RECUPERO BRAND_ID
// =============================================
$brand_id = null;
$stmt = $pdo->prepare("SELECT id FROM brands WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$brand = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$brand) {
    die("Profilo brand non trovato. Completa prima il profilo brand.");
}
$brand_id = $brand['id'];

// =============================================
// ELABORAZIONE PARAMETRI
// =============================================
$influencer_id = intval($_POST['influencer_id']);
$campaign_id = !empty($_POST['campaign_id']) ? intval($_POST['campaign_id']) : null;

// Gestione messaggio: priorità a custom_message, poi initial_message, poi default
if (!empty($_POST['custom_message'])) {
    $initial_message = trim($_POST['custom_message']);
} elseif (!empty($_POST['initial_message'])) {
    $initial_message = trim($_POST['initial_message']);
} else {
    $initial_message = "Ciao, sono interessato a collaborare con te!";
}

// Validazione del messaggio
if (empty($initial_message)) {
    die("Il messaggio non può essere vuoto.");
}

if (strlen($initial_message) > 1000) {
    die("Il messaggio è troppo lungo (max 1000 caratteri).");
}

// =============================================
// VERIFICA ESISTENZA INFLUENCER
// =============================================
$stmt = $pdo->prepare("SELECT full_name FROM influencers WHERE id = ?");
$stmt->execute([$influencer_id]);
$influencer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$influencer) {
    die("Influencer non trovato");
}

// =============================================
// GESTIONE CONVERSAZIONE ESISTENTE O NUOVA
// =============================================
$conversation_id = null;

// Se è stato passato un ID conversazione esistente (da pulsante "Nuovo Messaggio")
if (!empty($_POST['existing_conversation_id']) && is_numeric($_POST['existing_conversation_id'])) {
    $existing_conversation_id = intval($_POST['existing_conversation_id']);
    
    // Verifica che la conversazione esista e appartenga al brand
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
                VALUES (?, ?, 'brand', ?, NOW())
            ");
            $stmt->execute([$conversation_id, $brand_id, $initial_message]);
            
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
        // Se la conversazione non esiste o non appartiene al brand, creane una nuova
        $conversation_id = startConversation($pdo, $brand_id, $influencer_id, $campaign_id, $initial_message);
    }
} else {
    // Caso standard: crea nuova conversazione o recupera esistente
    $conversation_id = startConversation($pdo, $brand_id, $influencer_id, $campaign_id, $initial_message);
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
?>