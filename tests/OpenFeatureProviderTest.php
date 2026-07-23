<?php

namespace Featurevisor\Tests;

use DateTime;
use DateTimeImmutable;
use Featurevisor\Featurevisor;
use Featurevisor\OpenFeatureProvider;
use OpenFeature\OpenFeatureAPI;
use OpenFeature\implementation\flags\Attributes;
use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\interfaces\provider\ErrorCode;
use OpenFeature\interfaces\provider\Reason;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @group openfeature */
final class OpenFeatureProviderTest extends TestCase
{
    /** @return array<string, mixed> */
    private function datafile(): array
    {
        return [
            'schemaVersion' => '2',
            'revision' => 'revision-1',
            'featurevisorVersion' => '3.0.1',
            'segments' => [],
            'features' => [
                'checkout' => [
                    'bucketBy' => 'userId',
                    'variations' => [[
                        'value' => 'on',
                        'variables' => [
                            'title' => 'Hello',
                            'count' => 3,
                            'ratio' => 1.5,
                            'visible' => true,
                            'items' => ['a', 'b'],
                            'config' => ['color' => 'blue'],
                            'json' => '{"nested":true}',
                            'invalidJson' => 'not-json',
                        ],
                    ]],
                    'variablesSchema' => [
                        'title' => ['type' => 'string', 'defaultValue' => 'Default'],
                        'count' => ['type' => 'integer', 'defaultValue' => 0],
                        'ratio' => ['type' => 'double', 'defaultValue' => 0.0],
                        'visible' => ['type' => 'boolean', 'defaultValue' => false],
                        'items' => ['type' => 'array', 'defaultValue' => []],
                        'config' => ['type' => 'object', 'defaultValue' => []],
                        'json' => ['type' => 'json', 'defaultValue' => '{}'],
                        'invalidJson' => ['type' => 'json', 'defaultValue' => '{}'],
                    ],
                    'force' => [[
                        'conditions' => ['attribute' => 'userId', 'operator' => 'equals', 'value' => 'forced-user'],
                        'enabled' => true,
                        'variation' => 'on',
                    ]],
                    'traffic' => [['key' => 'all', 'segments' => '*', 'percentage' => 100000, 'variation' => 'on']],
                ],
                'disabled' => [
                    'bucketBy' => 'userId',
                    'disabledVariationValue' => 'off',
                    'variations' => [['value' => 'on']],
                    'force' => [[
                        'conditions' => ['attribute' => 'blocked', 'operator' => 'equals', 'value' => true],
                        'enabled' => false,
                    ]],
                    'traffic' => [['key' => 'all', 'segments' => '*', 'percentage' => 100000, 'variation' => 'on']],
                ],
                'emptyVariation' => [
                    'bucketBy' => 'userId',
                    'variations' => [],
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $options */
    private function provider(array $options = []): OpenFeatureProvider
    {
        return new OpenFeatureProvider(array_merge([
            'datafile' => $this->datafile(),
            'logLevel' => 'fatal',
        ], $options));
    }

    /**
     * @param array<string, mixed> $evaluation
     * @return array{0: OpenFeatureProvider, 1: Featurevisor}
     */
    private function providerReturning(array $evaluation): array
    {
        $featurevisor = Featurevisor::createFeaturevisor([
            'datafile' => $this->datafile(),
            'logLevel' => 'fatal',
            'modules' => [[
                'name' => 'evaluation-result',
                'after' => static function () use ($evaluation): array {
                    return $evaluation;
                },
            ]],
        ]);

        return [new OpenFeatureProvider(featurevisor: $featurevisor), $featurevisor];
    }

    public function testResolvesFlagsVariationsAndEveryOpenFeatureType(): void
    {
        $provider = $this->provider();
        $context = new EvaluationContext('forced-user', new Attributes());

        $flag = $provider->resolveBooleanValue('checkout', false, $context);
        self::assertTrue($flag->getValue());
        self::assertSame(Reason::TARGETING_MATCH, $flag->getReason());

        $variation = $provider->resolveStringValue('checkout:variation', 'fallback', $context);
        self::assertSame('on', $variation->getValue());
        self::assertSame('on', $variation->getVariant());
        self::assertSame(Reason::TARGETING_MATCH, $variation->getReason());

        self::assertSame('Hello', $provider->resolveStringValue('checkout:title', 'fallback', $context)->getValue());
        self::assertSame(3, $provider->resolveIntegerValue('checkout:count', 0, $context)->getValue());
        self::assertSame(3.0, $provider->resolveFloatValue('checkout:count', 0.0, $context)->getValue());
        self::assertSame(1.5, $provider->resolveFloatValue('checkout:ratio', 0.0, $context)->getValue());
        self::assertTrue($provider->resolveBooleanValue('checkout:visible', false, $context)->getValue());
        self::assertSame(['a', 'b'], $provider->resolveObjectValue('checkout:items', [], $context)->getValue());
        self::assertSame(['color' => 'blue'], $provider->resolveObjectValue('checkout:config', [], $context)->getValue());
        self::assertSame(['nested' => true], $provider->resolveObjectValue('checkout:json', [], $context)->getValue());
        self::assertSame('Featurevisor', $provider->getMetadata()->getName());
    }

    public function testMapsTargetingKeyDatesArraysAndNestedContextWithoutMutation(): void
    {
        $contexts = [];
        $createdAt = new DateTime('2026-01-02T04:04:05.123+01:00');
        $nestedDate = new DateTimeImmutable('2026-01-01T00:00:00.456Z');
        $attributes = [
            'createdAt' => $createdAt,
            'nested' => ['dates' => [$nestedDate]],
        ];
        $provider = new OpenFeatureProvider(
            ['datafile' => $this->datafile(), 'logLevel' => 'fatal', 'modules' => [[
                'name' => 'capture-context',
                'before' => static function (array $options) use (&$contexts): array {
                    $contexts[] = $options['context'];
                    return $options;
                },
            ]]],
            null,
            'accountId'
        );

        $provider->resolveBooleanValue(
            'checkout',
            false,
            new EvaluationContext('subject', new Attributes($attributes))
        );

        self::assertSame([
            'createdAt' => '2026-01-02T03:04:05.123Z',
            'nested' => ['dates' => ['2026-01-01T00:00:00.456Z']],
            'accountId' => 'subject',
        ], $contexts[0]);
        self::assertSame('2026-01-02T04:04:05.123+01:00', $createdAt->format('Y-m-d\TH:i:s.vP'));
        self::assertSame($createdAt, $attributes['createdAt']);
        self::assertSame($nestedDate, $attributes['nested']['dates'][0]);
    }

    public function testSupportsCustomKeySeparatorAndVariationSelector(): void
    {
        $provider = new OpenFeatureProvider(
            ['datafile' => $this->datafile(), 'logLevel' => 'fatal'],
            null,
            'userId',
            '/',
            '$variation'
        );

        self::assertSame('on', $provider->resolveStringValue('checkout/$variation', 'fallback')->getValue());
        self::assertSame('Hello', $provider->resolveStringValue('checkout/title', 'fallback')->getValue());
    }

    public function testReturnsDefaultsAndStandardErrorsForMissingEntitiesAndMalformedDatafiles(): void
    {
        $provider = $this->provider();

        $missingFeature = $provider->resolveBooleanValue('missing', true);
        self::assertTrue($missingFeature->getValue());
        self::assertSame(Reason::ERROR, $missingFeature->getReason());
        self::assertEquals(ErrorCode::FLAG_NOT_FOUND(), $missingFeature->getError()->getResolutionErrorCode());
        self::assertSame('Feature "missing" was not found', $missingFeature->getError()->getResolutionErrorMessage());

        $missingVariable = $provider->resolveStringValue('checkout:missing', 'fallback');
        self::assertSame('fallback', $missingVariable->getValue());
        self::assertEquals(ErrorCode::FLAG_NOT_FOUND(), $missingVariable->getError()->getResolutionErrorCode());

        $noVariations = $provider->resolveStringValue('emptyVariation:variation', 'fallback');
        self::assertSame('fallback', $noVariations->getValue());
        self::assertEquals(ErrorCode::FLAG_NOT_FOUND(), $noVariations->getError()->getResolutionErrorCode());

        $malformed = new OpenFeatureProvider(['datafile' => '{', 'logLevel' => 'fatal']);
        $result = $malformed->resolveBooleanValue('checkout', false);
        self::assertFalse($result->getValue());
        self::assertSame(Reason::ERROR, $result->getReason());
        self::assertEquals(ErrorCode::PARSE_ERROR(), $result->getError()->getResolutionErrorCode());
        self::assertSame('Could not parse datafile', $result->getError()->getResolutionErrorMessage());
    }

    public function testRecoversAfterMalformedDatafileIsReplaced(): void
    {
        $provider = new OpenFeatureProvider(['datafile' => '{', 'logLevel' => 'fatal']);
        self::assertEquals(
            ErrorCode::PARSE_ERROR(),
            $provider->resolveBooleanValue('checkout', false)->getError()->getResolutionErrorCode()
        );

        $provider->getFeaturevisor()->setDatafile($this->datafile(), true);
        $result = $provider->resolveBooleanValue(
            'checkout',
            false,
            new EvaluationContext('forced-user', new Attributes())
        );
        self::assertTrue($result->getValue());
        self::assertNull($result->getError());
    }

    public function testRejectsMismatchedValuesAndInvalidJson(): void
    {
        $provider = $this->provider();
        $results = [
            $provider->resolveStringValue('checkout', 'fallback'),
            $provider->resolveBooleanValue('checkout:title', false),
            $provider->resolveObjectValue('checkout:invalidJson', []),
            $provider->resolveIntegerValue('checkout:ratio', 0),
        ];

        foreach ($results as $result) {
            self::assertSame(Reason::ERROR, $result->getReason());
            self::assertEquals(ErrorCode::TYPE_MISMATCH(), $result->getError()->getResolutionErrorCode());
        }
    }

    /** @dataProvider invalidNumericValues */
    public function testRejectsInvalidNumericValues($value, string $resolver): void
    {
        [$provider, $featurevisor] = $this->providerReturning([
            'type' => 'variable',
            'featureKey' => 'checkout',
            'variableKey' => 'ratio',
            'reason' => 'allocated',
            'variableValue' => $value,
            'variableSchema' => ['type' => 'double'],
        ]);

        $result = $provider->{$resolver}('checkout:ratio', $resolver === 'resolveIntegerValue' ? 0 : 0.0);
        self::assertSame(Reason::ERROR, $result->getReason());
        self::assertEquals(ErrorCode::TYPE_MISMATCH(), $result->getError()->getResolutionErrorCode());

        $provider->shutdown();
        $featurevisor->close();
    }

    /** @return array<string, array{0: mixed, 1: string}> */
    public function invalidNumericValues(): array
    {
        return [
            'NaN as float' => [NAN, 'resolveFloatValue'],
            'positive infinity as float' => [INF, 'resolveFloatValue'],
            'negative infinity as float' => [-INF, 'resolveFloatValue'],
            'boolean as integer' => [true, 'resolveIntegerValue'],
            'boolean as float' => [true, 'resolveFloatValue'],
            'whole float as integer' => [1.0, 'resolveIntegerValue'],
        ];
    }

    public function testMapsDisabledEvaluations(): void
    {
        $provider = $this->provider();
        $context = new EvaluationContext(null, new Attributes(['blocked' => true]));

        $flag = $provider->resolveBooleanValue('disabled', true, $context);
        self::assertFalse($flag->getValue());
        self::assertSame(Reason::TARGETING_MATCH, $flag->getReason());

        $variation = $provider->resolveStringValue('disabled:variation', 'fallback', $context);
        self::assertSame('off', $variation->getValue());
        self::assertSame(Reason::DISABLED, $variation->getReason());
    }

    /** @dataProvider reasonMappings */
    public function testMapsEveryFeaturevisorReason(string $featurevisorReason, string $expectedReason): void
    {
        [$provider, $featurevisor] = $this->providerReturning([
            'type' => 'flag',
            'featureKey' => 'checkout',
            'reason' => $featurevisorReason,
            'enabled' => true,
        ]);

        $result = $provider->resolveBooleanValue('checkout', false);
        self::assertSame($expectedReason, $result->getReason());
        self::assertNull($result->getError());

        $provider->shutdown();
        $featurevisor->close();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public function reasonMappings(): array
    {
        return [
            'required' => ['required', Reason::TARGETING_MATCH],
            'forced' => ['forced', Reason::TARGETING_MATCH],
            'sticky' => ['sticky', Reason::TARGETING_MATCH],
            'rule' => ['rule', Reason::TARGETING_MATCH],
            'variation override' => ['variable_override_variation', Reason::TARGETING_MATCH],
            'rule override' => ['variable_override_rule', Reason::TARGETING_MATCH],
            'allocated' => ['allocated', Reason::SPLIT],
            'disabled' => ['disabled', Reason::DISABLED],
            'variation disabled' => ['variation_disabled', Reason::DISABLED],
            'variable disabled' => ['variable_disabled', Reason::DISABLED],
            'out of range' => ['out_of_range', Reason::DEFAULT],
            'no match' => ['no_match', Reason::DEFAULT],
            'variable default' => ['variable_default', Reason::DEFAULT],
        ];
    }

    /** @dataProvider generalErrors */
    public function testMapsGeneralEvaluationErrors($error, string $expectedMessage): void
    {
        [$provider, $featurevisor] = $this->providerReturning([
            'type' => 'flag',
            'featureKey' => 'checkout',
            'reason' => 'error',
            'error' => $error,
        ]);

        $result = $provider->resolveBooleanValue('checkout', false);
        self::assertFalse($result->getValue());
        self::assertSame(Reason::ERROR, $result->getReason());
        self::assertEquals(ErrorCode::GENERAL(), $result->getError()->getResolutionErrorCode());
        self::assertSame($expectedMessage, $result->getError()->getResolutionErrorMessage());

        $provider->shutdown();
        $featurevisor->close();
    }

    /** @return array<string, array{0: mixed, 1: string}> */
    public function generalErrors(): array
    {
        return [
            'throwable' => [new RuntimeException('Evaluation failed'), 'Evaluation failed'],
            'message string' => ['Evaluation failed as text', 'Evaluation failed as text'],
        ];
    }

    public function testForwardsTrackingArguments(): void
    {
        $tracked = [];
        $provider = new OpenFeatureProvider(
            ['datafile' => $this->datafile(), 'logLevel' => 'fatal'],
            null,
            'userId',
            ':',
            'variation',
            static function (...$arguments) use (&$tracked): void {
                $tracked[] = $arguments;
            }
        );
        $context = new EvaluationContext('user-1', new Attributes());
        $details = ['value' => 10, 'orderId' => '1'];

        $provider->track('purchase', $context, $details);
        self::assertSame([['purchase', $context, $details]], $tracked);
    }

    public function testClosesOwnedFeaturevisorExactlyOnce(): void
    {
        $closed = 0;
        $provider = $this->provider([
            'modules' => [[
                'name' => 'lifecycle',
                'close' => static function () use (&$closed): void {
                    $closed++;
                },
            ]],
        ]);

        $provider->shutdown();
        $provider->shutdown();
        self::assertSame(1, $closed);
    }

    public function testBorrowsExistingFeaturevisorAndExistingInstanceTakesPrecedence(): void
    {
        $closed = 0;
        $featurevisor = Featurevisor::createFeaturevisor([
            'datafile' => $this->datafile(),
            'logLevel' => 'fatal',
            'modules' => [[
                'name' => 'owner',
                'close' => static function () use (&$closed): void {
                    $closed++;
                },
            ]],
        ]);
        $provider = new OpenFeatureProvider(['datafile' => '{'], $featurevisor);

        self::assertSame($featurevisor, $provider->getFeaturevisor());
        self::assertTrue($provider->resolveBooleanValue(
            'checkout',
            false,
            new EvaluationContext('forced-user', new Attributes())
        )->getValue());

        $provider->shutdown();
        $provider->shutdown();
        self::assertSame(0, $closed);

        $featurevisor->setDatafile(array_merge($this->datafile(), ['features' => []]), true);
        self::assertSame('feature_not_found', $featurevisor->evaluateFlag('checkout')['reason']);
        $featurevisor->close();
        self::assertSame(1, $closed);
    }

    public function testWorksThroughOpenFeatureApiForAllNumericTypes(): void
    {
        $provider = $this->provider();
        $api = OpenFeatureAPI::getInstance();
        $api->setProvider($provider);
        $client = $api->getClient(null, null);
        $context = new EvaluationContext('forced-user', new Attributes());

        self::assertTrue($client->getBooleanValue('checkout', false, $context));
        self::assertSame(3, $client->getIntegerValue('checkout:count', 0, $context));
        self::assertSame(3.0, $client->getFloatValue('checkout:count', 0.0, $context));
        self::assertSame(1.5, $client->getFloatValue('checkout:ratio', 0.0, $context));

        $provider->shutdown();
    }
}
