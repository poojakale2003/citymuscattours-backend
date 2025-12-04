<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/utils/PriceHelper.php';

class PriceHelperTest extends TestCase
{
    public function testToFloat()
    {
        $result = PriceHelper::toFloat(100.50);
        $this->assertEquals(100.50, $result);
    }

    public function testToFloatWithString()
    {
        $result = PriceHelper::toFloat('100.50');
        $this->assertEquals(100.50, $result);
    }

    public function testToFloatWithNull()
    {
        $result = PriceHelper::toFloat(null);
        $this->assertEquals(0.0, $result);
    }

    public function testFormatForJson()
    {
        $result = PriceHelper::formatForJson(100.50);
        $this->assertEquals(100.50, $result);
    }

    public function testGetEffectivePrice()
    {
        $package = ['price' => 100, 'offer_price' => 80];
        $result = PriceHelper::getEffectivePrice($package);
        $this->assertEquals(80, $result);
    }

    public function testGetEffectivePriceNoOffer()
    {
        $package = ['price' => 100];
        $result = PriceHelper::getEffectivePrice($package);
        $this->assertEquals(100, $result);
    }
}

