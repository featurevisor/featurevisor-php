<?php

namespace Featurevisor\Tests;

use Featurevisor\Featurevisor;
use PHPUnit\Framework\TestCase;

class DiagnosticContractTest extends TestCase
{
    public function testEmptyDetailsSerializeAsAnObject(): void
    {
        $diagnostics = [];
        Featurevisor::createFeaturevisor([
            'logLevel' => 'info',
            'onDiagnostic' => static function (array $diagnostic) use (&$diagnostics): void {
                $diagnostics[] = $diagnostic;
            },
        ]);

        $encoded = json_encode($diagnostics[0], JSON_THROW_ON_ERROR);
        self::assertStringContainsString('"details":{}', $encoded);
    }

    public function testDiagnosticHandlerFailureIsIsolated(): void
    {
        $sdk = Featurevisor::createFeaturevisor([
            'onDiagnostic' => static function (): void {
                throw new \RuntimeException('handler failed');
            },
        ]);

        self::assertFalse($sdk->isEnabled('missing', []));
        $sdk->close();
    }
}
