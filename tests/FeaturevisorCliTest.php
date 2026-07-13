<?php

namespace Featurevisor\Tests;

use PHPUnit\Framework\TestCase;

class FeaturevisorCliTest extends TestCase
{
    public function testRepeatedTargetOptions()
    {
        self::assertSame(
            ['web', 'mobile'],
            \parseCliOptions(['featurevisor', 'test', '--target=web', '--target=mobile', '--target=web'], 'target')
        );
    }

    public static function setUpBeforeClass(): void
    {
        if (!defined('FEATUREVISOR_CLI_TEST')) {
            define('FEATUREVISOR_CLI_TEST', true);
        }

        require_once dirname(__DIR__) . '/featurevisor';
    }

    public function testTargetDatafileCacheKey()
    {
        self::assertSame('false-target-checkout', \getTargetDatafileKey(false, 'checkout'));
        self::assertSame('false-target-checkout', \getTargetDatafileKey(null, 'checkout'));
        self::assertSame('production-target-checkout', \getTargetDatafileKey('production', 'checkout'));
    }

    public function testEnvironmentListSupportsNoEnvironmentProjects()
    {
        self::assertSame([false], \getEnvironmentList(['environments' => false]));
        self::assertSame([false], \getEnvironmentList([]));
        self::assertSame(['staging', 'production'], \getEnvironmentList(['environments' => ['staging', 'production']]));
    }

    public function testTargetAssertionSelectsTargetDatafile()
    {
        $datafile = \getDatafileForAssertion(
            [
                'environment' => 'production',
                'target' => 'checkout',
            ],
            [
                'production' => ['kind' => 'base'],
                'production-target-checkout' => ['kind' => 'target'],
            ]
        );

        self::assertSame('target', $datafile['kind']);
    }

    public function testTargetAssertionFallsBackToBaseDatafile()
    {
        $datafile = \getDatafileForAssertion(
            [
                'environment' => 'production',
                'target' => 'checkout',
            ],
            [
                'production' => ['kind' => 'base'],
            ]
        );

        self::assertSame('base', $datafile['kind']);
    }

    public function testNoEnvironmentTargetAssertionSelectsTargetDatafile()
    {
        $datafile = \getDatafileForAssertion(
            [
                'target' => 'checkout',
            ],
            [
                false => ['kind' => 'base'],
                'false-target-checkout' => ['kind' => 'target'],
            ]
        );

        self::assertSame('target', $datafile['kind']);
    }
}
