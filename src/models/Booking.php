<?php

require_once __DIR__ . '/BaseModel.php';

class Booking extends BaseModel {
    protected $table = 'bookings';
    protected $primaryKey = 'id';

    public function findByUser($userId, $page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $stmt = $this->db->prepare("
            SELECT b.*, p.name as package_name, p.feature_image as package_image
            FROM {$this->table} b
            LEFT JOIN packages p ON b.package_id = p.id
            WHERE b.user_id = ?
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public function findWithPackage($id) {
        $stmt = $this->db->prepare("
            SELECT b.*, p.name as package_name, p.feature_image as package_image
            FROM {$this->table} b
            LEFT JOIN packages p ON b.package_id = p.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getBookedTravelersForDate($packageId, $date) {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(travelers), 0) as total_booked
            FROM {$this->table}
            WHERE package_id = ? 
            AND date = ?
            AND status IN ('Pending', 'Confirmed', 'Completed')
        ");
        $stmt->execute([$packageId, $date]);
        $result = $stmt->fetch();
        return (int)($result['total_booked'] ?? 0);
    }

    public function getAll($page = 1, $limit = 10) {
        $offset = ($page - 1) * $limit;
        $stmt = $this->db->prepare("
            SELECT b.*, p.name as package_name, p.feature_image as package_image, p.category as package_category
            FROM {$this->table} b
            LEFT JOIN packages p ON b.package_id = p.id
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }
    
    public function getAllWithoutLimit() {
        $stmt = $this->db->prepare("
            SELECT b.*, p.name as package_name, p.feature_image as package_image, p.category as package_category
            FROM {$this->table} b
            LEFT JOIN packages p ON b.package_id = p.id
            ORDER BY b.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function createBooking($data) {
        return $this->create($data);
    }
    
    public function updateBooking($id, $data) {
        return $this->update($id, $data);
    }
}

