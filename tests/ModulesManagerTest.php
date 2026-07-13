<?php

namespace Featurevisor\Tests;

use Featurevisor\ModulesManager;
use PHPUnit\Framework\TestCase;

class ModulesManagerTest extends TestCase
{
    public function testSetupFailureDoesNotRegisterModule(): void
    {
        $diagnostics = [];
        $closed = 0;
        $cleared = 0;
        $manager = new ModulesManager(
            [],
            static function (array $diagnostic) use (&$diagnostics): void {
                $diagnostics[] = $diagnostic;
            },
            static fn (array $module): array => [],
            static function (array $module) use (&$cleared): void {
                $cleared++;
            }
        );

        $unsubscribe = $manager->add([
            'name' => 'broken-setup',
            'setup' => static function (): void {
                throw new \RuntimeException('setup failed');
            },
            'close' => static function () use (&$closed): void {
                $closed++;
            },
        ]);

        self::assertNull($unsubscribe);
        self::assertSame([], $manager->getAll());
        self::assertSame(1, $closed);
        self::assertSame(1, $cleared);
        self::assertSame('module_setup_error', $diagnostics[0]['code']);
    }
}
