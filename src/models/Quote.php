<?php

require_once __DIR__ . '/BaseModel.php';

class Quote extends BaseModel {
    protected $table = 'quotes';
    protected $primaryKey = 'id';
}

