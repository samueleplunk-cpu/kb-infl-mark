<?php
// Funzioni per la gestione delle pagine interne

/**
 * Ottiene tutte le pagine
 */
function getAllPages() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, title, slug, content, meta_title, meta_description, is_active, 
                   created_at, updated_at
            FROM internal_pages 
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Errore nel recupero pagine: " . $e->getMessage());
        return [];
    }
}

/**
 * Ottiene una pagina specifica
 */
function getPageById($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM internal_pages 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Errore nel recupero pagina: " . $e->getMessage());
        return false;
    }
}

/**
 * Ottiene una pagina per slug
 */
function getPageBySlug($slug) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM internal_pages 
            WHERE slug = ? AND is_active = 1
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Errore nel recupero pagina per slug: " . $e->getMessage());
        return false;
    }
}

/**
 * Controlla se uno slug esiste già
 */
function slugExists($slug, $exclude_id = null) {
    global $pdo;
    
    try {
        if ($exclude_id) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM internal_pages 
                WHERE slug = ? AND id != ?
            ");
            $stmt->execute([$slug, $exclude_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM internal_pages 
                WHERE slug = ?
            ");
            $stmt->execute([$slug]);
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Errore nel controllo slug: " . $e->getMessage());
        return false;
    }
}

/**
 * Gestisce l'aggiunta di una nuova pagina
 */
function handleAddPage() {
    global $pdo;
    
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validazione
    if (empty($title) || empty($slug) || empty($content)) {
        $_SESSION['error_message'] = "Tutti i campi obbligatori devono essere compilati.";
        return;
    }
    
    // Validazione slug
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        $_SESSION['error_message'] = "Lo slug può contenere solo lettere minuscole, numeri e trattini.";
        return;
    }
    
    // Controlla se lo slug esiste già
    if (slugExists($slug)) {
        $_SESSION['error_message'] = "Lo slug \"$slug\" è già in uso. Scegline un altro.";
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO internal_pages 
            (title, slug, content, meta_title, meta_description, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $title,
            $slug,
            $content,
            $meta_title,
            $meta_description,
            $is_active
        ]);
        
        $_SESSION['success_message'] = "Pagina creata con successo!";
        header("Location: internal-pages.php");
        exit();
        
    } catch (Exception $e) {
        error_log("Errore nella creazione pagina: " . $e->getMessage());
        $_SESSION['error_message'] = "Errore nella creazione della pagina. Riprova.";
    }
}

/**
 * Gestisce la modifica di una pagina
 */
function handleEditPage() {
    global $pdo;
    
    $page_id = $_POST['page_id'] ?? 0;
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validazione
    if (empty($page_id) || empty($title) || empty($slug) || empty($content)) {
        $_SESSION['error_message'] = "Tutti i campi obbligatori devono essere compilati.";
        return;
    }
    
    // Validazione slug
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        $_SESSION['error_message'] = "Lo slug può contenere solo lettere minuscole, numeri e trattini.";
        return;
    }
    
    // Controlla se lo slug esiste già (escludendo la pagina corrente)
    if (slugExists($slug, $page_id)) {
        $_SESSION['error_message'] = "Lo slug \"$slug\" è già in uso. Scegline un altro.";
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE internal_pages 
            SET title = ?, slug = ?, content = ?, meta_title = ?, 
                meta_description = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $title,
            $slug,
            $content,
            $meta_title,
            $meta_description,
            $is_active,
            $page_id
        ]);
        
        $_SESSION['success_message'] = "Pagina aggiornata con successo!";
        header("Location: internal-pages.php");
        exit();
        
    } catch (Exception $e) {
        error_log("Errore nell'aggiornamento pagina: " . $e->getMessage());
        $_SESSION['error_message'] = "Errore nell'aggiornamento della pagina. Riprova.";
    }
}

/**
 * Gestisce l'eliminazione di una pagina
 */
function handleDeletePage() {
    global $pdo;
    
    $page_id = $_POST['page_id'] ?? 0;
    
    if (empty($page_id)) {
        $_SESSION['error_message'] = "ID pagina non valido.";
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM internal_pages WHERE id = ?");
        $stmt->execute([$page_id]);
        
        $_SESSION['success_message'] = "Pagina eliminata con successo!";
        header("Location: internal-pages.php");
        exit();
        
    } catch (Exception $e) {
        error_log("Errore nell'eliminazione pagina: " . $e->getMessage());
        $_SESSION['error_message'] = "Errore nell'eliminazione della pagina. Riprova.";
    }
}

/**
 * Gestisce l'attivazione/disattivazione di una pagina
 */
function handleTogglePageStatus() {
    global $pdo;
    
    $page_id = $_POST['page_id'] ?? 0;
    $status_action = $_POST['status_action'] ?? '';
    
    if (empty($page_id) || !in_array($status_action, ['activate', 'deactivate'])) {
        $_SESSION['error_message'] = "Parametri non validi.";
        return;
    }
    
    $is_active = $status_action === 'activate' ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE internal_pages 
            SET is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$is_active, $page_id]);
        
        $action_text = $status_action === 'activate' ? 'attivata' : 'disattivata';
        $_SESSION['success_message'] = "Pagina {$action_text} con successo!";
        header("Location: internal-pages.php");
        exit();
        
    } catch (Exception $e) {
        error_log("Errore nel cambio stato pagina: " . $e->getMessage());
        $_SESSION['error_message'] = "Errore nel cambio stato della pagina. Riprova.";
    }
}
?>