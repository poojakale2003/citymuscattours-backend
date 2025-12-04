<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/models/User.php';

class UserModelTest extends TestCase
{
    public function testUserModelExists()
    {
        $this->assertTrue(class_exists('User'));
    }
}

