<?php

require_once __DIR__ . '/BaseModel.php';

class Review extends BaseModel {
    protected $table = 'reviews';
    protected $primaryKey = 'id';

    public function findByPackage($packageId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $stmt = $this->db->prepare("
            SELECT r.*, u.name as user_name
            FROM {$this->table} r
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.package_id = ?
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$packageId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public function findByUserAndPackage($userId, $packageId) {
        return $this->findOne(['user_id' => $userId, 'package_id' => $packageId]);
    }

    public function getAverageRating($packageId) {
        $stmt = $this->db->prepare("
            SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
            FROM {$this->table}
            WHERE package_id = ?
        ");
        $stmt->execute([$packageId]);
        return $stmt->fetch();
    }
}

