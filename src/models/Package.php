<?php

require_once __DIR__ . '/BaseModel.php';

class Package extends BaseModel {
    protected $table = 'packages';
    protected $primaryKey = 'id';

    public function search($filters = [], $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        if (isset($filters['destination'])) {
            $where[] = "destination LIKE ?";
            $params[] = "%{$filters['destination']}%";
        }

        if (isset($filters['category'])) {
            $where[] = "category = ?";
            $params[] = $filters['category'];
        }

        if (isset($filters['isFeatured'])) {
            $where[] = "is_featured = ?";
            $params[] = $filters['isFeatured'] ? 1 : 0;
        }

        // Always filter by is_archived - check if filter is explicitly set
        if (array_key_exists('isArchived', $filters)) {
            // Convert boolean/string to integer for database
            $isArchivedValue = $filters['isArchived'];
            $archivedInt = 0;
            if ($isArchivedValue === true || $isArchivedValue === 1 || $isArchivedValue === '1' || $isArchivedValue === 'true') {
                $archivedInt = 1;
            }
            $where[] = "is_archived = ?";
            $params[] = $archivedInt;
            error_log("Package::search - Filtering by is_archived = " . $archivedInt . " (from filter value: " . var_export($isArchivedValue, true) . ", type: " . gettype($isArchivedValue) . ")");
        } else {
            // Default to non-archived packages if not specified
            $where[] = "is_archived = ?";
            $params[] = 0;
            error_log("Package::search - No isArchived filter key found, defaulting to is_archived = 0");
        }

        if (isset($filters['minPrice'])) {
            $where[] = "COALESCE(offer_price, price) >= ?";
            $params[] = $filters['minPrice'];
        }

        if (isset($filters['maxPrice'])) {
            $where[] = "COALESCE(offer_price, price) <= ?";
            $params[] = $filters['maxPrice'];
        }

        $sql = "SELECT * FROM {$this->table}";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        error_log("Package::search - SQL: " . $sql);
        error_log("Package::search - Params: " . json_encode($params));
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Package::search - Results count: " . count($results));
        if (!empty($results)) {
            foreach ($results as $idx => $result) {
                error_log("Package::search - Result #{$idx}: id={$result['id']}, name={$result['name']}, is_archived={$result['is_archived']} (type: " . gettype($result['is_archived']) . ")");
            }
        } else {
            error_log("Package::search - No results returned!");
        }
        
        return $results;
    }

    public function findBySlug($slug) {
        return $this->findOne(['slug' => $slug]);
    }

    // Public wrapper methods to access protected BaseModel methods
    public function createPackage($data) {
        return $this->create($data);
    }

    public function getById($id) {
        return $this->findById($id);
    }
    
    public function getAllPackages($limit = 10) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE is_archived = 0 ORDER BY id DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updatePackage($id, $data) {
        return $this->update($id, $data);
    }

    public function deletePackage($id) {
        return $this->delete($id);
    }
}

