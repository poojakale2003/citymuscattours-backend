<?php

require_once __DIR__ . '/../config/db.php';

/**
 * Get all unique categories from packages
 */
function getCategories($req, $res) {
    try {
        $db = getDB();
        
        // Get distinct categories with package count
        $sql = "SELECT 
                    category,
                    COUNT(*) as package_count
                FROM packages 
                WHERE category IS NOT NULL AND category != ''
                GROUP BY category
                ORDER BY category ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format response
        $categories = array_map(function($row) {
            return [
                'name' => $row['category'],
                'slug' => strtolower(str_replace(' ', '-', trim($row['category']))),
                'packageCount' => (int)$row['package_count']
            ];
        }, $results);
        
        // If no categories found, return empty array with some default categories
        if (empty($categories)) {
            $categories = [
                ['name' => 'Adventure', 'slug' => 'adventure', 'packageCount' => 0],
                ['name' => 'Beach', 'slug' => 'beach', 'packageCount' => 0],
                ['name' => 'Cultural', 'slug' => 'cultural', 'packageCount' => 0],
                ['name' => 'Family', 'slug' => 'family', 'packageCount' => 0],
                ['name' => 'Honeymoon', 'slug' => 'honeymoon', 'packageCount' => 0],
                ['name' => 'Luxury', 'slug' => 'luxury', 'packageCount' => 0],
                ['name' => 'Wildlife', 'slug' => 'wildlife', 'packageCount' => 0],
            ];
        }
        
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $categories,
            'meta' => [
                'total' => count($categories)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting categories: " . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch categories',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Get a single category by slug/name
 */
function getCategory($req, $res) {
    try {
        $slug = $req['params']['slug'] ?? $req['params']['name'] ?? null;
        
        if (!$slug) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Category slug or name is required'
            ]);
            return;
        }
        
        $db = getDB();
        
        // Search by category name (case-insensitive)
        $categoryName = str_replace('-', ' ', $slug);
        $categoryName = ucwords($categoryName); // Convert to title case
        
        $sql = "SELECT 
                    category,
                    COUNT(*) as package_count
                FROM packages 
                WHERE category = ? OR LOWER(category) = LOWER(?)
                GROUP BY category
                LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$categoryName, $slug]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Category not found'
            ]);
            return;
        }
        
        $category = [
            'name' => $result['category'],
            'slug' => strtolower(str_replace(' ', '-', trim($result['category']))),
            'packageCount' => (int)$result['package_count']
        ];
        
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $category
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting category: " . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch category',
            'error' => $e->getMessage()
        ]);
    }
}

