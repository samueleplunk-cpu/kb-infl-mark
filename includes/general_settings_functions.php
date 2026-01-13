<?php
/**
 * Funzioni per la gestione delle impostazioni generali
 */

// Includi le funzioni per i social network
require_once 'social_network_functions.php';

/**
 * Recupera le impostazioni delle categorie dal database (NUOVA VERSIONE)
 * Ora utilizza la tabella categories invece delle settings
 */
function get_categories_settings() {
    global $pdo;
    
    try {
        // Verifica se la tabella categories esiste
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'categories'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Usa la nuova tabella categories
            $stmt = $pdo->prepare("
                SELECT id, name, slug, display_order, is_active 
                FROM categories 
                ORDER BY display_order ASC, name ASC
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Converti nel formato compatibile
            $formatted_categories = [];
            foreach ($categories as $category) {
                $formatted_categories[] = [
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'slug' => $category['slug'],
                    'order' => $category['display_order'],
                    'active' => (bool)$category['is_active']
                ];
            }
            
            return ['categories' => $formatted_categories];
        } else {
            // Fallback alla vecchia tabella settings
            return get_categories_settings_legacy();
        }
        
    } catch (PDOException $e) {
        error_log("Errore nel recupero delle categorie: " . $e->getMessage());
        return get_categories_settings_legacy();
    }
}

/**
 * Recupera le impostazioni delle categorie dalla tabella settings (legacy)
 */
function get_categories_settings_legacy() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM settings WHERE setting_key = 'categories_settings'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['setting_value'])) {
            return json_decode($result['setting_value'], true);
        }
    } catch (PDOException $e) {
        error_log("Errore nel recupero legacy delle categorie: " . $e->getMessage());
    }
    
    // Valori di default
    return [
        'categories' => [
            [
                'name' => 'Beauty & Makeup',
                'slug' => 'beauty-makeup',
                'order' => 1,
                'active' => true
            ],
            [
                'name' => 'Fashion',
                'slug' => 'fashion',
                'order' => 2,
                'active' => true
            ]
        ]
    ];
}

/**
 * Recupera le impostazioni dei social network dal database
 */
function get_social_networks_settings() {
    // Ora usiamo la tabella dedicata invece delle impostazioni
    return [
        'social_networks' => get_all_social_networks()
    ];
}

/**
 * Salva le impostazioni delle categorie nel database (NUOVA VERSIONE)
 */
function save_categories_settings($post_data) {
    global $pdo;
    
    try {
        // Verifica se la tabella categories esiste
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'categories'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Usa la nuova tabella categories
            return save_categories_to_table($post_data);
        } else {
            // Usa la vecchia tabella settings
            return save_categories_to_settings($post_data);
        }
        
    } catch (PDOException $e) {
        error_log("Errore nel salvataggio delle categorie: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Errore nel salvataggio delle categorie: ' . $e->getMessage()
        ];
    }
}

/**
 * Salva le categorie nella nuova tabella categories - MODIFICATO: Rimossa descrizione
 */
function save_categories_to_table($post_data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        $categories = $post_data['categories'] ?? [];
        $existing_ids = [];
        
        foreach ($categories as $category_data) {
            if (empty($category_data['name']) || empty($category_data['slug'])) {
                continue;
            }
            
            $category_data['is_active'] = isset($category_data['active']) ? 1 : 0;
            $category_data['display_order'] = intval($category_data['order'] ?? 1);
            
            if (isset($category_data['id']) && !empty($category_data['id'])) {
                // Aggiorna categoria esistente
                $result = update_category_in_table($category_data['id'], $category_data);
                if ($result['success']) {
                    $existing_ids[] = $category_data['id'];
                }
            } else {
                // Crea nuova categoria
                $result = create_category_in_table($category_data);
                if ($result['success']) {
                    $existing_ids[] = $result['id'];
                }
            }
        }
        
        $pdo->commit();
        return [
            'success' => true,
            'message' => 'Categorie salvate con successo nella nuova tabella!'
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Errore nel salvataggio delle categorie nella tabella: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Errore nel salvataggio delle categorie: ' . $e->getMessage()
        ];
    }
}

/**
 * Crea una nuova categoria nella tabella categories - MODIFICATO: Rimossa descrizione
 */
function create_category_in_table($category_data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO categories (name, slug, display_order, is_active) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $category_data['name'],
            $category_data['slug'],
            $category_data['display_order'],
            $category_data['is_active']
        ]);
        
        return [
            'success' => true,
            'id' => $pdo->lastInsertId()
        ];
        
    } catch (PDOException $e) {
        error_log("Errore nella creazione della categoria: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Errore nella creazione della categoria: ' . $e->getMessage()
        ];
    }
}

/**
 * Aggiorna una categoria esistente nella tabella categories - MODIFICATO: Rimossa descrizione
 */
