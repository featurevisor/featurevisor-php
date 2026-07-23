<?php

namespace Featurevisor\Tests;

use Featurevisor\Featurevisor;
use PHPUnit\Framework\TestCase;

class DiagnosticContractTest extends TestCase
{
    public function testLoggerClassesAreAbsent(): void
    {
        self::assertFalse(class_exists('Featurevisor\\Logger'));
        self::assertFalse(class_exists('Featurevisor\\Internal\\Logger'));
    }

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

    public function testEvaluationsReportStructuredDiagnosticsDirectly(): void
    {
        $diagnostics = [];
        $sdk = Featurevisor::createFeaturevisor([
            'logLevel' => 'debug',
            'onDiagnostic' => static function (array $diagnostic) use (&$diagnostics): void {
                $diagnostics[] = $diagnostic;
            },
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1',
                'segments' => [],
                'features' => [],
            ],
        ]);

        self::assertFalse($sdk->isEnabled('missing', ['userId' => 'user-1']));

        $diagnostic = array_values(array_filter(
            $diagnostics,
            static fn (array $item): bool => $item['code'] === 'feature_not_found'
        ))[0];

        self::assertSame('warn', $diagnostic['level']);
        self::assertSame('Feature not found', $diagnostic['message']);
        self::assertSame('missing', $diagnostic['details']['featureKey']);
        self::assertSame('feature_not_found', $diagnostic['details']['reason']);
        self::assertSame('flag', $diagnostic['details']['evaluation']['type']);
    }

    public function testInvalidBucketByReportsDiagnosticsWithoutLoggerInfrastructure(): void
    {
        $diagnostics = [];
        $sdk = Featurevisor::createFeaturevisor([
            'logLevel' => 'debug',
            'onDiagnostic' => static function (array $diagnostic) use (&$diagnostics): void {
                $diagnostics[] = $diagnostic;
            },
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1',
                'segments' => [],
                'features' => [
                    'invalid' => [
                        'key' => 'invalid',
                        'bucketBy' => true,
                        'traffic' => [],
                    ],
                ],
            ],
        ]);

        self::assertFalse($sdk->isEnabled('invalid', []));
        self::assertContains('invalid_bucket_by', array_column($diagnostics, 'code'));
        self::assertContains('evaluation_error', array_column($diagnostics, 'code'));
    }

    public function testInvalidDiagnosticLevelIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Featurevisor::createFeaturevisor(['logLevel' => 'notice']);
    }
}
