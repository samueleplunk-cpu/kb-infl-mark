<?php
/**
 * Funzioni per la gestione dei social network dal database
 */

// =============================================
// INIZIALIZZAZIONE IMMEDIATA DEL SISTEMA
// =============================================

/**
 * Verifica e crea IMMEDIATAMENTE tutte le colonne mancanti
 */
function initialize_social_platforms_immediately() {
    global $pdo;
    
    // Verifica se la tabella influencers esiste
    try {
        $stmt = $pdo->query("SELECT 1 FROM influencers LIMIT 1");
    } catch (PDOException $e) {
        error_log("❌ Tabella influencers non trovata: " . $e->getMessage());
        return;
    }
    
    // Ottieni tutte le piattaforme social
    $social_networks = get_all_social_networks();
    
    if (empty($social_networks)) {
        error_log("📝 Nessuna piattaforma social trovata nel database");
        return;
    }
    
    error_log("🔧 Inizializzazione IMMEDIATA piattaforme social...");
    
    foreach ($social_networks as $network) {
        $column_name = $network['slug'] . '_handle';
        
        // Verifica se la colonna esiste
        $column_exists = false;
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'influencers' 
                AND COLUMN_NAME = ?
            ");
            $stmt->execute([$column_name]);
            $column_exists = (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("❌ Errore verifica colonna {$column_name}: " . $e->getMessage());
        }
        
        if (!$column_exists) {
            // Crea la colonna
            try {
                $sql = "ALTER TABLE influencers ADD COLUMN `{$column_name}` VARCHAR(255) NULL DEFAULT NULL";
                $pdo->exec($sql);
                error_log("🚀 COLONNA CREATA: {$column_name} per {$network['name']}");
            } catch (PDOException $e) {
                error_log("💥 ERRORE creazione colonna {$column_name}: " . $e->getMessage());
                
                // Tentativo alternativo
                try {
                    $sql = "ALTER TABLE influencers ADD `{$column_name}` TEXT NULL";
                    $pdo->exec($sql);
                    error_log("🚀 COLONNA CREATA (alternativa): {$column_name} per {$network['name']}");
                } catch (PDOException $e2) {
                    error_log("💥 ERRORE CRITICO colonna {$column_name}: " . $e2->getMessage());
                }
            }
        } else {
            error_log("✅ Colonna esistente: {$column_name}");
        }
    }
    
    error_log("🎯 Inizializzazione piattaforme COMPLETATA");
}

// ESEGUIIMMEDIATAMENTE l'inizializzazione
initialize_social_platforms_immediately();

// =============================================
// FUNZIONI PRINCIPALI
// =============================================

/**
 * Recupera tutti i social network attivi ordinati
 */
function get_active_social_networks() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM social_networks 
            WHERE is_active = TRUE 
            ORDER BY display_order ASC, name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Errore nel recupero social networks: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupera un social network per slug
 */
function get_social_network_by_slug($slug) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM social_networks WHERE slug = ? AND is_active = TRUE");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Errore nel recupero social network per slug: " . $e->getMessage());
        return null;
    }
}

/**
 * Recupera tutti i social network (anche non attivi) per admin
 */
function get_all_social_networks() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM social_networks ORDER BY display_order ASC, name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Errore nel recupero tutti social networks: " . $e->getMessage());
        return [];
    }
}

/**
 * Crea un nuovo social network
 */
function create_social_network($data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO social_networks (name, slug, icon, base_url, display_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['icon'],
            $data['base_url'],
            $data['display_order'],
            $data['is_active']
        ]);
    } catch (PDOException $e) {
        error_log("Errore nella creazione social network: " . $e->getMessage());
        return false;
    }
}

/**
 * Crea un nuovo social network con migrazione automatica della colonna
 * VERSIONE SEMPLIFICATA E SICURA
 */
