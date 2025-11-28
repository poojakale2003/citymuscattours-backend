<?php

require_once __DIR__ . '/BaseModel.php';

class Payment extends BaseModel {
    protected $table = 'payments';
    protected $primaryKey = 'id';

    public function findByBooking($bookingId) {
        return $this->findOne(['booking_id' => $bookingId]);
    }
}

