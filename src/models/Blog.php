<?php

require_once __DIR__ . '/BaseModel.php';

class Blog extends BaseModel {
    protected $table = 'blogs';
    protected $primaryKey = 'id';

    public function getById($id) {
        return $this->findById($id);
    }

    public function getAll($filters = [], $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        // Only filter by published status if explicitly set
        // If not set, return all blogs (for admin access)
        if (isset($filters['published']) && $filters['published'] !== null) {
            $where[] = "is_published = ?";
            $params[] = $filters['published'] ? 1 : 0;
        }

        if (isset($filters['category'])) {
            $where[] = "category = ?";
            $params[] = $filters['category'];
        }

        if (isset($filters['search'])) {
            $where[] = "(title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql = "SELECT * FROM {$this->table}";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY published_at DESC, created_at DESC";
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

        // Only filter by published status if explicitly set
        // If not set, return all blogs (for admin access)
        if (isset($filters['published']) && $filters['published'] !== null) {
            $where[] = "is_published = ?";
            $params[] = $filters['published'] ? 1 : 0;
        }

        if (isset($filters['category'])) {
            $where[] = "category = ?";
            $params[] = $filters['category'];
        }

        if (isset($filters['search'])) {
            $where[] = "(title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
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

    public function getBySlug($slug) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE slug = ? AND is_published = 1");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    public function createBlog($data) {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        if (!isset($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title']);
        }
        
        // Convert boolean to integer for database
        if (!isset($data['is_published'])) {
            $data['is_published'] = 0;
        } else {
            // Ensure it's an integer (0 or 1)
            $data['is_published'] = ($data['is_published'] === true || $data['is_published'] === 'true' || $data['is_published'] === '1' || $data['is_published'] === 1) ? 1 : 0;
        }
        
        if ($data['is_published'] == 1 && !isset($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }
        
        return $this->create($data);
    }

    public function updateBlog($id, $data) {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        // Convert boolean to integer for database if is_published is set
        if (isset($data['is_published'])) {
            $data['is_published'] = ($data['is_published'] === true || $data['is_published'] === 'true' || $data['is_published'] === '1' || $data['is_published'] === 1) ? 1 : 0;
        }
        
        // If publishing for the first time, set published_at
        if (isset($data['is_published']) && $data['is_published'] == 1) {
            $existing = $this->findById($id);
            if ($existing && empty($existing['published_at'])) {
                $data['published_at'] = date('Y-m-d H:i:s');
            }
        }
        
        // If slug is being updated, regenerate it
        if (isset($data['title']) && !isset($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title'], $id);
        }
        
        return $this->update($id, $data);
    }

    public function deleteBlog($id) {
        return $this->delete($id);
    }

    private function generateSlug($title, $excludeId = null) {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure uniqueness
        $baseSlug = $slug;
        $counter = 1;
        while (true) {
            $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE slug = ?" . ($excludeId ? " AND id != ?" : ""));
            $params = [$slug];
            if ($excludeId) {
                $params[] = $excludeId;
            }
            $stmt->execute($params);
            $existing = $stmt->fetch();
            
            if (!$existing) {
                break;
            }
            
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
}