function create_social_network_with_auto_migrate($data) {
    global $pdo;
    
    try {
        // PRIMA crea la colonna
        $column_name = $data['slug'] . '_handle';
        
        // Verifica se la colonna esiste già
        $column_exists = false;
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'influencers' 
                AND COLUMN_NAME = ?
            ");
            $stmt->execute([$column_name]);
            $column_exists = (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Errore verifica colonna: " . $e->getMessage());
        }
        
        if (!$column_exists) {
            // Crea la colonna
            try {
                $sql = "ALTER TABLE influencers ADD COLUMN `{$column_name}` VARCHAR(255) NULL DEFAULT NULL";
                $pdo->exec($sql);
                error_log("🚀 COLONNA CREATA DURANTE SALVATAGGIO: {$column_name}");
            } catch (PDOException $e) {
                error_log("💥 ERRORE creazione colonna durante salvataggio: " . $e->getMessage());
                // Continua comunque con la creazione della piattaforma
            }
        }
        
        // POI crea il social network
        $stmt = $pdo->prepare("
            INSERT INTO social_networks (name, slug, icon, base_url, display_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $success = $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['icon'],
            $data['base_url'],
            $data['display_order'],
            $data['is_active'] ?? 1
        ]);
        
        if ($success) {
            error_log("✅ Piattaforma '{$data['name']}' creata con successo");
        }
        
        return $success;
        
    } catch (Exception $e) {
        error_log("❌ Errore nella creazione social network: " . $e->getMessage());
        return false;
    }
}

/**
 * Aggiorna un social network
 */
function update_social_network($id, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE social_networks 
            SET name = ?, slug = ?, icon = ?, base_url = ?, display_order = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['icon'],
            $data['base_url'],
            $data['display_order'],
            $data['is_active'],
            $id
        ]);
    } catch (PDOException $e) {
        error_log("Errore nell'aggiornamento social network: " . $e->getMessage());
        return false;
    }
}

/**
 * Elimina un social network
 */
function delete_social_network($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM social_networks WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Errore nell'eliminazione social network: " . $e->getMessage());
        return false;
    }
}

/**
 * Genera le opzioni per i dropdown delle piattaforme
 */
function generate_social_platforms_options($selected_platforms = []) {
    $social_networks = get_active_social_networks();
    $options = '';
    
    foreach ($social_networks as $social) {
        $is_selected = in_array($social['slug'], $selected_platforms) ? 'selected' : '';
        $options .= "<option value=\"{$social['slug']}\" $is_selected>{$social['name']}</option>";
    }
    
    return $options;
}

/**
 * Genera i campi per gli social handles
 */
function generate_social_handles_fields($current_handles = []) {
    $social_networks = get_active_social_networks();
    $fields = '';
    
    foreach ($social_networks as $social) {
        $handle_value = $current_handles[$social['slug'] . '_handle'] ?? '';
        $fields .= "
            <div class=\"mb-3\">
                <label for=\"{$social['slug']}_handle\" class=\"form-label\">
                    <i class=\"{$social['icon']} me-2\"></i>{$social['name']}
                </label>
                <div class=\"input-group\">
                    <span class=\"input-group-text\">{$social['base_url']}</span>
                    <input type=\"text\" class=\"form-control\" id=\"{$social['slug']}_handle\" 
                           name=\"{$social['slug']}_handle\" 
                           value=\"" . htmlspecialchars($handle_value) . "\" 
                           placeholder=\"username\">
                </div>
            </div>
        ";
    }
    
    return $fields;
}

/**
 * Verifica se una piattaforma può essere utilizzata per il filtro
 */
function can_filter_by_platform($platform_slug) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'influencers' 
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([$platform_slug . '_handle']);
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Errore verifica colonna filtro: " . $e->getMessage());
        return false;
    }
}

/**
 * Recupera solo le piattaforme che possono essere utilizzate per il filtro
 */
function get_filterable_social_networks() {
    $all_networks = get_active_social_networks();
    $filterable_networks = [];
    
    foreach ($all_networks as $network) {
        if (can_filter_by_platform($network['slug'])) {
            $filterable_networks[] = $network;
        }
    }
    
    return $filterable_networks;
}

/**
 * DEBUG: Mostra lo stato di tutte le colonne
 */
function debug_social_platform_columns() {
    global $pdo;
    
    $social_networks = get_all_social_networks();
    $results = [];
    
    foreach ($social_networks as $network) {
        $column_name = $network['slug'] . '_handle';
        
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'influencers' 
                AND COLUMN_NAME = ?
            ");
            $stmt->execute([$column_name]);
            $exists = (bool)$stmt->fetchColumn();
            
            $results[] = [
                'platform' => $network['name'],
                'slug' => $network['slug'],
                'column' => $column_name,
                'exists' => $exists
            ];
        } catch (PDOException $e) {
            $results[] = [
                'platform' => $network['name'],
                'slug' => $network['slug'],
                'column' => $column_name,
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    return $results;
}

?>