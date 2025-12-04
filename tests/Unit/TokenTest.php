<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/utils/token.php';

class TokenTest extends TestCase
{
    public function testTokenUtilsExist()
    {
        $this->assertTrue(function_exists('generateToken') || class_exists('Token'));
    }
}

