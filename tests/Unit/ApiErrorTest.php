<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/utils/ApiError.php';

class ApiErrorTest extends TestCase
{
    public function testApiErrorClassExists()
    {
        $this->assertTrue(class_exists('ApiError'));
    }

    public function testApiErrorCreation()
    {
        $error = new ApiError('Test error', 400);
        $this->assertInstanceOf(ApiError::class, $error);
    }
}

