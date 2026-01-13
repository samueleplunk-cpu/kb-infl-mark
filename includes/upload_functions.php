<?php
/**
 * Funzioni per la gestione dell'upload dei documenti per le pause campagne
 */

/**
 * Gestisce l'upload sicuro dei documenti
 */
function handlePauseDocumentUpload($file, $pause_request_id, $user_id) {
    global $pdo;
    
    // Configurazione upload
    $upload_dir = dirname(__DIR__) . '/uploads/pause_documents/';
    $max_file_size = 10 * 1024 * 1024; // 10MB
    $allowed_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'txt' => 'text/plain'
    ];
    
    // Crea directory se non esiste
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['success' => false, 'error' => 'Impossibile creare la directory di upload'];
        }
    }
    
    // Validazioni di sicurezza
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File troppo grande (limite server)',
            UPLOAD_ERR_FORM_SIZE => 'File troppo grande (limite form)',
            UPLOAD_ERR_PARTIAL => 'Upload interrotto',
            UPLOAD_ERR_NO_FILE => 'Nessun file selezionato',
            UPLOAD_ERR_NO_TMP_DIR => 'Directory temporanea mancante',
            UPLOAD_ERR_CANT_WRITE => 'Errore scrittura su disco',
            UPLOAD_ERR_EXTENSION => 'Upload bloccato da estensione'
        ];
        return ['success' => false, 'error' => $error_messages[$file['error']] ?? 'Errore sconosciuto'];
    }
    
    // Verifica dimensione file
    if ($file['size'] > $max_file_size) {
        return ['success' => false, 'error' => 'File troppo grande (massimo 10MB)'];
    }
    
    // Verifica tipo file
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_type = $file['type'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $real_mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($file_extension, array_keys($allowed_types)) || 
        !in_array($real_mime_type, array_values($allowed_types))) {
        return ['success' => false, 'error' => 'Tipo file non supportato. Usa: PDF, DOC, DOCX, JPG, PNG, TXT'];
    }
    
    // Verifica che il tipo MIME corrisponda all'estensione
    if ($allowed_types[$file_extension] !== $real_mime_type) {
        return ['success' => false, 'error' => 'Tipo file non valido'];
    }
    
    // Genera nome file sicuro
    $safe_filename = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $file['name']);
    $filename = uniqid() . '_' . time() . '_' . $safe_filename;
    $file_path = $upload_dir . $filename;
    
    // Verifica che non esista già un file con lo stesso nome
    if (file_exists($file_path)) {
        $filename = uniqid() . '_' . $filename;
        $file_path = $upload_dir . $filename;
    }
    
    // Sposta file nella directory di destinazione
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => false, 'error' => 'Errore nel salvataggio del file'];
    }
    
    // Imposta i permessi del file
    chmod($file_path, 0644);
    
    // Salva informazioni nel database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO campaign_pause_documents 
            (pause_request_id, filename, original_name, file_path, file_size, file_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $pause_request_id,
            $filename,
            $file['name'],
            $file_path,
            $file['size'],
            $real_mime_type,
            $user_id
        ]);
        
        if ($success) {
            return [
                'success' => true, 
                'file_path' => $file_path,
                'document_id' => $pdo->lastInsertId(),
                'filename' => $filename
            ];
        } else {
            // Cancella file in caso di errore database
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            return ['success' => false, 'error' => 'Errore nel salvataggio nel database'];
        }
        
    } catch (PDOException $e) {
        // Cancella file in caso di errore database
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        error_log("Errore salvataggio documento pausa: " . $e->getMessage());
        return ['success' => false, 'error' => 'Errore di sistema nel salvataggio'];
    }
}

/**
 * Elimina un documento caricato
 */
function deletePauseDocument($document_id, $user_id) {
    global $pdo;
    
    try {
        // Recupera informazioni del documento
        $stmt = $pdo->prepare("
            SELECT cpd.*, cpr.campaign_id, b.user_id as brand_user_id
            FROM campaign_pause_documents cpd 
            JOIN campaign_pause_requests cpr ON cpd.pause_request_id = cpr.id 
            JOIN campaigns c ON cpr.campaign_id = c.id 
            JOIN brands b ON c.brand_id = b.id 
            WHERE cpd.id = ?
        ");
        $stmt->execute([$document_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$document) {
            return ['success' => false, 'error' => 'Documento non trovato'];
        }
        
        // Verifica permessi (solo l'utente che ha caricato il documento o admin)
        if ($document['uploaded_by'] != $user_id && !is_admin_logged_in()) {
            return ['success' => false, 'error' => 'Permessi insufficienti'];
        }
        
        // Elimina file fisico
        if (file_exists($document['file_path'])) {
            if (!unlink($document['file_path'])) {
                return ['success' => false, 'error' => 'Errore nell\'eliminazione del file'];
            }
        }
        
        // Elimina record database
        $stmt = $pdo->prepare("DELETE FROM campaign_pause_documents WHERE id = ?");
        $success = $stmt->execute([$document_id]);
        
        return ['success' => $success];
        
    } catch (PDOException $e) {
        error_log("Errore eliminazione documento pausa: " . $e->getMessage());
        return ['success' => false, 'error' => 'Errore di sistema nell\'eliminazione'];
    }
}

/**
 * Formatta la dimensione del file in formato leggibile
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return "0 Bytes";
    $k = 1024;
    $sizes = ["Bytes", "KB", "MB", "GB"];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . " " . $sizes[$i];
}

/**
 * Ottiene l'icona appropriata per il tipo di file
 */
function getFileIcon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        'pdf' => 'fas fa-file-pdf text-danger',
        'doc' => 'fas fa-file-word text-primary',
        'docx' => 'fas fa-file-word text-primary',
        'jpg' => 'fas fa-file-image text-success',
        'jpeg' => 'fas fa-file-image text-success',
        'png' => 'fas fa-file-image text-success',
        'txt' => 'fas fa-file-alt text-secondary'
    ];
    
    return $icons[$extension] ?? 'fas fa-file text-muted';
}

/**
 * Verifica se un file è un'immagine
 */
function isImageFile($filename) {
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $image_extensions);
}

/**
 * Pulisce la directory degli upload da file orfani
 */
function cleanupOrphanedUploads($older_than_days = 30) {
    $upload_dir = dirname(__DIR__) . '/uploads/pause_documents/';
    
    if (!file_exists($upload_dir)) {
        return ['success' => true, 'deleted' => 0];
    }
    
    try {
        // Trova tutti i file nella directory
        $files = glob($upload_dir . '*');
        $deleted_count = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                // Verifica se il file è più vecchio del limite
                if (filemtime($file) < strtotime("-$older_than_days days")) {
                    // Verifica se il file esiste nel database
                    $filename = basename($file);
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM campaign_pause_documents 
                        WHERE filename = ?
                    ");
                    $stmt->execute([$filename]);
                    $exists_in_db = $stmt->fetchColumn() > 0;
                    
                    // Se non esiste nel database, elimina il file
                    if (!$exists_in_db) {
                        if (unlink($file)) {
                            $deleted_count++;
                        }
                    }
                }
            }
        }
        
        return ['success' => true, 'deleted' => $deleted_count];
        
    } catch (Exception $e) {
        error_log("Errore pulizia upload orfani: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>