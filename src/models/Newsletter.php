<?php

require_once __DIR__ . '/BaseModel.php';

class Newsletter extends BaseModel {
    protected $table = 'newsletter';
    protected $primaryKey = 'id';

    public function findByEmail($email) {
        return $this->findOne(['email' => strtolower(trim($email))]);
    }

    public function createSubscription(array $data) {
        return $this->create($data);
    }

    public function getById($id) {
        return $this->findById($id);
    }
}

