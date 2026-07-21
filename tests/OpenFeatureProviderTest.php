<?php

namespace Featurevisor\Tests;

use Featurevisor\Featurevisor;
use Featurevisor\OpenFeatureProvider;
use OpenFeature\OpenFeatureAPI;
use OpenFeature\implementation\flags\Attributes;
use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\interfaces\provider\ErrorCode;
use PHPUnit\Framework\TestCase;

final class OpenFeatureProviderTest extends TestCase
{
    /** @return array<string, mixed> */
    private function datafile(): array
    {
        return [
            'schemaVersion' => '2', 'revision' => 'openfeature-test', 'segments' => [],
            'features' => [
                'checkout' => [
                    'bucketBy' => 'userId',
                    'variations' => [[
                        'value' => 'on',
                        'variables' => [
                            'title' => 'Hello', 'count' => 3, 'ratio' => 1.5, 'visible' => true,
                            'items' => ['a'], 'config' => ['color' => 'blue'], 'json' => '{"nested":true}',
                        ],
                    ]],
                    'variablesSchema' => [
                        'title' => ['type' => 'string', 'defaultValue' => 'Default'],
                        'count' => ['type' => 'integer', 'defaultValue' => 0],
                        'ratio' => ['type' => 'double', 'defaultValue' => 0],
                        'visible' => ['type' => 'boolean', 'defaultValue' => false],
                        'items' => ['type' => 'array', 'defaultValue' => []],
                        'config' => ['type' => 'object', 'defaultValue' => []],
                        'json' => ['type' => 'json', 'defaultValue' => '{}'],
                    ],
                    'force' => [[
                        'conditions' => ['attribute' => 'userId', 'operator' => 'equals', 'value' => 'forced-user'],
                        'enabled' => true, 'variation' => 'on',
                    ]],
                    'traffic' => [['key' => 'all', 'segments' => '*', 'percentage' => 100000, 'variation' => 'on']],
                ],
            ],
        ];
    }

    public function testResolvesEveryTypeAndMapsTargetingKey(): void
    {
        $provider = new OpenFeatureProvider(['datafile' => $this->datafile(), 'logLevel' => 'fatal']);
        $context = new EvaluationContext('forced-user', new Attributes());
        self::assertTrue($provider->resolveBooleanValue('checkout', false, $context)->getValue());
        self::assertSame('on', $provider->resolveStringValue('checkout:variation', 'fallback', $context)->getValue());
        self::assertSame('Hello', $provider->resolveStringValue('checkout:title', 'fallback', $context)->getValue());
        self::assertSame(3, $provider->resolveIntegerValue('checkout:count', 0, $context)->getValue());
        self::assertSame(1.5, $provider->resolveFloatValue('checkout:ratio', 0.0, $context)->getValue());
        self::assertTrue($provider->resolveBooleanValue('checkout:visible', false, $context)->getValue());
        self::assertSame(['a'], $provider->resolveObjectValue('checkout:items', [], $context)->getValue());
        self::assertSame(['color' => 'blue'], $provider->resolveObjectValue('checkout:config', [], $context)->getValue());
        self::assertSame(['nested' => true], $provider->resolveObjectValue('checkout:json', [], $context)->getValue());
    }

    public function testErrorsGrammarTrackingAndLifecycle(): void
    {
        $tracked = [];
        $provider = new OpenFeatureProvider(
            ['datafile' => $this->datafile(), 'logLevel' => 'fatal'],
            null,
            'userId',
            '/',
            '$variation',
            function (...$args) use (&$tracked): void { $tracked[] = $args; }
        );
        self::assertSame('on', $provider->resolveStringValue('checkout/$variation', 'fallback')->getValue());
        self::assertEquals(ErrorCode::TYPE_MISMATCH(), $provider->resolveStringValue('missing', 'fallback')->getError()->getResolutionErrorCode());
        $missing = $provider->resolveBooleanValue('missing', true);
        self::assertTrue($missing->getValue());
        self::assertEquals(ErrorCode::FLAG_NOT_FOUND(), $missing->getError()->getResolutionErrorCode());
        $provider->track('purchase');
        self::assertSame('purchase', $tracked[0][0]);
        $provider->shutdown();
    }

    public function testMalformedDatafileReportsParseError(): void
    {
        $provider = new OpenFeatureProvider(['datafile' => '{', 'logLevel' => 'fatal']);
        $result = $provider->resolveBooleanValue('checkout', false);
        self::assertEquals(ErrorCode::PARSE_ERROR(), $result->getError()->getResolutionErrorCode());
        self::assertSame('Could not parse datafile', $result->getError()->getResolutionErrorMessage());
        $provider->getFeaturevisor()->setDatafile($this->datafile(), true);
        self::assertTrue($provider->resolveBooleanValue('checkout', false, new EvaluationContext('forced-user', new Attributes()))->getValue());
    }

    public function testWorksThroughOpenFeatureApi(): void
    {
        $api = OpenFeatureAPI::getInstance();
        $api->setProvider(new OpenFeatureProvider(['datafile' => $this->datafile(), 'logLevel' => 'fatal']));
        self::assertTrue($api->getClient(null, null)->getBooleanValue(
            'checkout',
            false,
            new EvaluationContext('forced-user', new Attributes())
        ));
    }

    public function testBorrowsExistingFeaturevisor(): void
    {
        $lifecycle = new class {
            public bool $closed = false;

            public function close(): void
            {
                $this->closed = true;
            }
        };
        $featurevisor = Featurevisor::createFeaturevisor([
            'datafile' => $this->datafile(),
            'logLevel' => 'fatal',
            'modules' => [[
                'name' => 'owner',
                'close' => [$lifecycle, 'close'],
            ]],
        ]);
        $provider = new OpenFeatureProvider(featurevisor: $featurevisor);

        self::assertSame($featurevisor, $provider->getFeaturevisor());
        $provider->shutdown();
        self::assertFalse($lifecycle->closed);

        $featurevisor->close();
        self::assertTrue($lifecycle->closed);
    }
}
