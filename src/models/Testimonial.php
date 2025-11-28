<?php

require_once __DIR__ . '/BaseModel.php';

class Testimonial extends BaseModel {
    protected $table = 'testimonials';
    protected $primaryKey = 'id';

    public function getById($id) {
        return $this->findById($id);
    }

    public function getAll($filters = [], $page = 1, $limit = 100) {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        // Filter by active status if set
        if (isset($filters['is_active']) && $filters['is_active'] !== null) {
            $where[] = "is_active = ?";
            $params[] = $filters['is_active'] ? 1 : 0;
        }

        if (isset($filters['search'])) {
            $where[] = "(name LIKE ? OR location LIKE ? OR quote LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql = "SELECT * FROM {$this->table}";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY display_order ASC, created_at DESC";
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getTotalCount($filters = []) {
        $where = [];
        $params = [];

        if (isset($filters['is_active']) && $filters['is_active'] !== null) {
            $where[] = "is_active = ?";
            $params[] = $filters['is_active'] ? 1 : 0;
        }

        if (isset($filters['search'])) {
            $where[] = "(name LIKE ? OR location LIKE ? OR quote LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }

    public function getActive() {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY display_order ASC, created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createTestimonial($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Convert boolean to integer for database
        if (!isset($data['is_active'])) {
            $data['is_active'] = 1;
        } else {
            $data['is_active'] = ($data['is_active'] === true || $data['is_active'] === 'true' || $data['is_active'] === '1' || $data['is_active'] === 1) ? 1 : 0;
        }

        if (!isset($data['rating'])) {
            $data['rating'] = 5;
        }

        if (!isset($data['display_order'])) {
            $data['display_order'] = 0;
        }
        
        return $this->create($data);
    }

    public function updateTestimonial($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Convert boolean to integer for database if is_active is set
        if (isset($data['is_active'])) {
            $data['is_active'] = ($data['is_active'] === true || $data['is_active'] === 'true' || $data['is_active'] === '1' || $data['is_active'] === 1) ? 1 : 0;
        }
        
        return $this->update($id, $data);
    }

    public function deleteTestimonial($id) {
        return $this->delete($id);
    }
}

