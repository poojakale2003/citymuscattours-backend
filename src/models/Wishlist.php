<?php

require_once __DIR__ . '/BaseModel.php';

class Wishlist extends BaseModel {
    protected $table = 'wishlist';
    protected $primaryKey = 'id';

    public function findByUser($userId) {
        $stmt = $this->db->prepare("
            SELECT w.*, p.name, p.destination, p.price, p.offer_price, p.feature_image
            FROM {$this->table} w
            LEFT JOIN packages p ON w.package_id = p.id
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findByUserAndPackage($userId, $packageId) {
        return $this->findOne(['user_id' => $userId, 'package_id' => $packageId]);
    }

    public function findByDeviceAndPackage($deviceId, $packageId) {
        return $this->findOne(['device_id' => $deviceId, 'package_id' => $packageId]);
    }
}

