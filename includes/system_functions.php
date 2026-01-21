<?php
/**
 * Funzioni per la gestione delle impostazioni di sistema
 */

/**
 * Ottiene le impostazioni di sistema dal database
 */
function get_system_settings() {
    global $pdo;
    
    $settings = [
        'timezone' => 'Europe/Rome',
        'date_format' => 'd/m/Y',
        'time_format' => 'H:i',
        'auto_sync_time' => '0'
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('timezone', 'date_format', 'time_format', 'auto_sync_time')");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($results as $key => $value) {
            $settings[$key] = $value;
        }
    } catch (PDOException $e) {
        error_log("Errore recupero impostazioni sistema: " . $e->getMessage());
    }
    
    return $settings;
}

/**
 * Salva le impostazioni di sistema nel database
 */
function save_system_settings($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $settings = [
            'timezone' => $data['timezone'] ?? 'Europe/Rome',
            'date_format' => $data['date_format'] ?? 'd/m/Y',
            'time_format' => $data['time_format'] ?? 'H:i',
            'auto_sync_time' => isset($data['auto_sync_time']) ? '1' : '0'
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO site_settings (setting_key, setting_value, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    setting_value = VALUES(setting_value),
                    updated_at = NOW()
            ");
            $stmt->execute([$key, $value]);
        }
        
        $pdo->commit();
        
        // Applica il timezone immediatamente
        apply_system_timezone($settings['timezone']);
        
        return [
            'success' => true,
            'message' => 'Impostazioni di sistema salvate con successo!'
        ];
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Errore salvataggio impostazioni sistema: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Errore nel salvataggio delle impostazioni di sistema.'
        ];
    }
}

/**
 * Applica il timezone in tutta la piattaforma
 */
function apply_system_timezone($timezone = null) {
    if ($timezone === null) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'timezone' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $timezone = $result ? $result['setting_value'] : 'Europe/Rome';
        } catch (PDOException $e) {
            error_log("Errore recupero timezone: " . $e->getMessage());
            $timezone = 'Europe/Rome';
        }
    }
    
    try {
        // Imposta il timezone per PHP
        date_default_timezone_set($timezone);
        
        // Se c'è una connessione al database, imposta anche il timezone MySQL
        global $pdo;
        if ($pdo) {
            $offset = (new DateTime('now', new DateTimeZone($timezone)))->format('P');
            $stmt = $pdo->prepare("SET time_zone = ?");
            $stmt->execute([$offset]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Errore applicazione timezone '$timezone': " . $e->getMessage());
        // Fallback al timezone di default
        date_default_timezone_set('Europe/Rome');
        return false;
    }
}

/**
 * Ottiene il formato data/ora configurato
 */
function get_system_datetime_format($type = 'both') {
    $settings = get_system_settings();
    
    switch ($type) {
        case 'date':
            return $settings['date_format'];
        case 'time':
            return $settings['time_format'];
        case 'both':
        default:
            return $settings['date_format'] . ' ' . $settings['time_format'];
    }
}

/**
 * Formatta una data secondo le impostazioni di sistema
 */
function format_system_datetime($datetime_string, $type = 'both') {
    $timezone = get_system_settings()['timezone'];
    $format = get_system_datetime_format($type);
    
    try {
        $date = new DateTime($datetime_string);
        $date->setTimezone(new DateTimeZone($timezone));
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Errore formattazione data: " . $e->getMessage());
        return $datetime_string;
    }
}

/**
 * Ottiene la data/ora corrente del sistema
 */
function get_system_datetime() {
    $timezone = get_system_settings()['timezone'];
    $format = get_system_datetime_format('both');
    
    try {
        $date = new DateTime('now', new DateTimeZone($timezone));
        return [
            'formatted' => $date->format($format),
            'timestamp' => $date->getTimestamp(),
            'timezone' => $timezone,
            'offset' => $date->format('P')
        ];
    } catch (Exception $e) {
        error_log("Errore recupero data sistema: " . $e->getMessage());
        return date($format);
    }
}