<?php

namespace Featurevisor\Tests;

use PHPUnit\Framework\TestCase;
use Featurevisor\Bucketer;

class BucketerTest extends TestCase {

    public function testGetBucketedNumberRange() {
        $keys = ['foo', 'bar', 'baz', '123adshlk348-93asdlk'];
        foreach ($keys as $key) {
            $n = Bucketer::getBucketedNumber($key);
            self::assertGreaterThanOrEqual(0, $n);
            self::assertLessThanOrEqual(Bucketer::MAX_BUCKETED_NUMBER, $n);
        }
    }

    public function testGetBucketedNumberKnownKeys() {
        // These values must match the JS SDK for cross-language consistency
        $expectedResults = [
            'foo' => 20602,
            'bar' => 89144,
            '123.foo' => 3151,
            '123.bar' => 9710,
            '123.456.foo' => 14432,
            '123.456.bar' => 1982,
        ];
        foreach ($expectedResults as $key => $expected) {
            $n = Bucketer::getBucketedNumber($key);
            self::assertEquals($expected, $n, "Bucketed number for '$key' should be $expected, got $n");
        }
    }

    public function testGetBucketKeyIsFunction() {
        self::assertTrue(is_callable([Bucketer::class, 'getBucketKey']));
    }

    public function testGetBucketKeyPlain() {
        $featureKey = 'test-feature';
        $bucketBy = 'userId';
        $context = ['userId' => '123', 'browser' => 'chrome'];
        $bucketKey = Bucketer::getBucketKey([
            'featureKey' => $featureKey,
            'bucketBy' => $bucketBy,
            'context' => $context,
        ]);
        self::assertEquals('123.test-feature', $bucketKey);
    }

    public function testGetBucketKeyPlainMissingContext() {
        $featureKey = 'test-feature';
        $bucketBy = 'userId';
        $context = ['browser' => 'chrome'];
        $bucketKey = Bucketer::getBucketKey([
            'featureKey' => $featureKey,
            'bucketBy' => $bucketBy,
            'context' => $context,
        ]);
        self::assertEquals('test-feature', $bucketKey);
    }

    public function testGetBucketKeyStringifiesWholeFloatsAndNegativeZeroLikeJavaScript() {
        $bucketKey = Bucketer::getBucketKey([
            'featureKey' => 'feature',
            'bucketBy' => ['whole', 'negativeZero', 'small', 'large'],
            'context' => ['whole' => 1.0, 'negativeZero' => -0.0, 'small' => 1e-6, 'large' => 1e21],
        ]);
        self::assertSame('1.0.0.000001.1e+21.feature', $bucketKey);
    }

    public function testGetBucketKeyAndAllPresent() {
        $featureKey = 'test-feature';
        $bucketBy = ['organizationId', 'userId'];
        $context = ['organizationId' => '123', 'userId' => '234', 'browser' => 'chrome'];
        $bucketKey = Bucketer::getBucketKey([
            'featureKey' => $featureKey,
            'bucketBy' => $bucketBy,
            'context' => $context,
        ]);
        self::assertEquals('123.234.test-feature', $bucketKey);
    }

    public function testGetBucketKeyAndPartial() {
        $featureKey = 'test-feature';
        $bucketBy = ['organizationId', 'userId'];
        $context = ['organizationId' => '123', 'browser' => 'chrome'];
        $bucketKey = Bucketer::getBucketKey([
            'featureKey' => $featureKey,
            'bucketBy' => $bucketBy,
            'context' => $context,
        ]);
        self::assertEquals('123.test-feature', $bucketKey);
    }

    public function testGetBucketKeyAndDotSeparated() {
        $featureKey = 'test-feature';
        $bucketBy = ['organizationId', 'user.id'];
        $context = [
            'organizationId' => '123',
            'user' => ['id' => '234'],
            'browser' => 'chrome',
        ];
        $bucketKey = Bucketer::getBucketKey([
            'featureKey' => $featureKey,
            'bucketBy' => $bucketBy,
            'context' => $context,
        ]);
        self::assertEquals('123.234.test-feature', $bucketKey);
    }

    public function testGetBucketKeyOrFirstAvailable() {
        $featureKey = 'test-feature';
        $bucketBy = ['or' => ['userId', 'deviceId']];
        $context = ['deviceId' => 'deviceIdHere', 'userId' => '234', 'browser' => 'chrome'];
        $bucketKey = Bucketer::getBucketKey([
            'featureKey' => $featureKey,
            'bucketBy' => $bucketBy,
            'context' => $context,
        ]);
        self::assertEquals('234.test-feature', $bucketKey);
    }

    public function testGetBucketKeyOrOnlyDeviceId() {
        $featureKey = 'test-feature';
        $bucketBy = ['or' => ['userId', 'deviceId']];
        $context = ['deviceId' => 'deviceIdHere', 'browser' => 'chrome'];
        $bucketKey = Bucketer::getBucketKey([
            'featureKey' => $featureKey,
            'bucketBy' => $bucketBy,
            'context' => $context,
        ]);
        self::assertEquals('deviceIdHere.test-feature', $bucketKey);
    }
}
