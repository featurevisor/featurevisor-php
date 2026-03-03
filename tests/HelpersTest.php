<?php

namespace Featurevisor\Tests;

use Featurevisor\Helpers;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function testShouldReturnNullForTypeMismatch()
    {
        self::assertNull(Helpers::getValueByType(1, 'string'));
    }

    public function testShouldResolveSupportedTypes()
    {
        self::assertSame('1', Helpers::getValueByType('1', 'string'));
        self::assertSame(true, Helpers::getValueByType(true, 'boolean'));
        self::assertSame(['a' => 1], Helpers::getValueByType(['a' => 1], 'object'));
        self::assertSame(['1', '2'], Helpers::getValueByType(['1', '2'], 'array'));
        self::assertSame(1, Helpers::getValueByType('1', 'integer'));
        self::assertSame(1.1, Helpers::getValueByType('1.1', 'double'));
    }

    public function testShouldReturnNullForNullValue()
    {
        self::assertNull(Helpers::getValueByType(null, 'string'));
    }
}
