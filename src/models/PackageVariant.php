<?php

require_once __DIR__ . '/BaseModel.php';

class PackageVariant extends BaseModel {
    protected $table = 'package_variants';
    protected $primaryKey = 'id';

    public function findByPackage($packageId, $activeOnly = true) {
        $sql = "SELECT * FROM {$this->table} WHERE package_id = ?";
        $params = [$packageId];
        
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        
        $sql .= " ORDER BY display_order ASC, id ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByPackageAndVariant($packageId, $variantId) {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE package_id = ? AND variant_id = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$packageId, $variantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createVariant($data) {
        return $this->create($data);
    }

    public function updateVariant($id, $data) {
        return $this->update($id, $data);
    }

    public function deleteVariant($id) {
        return $this->delete($id);
    }

    public function getById($id) {
        return $this->findById($id);
    }
}

