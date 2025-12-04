<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/models/Package.php';

class PackageModelTest extends TestCase
{
    public function testPackageModelExists()
    {
        $this->assertTrue(class_exists('Package'));
    }
}