function update_category_in_table($category_id, $category_data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE categories 
            SET name = ?, slug = ?, display_order = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $category_data['name'],
            $category_data['slug'],
            $category_data['display_order'],
            $category_data['is_active'],
            $category_id
        ]);
        
        return ['success' => true];
        
    } catch (PDOException $e) {
        error_log("Errore nell'aggiornamento della categoria: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Errore nell\'aggiornamento della categoria: ' . $e->getMessage()
        ];
    }
}

/**
 * Elimina una categoria con controllo integrità referenziale
 */
function delete_category_from_table($category_id) {
    global $pdo;
    
    try {
        // Controlla se la categoria è utilizzata da qualche brand
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM brands 
            WHERE industry = (SELECT name FROM categories WHERE id = ?)
        ");
        $stmt->execute([$category_id]);
        $usage_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($usage_count > 0) {
            return [
                'success' => false, 
                'error' => 'Impossibile eliminare: categoria in uso da ' . $usage_count . ' brand'
            ];
        }
        
        // Elimina la categoria
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        
        return ['success' => true];
        
    } catch (PDOException $e) {
        error_log("Errore nell'eliminazione della categoria: " . $e->getMessage());
        return [
            'success' => false, 
            'error' => 'Errore nell\'eliminazione della categoria: ' . $e->getMessage()
        ];
    }
}

/**
 * Salva le categorie nella vecchia tabella settings (compatibilità) - MODIFICATO: Rimossa descrizione
 */
function save_categories_to_settings($post_data) {
    global $pdo;
    
    try {
        $categories = [];
        
        if (isset($post_data['categories']) && is_array($post_data['categories'])) {
            foreach ($post_data['categories'] as $category_data) {
                $category = [
                    'name' => trim($category_data['name']),
                    'slug' => trim($category_data['slug']),
                    'order' => intval($category_data['order']),
                    'active' => isset($category_data['active']) ? true : false
                ];
                
                // Validazione base
                if (empty($category['name']) || empty($category['slug'])) {
                    continue;
                }
                
                $categories[] = $category;
            }
        }
        
        // Ordina per ordine
        usort($categories, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        $settings_data = ['categories' => $categories];
        $json_data = json_encode($settings_data);
        
        // Salva nel database
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at) 
            VALUES ('categories_settings', ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
        ");
        $stmt->execute([$json_data, $json_data]);
        
        return [
            'success' => true,
            'message' => 'Categorie salvate con successo!'
        ];
        
    } catch (PDOException $e) {
        error_log("Errore nel salvataggio delle categorie: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Errore nel salvataggio delle categorie: ' . $e->getMessage()
        ];
    }
}

/**
 * Salva le impostazioni dei social network nel database
 */
function save_social_networks_settings($post_data) {
    global $pdo;
    
    try {
        if (isset($post_data['social_networks']) && is_array($post_data['social_networks'])) {
            foreach ($post_data['social_networks'] as $social_data) {
                $social_network = [
                    'name' => trim($social_data['name']),
                    'slug' => trim($social_data['slug']),
                    'icon' => trim($social_data['icon']),
                    'base_url' => trim($social_data['base_url']),
                    'display_order' => intval($social_data['order']),
                    'is_active' => isset($social_data['active']) ? true : false
                ];
                
                // Validazione
                if (empty($social_network['name']) || empty($social_network['slug'])) {
                    continue;
                }
                
                // Verifica se esiste già
                $existing = get_social_network_by_slug($social_network['slug']);
                
                if ($existing) {
                    // Update
                    update_social_network($existing['id'], $social_network);
                } else {
                    // Insert
                    create_social_network($social_network);
                }
            }
        }
        
        return [
            'success' => true,
            'message' => 'Social network salvati con successo!'
        ];
        
    } catch (PDOException $e) {
        error_log("Errore nel salvataggio dei social network: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Errore nel salvataggio dei social network: ' . $e->getMessage()
        ];
    }
}

/**
 * Recupera tutte le categorie attive per i brand (NUOVA FUNZIONE) - MODIFICATO: Rimossa descrizione
 */
function get_active_categories_for_brands() {
    global $pdo;
    
    try {
        // Verifica se la tabella categories esiste
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'categories'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Usa la nuova tabella categories
            $stmt = $pdo->prepare("
                SELECT id, name, slug, display_order 
                FROM categories 
                WHERE is_active = TRUE 
                ORDER BY display_order ASC, name ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Fallback alle categorie dalla tabella settings
            $settings = get_categories_settings_legacy();
            $categories = [];
            
            foreach ($settings['categories'] as $index => $category) {
                if ($category['active']) {
                    $categories[] = [
                        'id' => $index,
                        'name' => $category['name'],
                        'slug' => $category['slug'],
                        'display_order' => $category['order']
                    ];
                }
            }
            
            return $categories;
        }
        
    } catch (PDOException $e) {
        error_log("Errore nel recupero delle categorie attive: " . $e->getMessage());
        return [];
    }
}