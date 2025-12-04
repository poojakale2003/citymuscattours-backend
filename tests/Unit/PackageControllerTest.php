<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/controllers/packageController.php';

class PackageControllerTest extends TestCase
{
    public function testPackageControllerExists()
    {
        $this->assertTrue(class_exists('PackageController') || function_exists('handlePackages'));
    }
}

