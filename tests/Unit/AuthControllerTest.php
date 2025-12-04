<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/controllers/authController.php';

class AuthControllerTest extends TestCase
{
    public function testAuthControllerExists()
    {
        $this->assertTrue(class_exists('AuthController') || function_exists('handleAuth'));
    }
}

