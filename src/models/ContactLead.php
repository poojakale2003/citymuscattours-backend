<?php

require_once __DIR__ . '/BaseModel.php';

class ContactLead extends BaseModel {
    protected $table = 'contact_leads';
    protected $primaryKey = 'id';

    public function createLead(array $data) {
        return $this->create($data);
    }

    public function getById($id) {
        return $this->findById($id);
    }
}

