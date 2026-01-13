<?php
// includes/category_functions.php

/**
 * Recupera tutte le categorie attive ordinate per display_order - MODIFICATO: Rimossa descrizione
 */
function get_active_categories($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, slug, display_order 
            FROM categories 
            WHERE is_active = TRUE 
            ORDER BY display_order ASC, name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting active categories: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupera tutte le categorie (anche non attive) per admin - MODIFICATO: Rimossa descrizione
 */
function get_all_categories($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, slug, display_order, is_active, created_at, updated_at 
            FROM categories 
            ORDER BY display_order ASC, name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting all categories: " . $e->getMessage());
        return [];
    }
}

/**
 * Recupera una categoria specifica per ID - MODIFICATO: Rimossa descrizione
 */
function get_category_by_id($pdo, $category_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, slug, display_order, is_active, created_at, updated_at 
            FROM categories 
            WHERE id = ?
        ");
        $stmt->execute([$category_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting category by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Crea una nuova categoria - MODIFICATO: Rimossa descrizione
 */
function create_category($pdo, $category_data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO categories (name, slug, display_order, is_active) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $category_data['name'],
            $category_data['slug'],
            $category_data['display_order'],
            $category_data['is_active'] ?? true
        ]);
        return ['success' => true, 'id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        error_log("Error creating category: " . $e->getMessage());
        return ['success' => false, 'error' => 'Errore nella creazione della categoria'];
    }
}

/**
 * Aggiorna una categoria esistente - MODIFICATO: Rimossa descrizione
 */
function update_category($pdo, $category_id, $category_data) {
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
            $category_data['is_active'] ?? true,
            $category_id
        ]);
        return ['success' => true];
    } catch (PDOException $e) {
        error_log("Error updating category: " . $e->getMessage());
        return ['success' => false, 'error' => 'Errore nell\'aggiornamento della categoria'];
    }
}

/**
 * Elimina una categoria (controllo integrità referenziale)
 */
function delete_category($pdo, $category_id) {
    try {
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
        
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        
        return ['success' => true];
    } catch (PDOException $e) {
        error_log("Error deleting category: " . $e->getMessage());
        return ['success' => false, 'error' => 'Errore nell\'eliminazione della categoria'];
    }
}

/**
 * Verifica se uno slug è già in uso
 */
function is_slug_available($pdo, $slug, $exclude_id = null) {
    try {
        $sql = "SELECT COUNT(*) as count FROM categories WHERE slug = ?";
        $params = [$slug];
        
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] == 0;
    } catch (PDOException $e) {
        error_log("Error checking slug availability: " . $e->getMessage());
        return false;
    }
}

/**
 * Genera uno slug univoco dal nome
 */
function generate_unique_slug($pdo, $name, $exclude_id = null) {
    $base_slug = strtolower(trim($name));
    $base_slug = preg_replace('/[^a-z0-9]+/', '-', $base_slug);
    $base_slug = trim($base_slug, '-');
    
    $slug = $base_slug;
    $counter = 1;
    
    while (!is_slug_available($pdo, $slug, $exclude_id)) {
        $slug = $base_slug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

/**
 * Recupera le categorie in formato semplificato per dropdown - NUOVA FUNZIONE
 */
function get_categories_for_dropdown($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, name 
            FROM categories 
            WHERE is_active = TRUE 
            ORDER BY display_order ASC, name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting categories for dropdown: " . $e->getMessage());
        return [];
    }
}

/**
 * Conta il numero totale di categorie attive - NUOVA FUNZIONE
 */
function count_active_categories($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM categories WHERE is_active = TRUE");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (PDOException $e) {
        error_log("Error counting active categories: " . $e->getMessage());
        return 0;
    }
}

/**
 * Verifica se una categoria esiste - NUOVA FUNZIONE
 */
function category_exists($pdo, $category_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking if category exists: " . $e->getMessage());
        return false;
    }
}

/**
 * Recupera le categorie con informazioni di utilizzo - NUOVA FUNZIONE
 */
function get_categories_with_usage($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.name,
                c.slug,
                c.display_order,
                c.is_active,
                COUNT(b.id) as brand_count
            FROM categories c
            LEFT JOIN brands b ON b.industry = c.name
            GROUP BY c.id, c.name, c.slug, c.display_order, c.is_active
            ORDER BY c.display_order ASC, c.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting categories with usage: " . $e->getMessage());
        return [];
    }
}