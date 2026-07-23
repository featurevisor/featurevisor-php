<?php

namespace Featurevisor\Tests;

use Featurevisor\Conditions;
use Featurevisor\Featurevisor;
use Featurevisor\Helpers;
use PHPUnit\Framework\TestCase;

final class JavaScriptAlignmentTest extends TestCase
{
    /** @return array<string, mixed> */
    private function datafile(array $features = [], array $segments = [], string $revision = '1'): array
    {
        return [
            'schemaVersion' => '2',
            'revision' => $revision,
            'segments' => $segments,
            'features' => $features,
        ];
    }

    /** @return array<string, mixed> */
    private function feature(array $overrides = []): array
    {
        return array_merge([
            'key' => 'test',
            'bucketBy' => 'userId',
            'variations' => [['value' => 'control']],
            'variablesSchema' => [],
            'traffic' => [[
                'key' => 'everyone',
                'segments' => '*',
                'percentage' => 100000,
                'allocation' => [['variation' => 'control', 'range' => [0, 100000]]],
            ]],
        ], $overrides);
    }

    public function testDefaultValuesOnlyFillMissingEvaluationValues(): void
    {
        $featurevisor = Featurevisor::createFeaturevisor([
            'logLevel' => 'fatal',
            'datafile' => $this->datafile(['test' => $this->feature()]),
        ]);

        $variation = $featurevisor->evaluateVariation('test', ['userId' => '1'], ['defaultVariationValue' => 'fallback']);
        self::assertSame('control', $variation['variation']['value']);
        self::assertArrayNotHasKey('variationValue', $variation);

        $missing = $featurevisor->evaluateVariation('missing', [], ['defaultVariationValue' => null]);
        self::assertArrayHasKey('variationValue', $missing);
        self::assertNull($missing['variationValue']);
    }

    public function testNullValuesArePreservedAcrossStickyForceAndDisabledPaths(): void
    {
        $feature = $this->feature([
            'force' => [['conditions' => '*', 'variables' => ['forced' => null], 'enabled' => true]],
            'variablesSchema' => [
                'forced' => ['type' => 'json', 'defaultValue' => ['fallback']],
            ],
        ]);
        $featurevisor = Featurevisor::createFeaturevisor([
            'logLevel' => 'fatal',
            'datafile' => $this->datafile(['test' => $feature]),
            'sticky' => ['test' => ['variables' => ['forced' => null]]],
        ]);

        $sticky = $featurevisor->evaluateVariable('test', 'forced');
        self::assertSame('sticky', $sticky['reason']);
        self::assertArrayHasKey('variableValue', $sticky);
        self::assertNull($sticky['variableValue']);

        $withoutSticky = Featurevisor::createFeaturevisor(['logLevel' => 'fatal', 'datafile' => $this->datafile(['test' => $feature])]);
        $forced = $withoutSticky->evaluateVariable('test', 'forced');
        self::assertSame('forced', $forced['reason']);
        self::assertArrayHasKey('variableValue', $forced);
        self::assertNull($forced['variableValue']);

        $disabledFeature = $this->feature([
            'force' => [['conditions' => '*', 'enabled' => false]],
            'variablesSchema' => [
                'disabled' => ['type' => 'json', 'defaultValue' => ['fallback'], 'disabledValue' => null],
            ],
        ]);
        $disabledFeaturevisor = Featurevisor::createFeaturevisor([
            'logLevel' => 'fatal',
            'datafile' => $this->datafile(['test' => $disabledFeature]),
        ]);
        $disabled = $disabledFeaturevisor->evaluateVariable('test', 'disabled');
        self::assertSame('variable_disabled', $disabled['reason']);
        self::assertArrayHasKey('variableValue', $disabled);
        self::assertNull($disabled['variableValue']);
    }

    public function testConditionTypingAndMissingValuesMatchJavaScript(): void
    {
        $regex = static fn(string $pattern, string $flags): string => '~'.$pattern.'~'.str_replace(['g', 'y'], '', $flags);

        self::assertFalse(Conditions::conditionIsMatched(['attribute' => 'age', 'operator' => 'greaterThan', 'value' => 1], ['age' => '2'], $regex));
        self::assertFalse(Conditions::conditionIsMatched(['attribute' => 'value', 'operator' => 'in', 'value' => [1]], ['value' => '1'], $regex));
        self::assertFalse(Conditions::conditionIsMatched(['attribute' => 'missing', 'operator' => 'equals', 'value' => null], [], $regex));
        self::assertTrue(Conditions::conditionIsMatched(['attribute' => 'present', 'operator' => 'equals', 'value' => null], ['present' => null], $regex));
        self::assertTrue(Conditions::conditionIsMatched(['attribute' => 'values', 'operator' => 'includes', 'value' => false], ['values' => [false, null]], $regex));
        self::assertTrue(Conditions::conditionIsMatched(['attribute' => 'name', 'operator' => 'endsWith', 'value' => ''], ['name' => 'Featurevisor'], $regex));
    }

    public function testMalformedExpressionsReportDiagnosticsAndFailSafely(): void
    {
        $diagnostics = [];
        $feature = $this->feature([
            'force' => [['conditions' => '{bad', 'enabled' => true]],
            'traffic' => [[
                'key' => 'bad',
                'segments' => '{bad',
                'percentage' => 100000,
            ]],
        ]);
        $featurevisor = Featurevisor::createFeaturevisor([
            'logLevel' => 'debug',
            'onDiagnostic' => static function (array $diagnostic) use (&$diagnostics): void {
                $diagnostics[] = $diagnostic;
            },
            'datafile' => $this->datafile(['test' => $feature]),
        ]);

        $evaluation = $featurevisor->evaluateFlag('test', ['userId' => '1']);
        self::assertSame('error', $evaluation['reason']);
        self::assertInstanceOf(\Throwable::class, $evaluation['error']);
        self::assertContains('conditions_parse_error', array_column($diagnostics, 'code'));
        self::assertContains('evaluation_error', array_column($diagnostics, 'code'));
    }

    public function testTypedArrayAndObjectGettersDoNotOverlap(): void
    {
        self::assertSame(['one'], Helpers::getValueByType(['one'], 'array'));
        self::assertNull(Helpers::getValueByType(['one'], 'object'));
        self::assertSame(['key' => 'value'], Helpers::getValueByType(['key' => 'value'], 'object'));
        self::assertNull(Helpers::getValueByType(['key' => 'value'], 'array'));
    }

    public function testGetAllEvaluationsDoesNotCacheNestedFlagEvaluations(): void
    {
        $beforeCalls = 0;
        $evaluationDiagnostics = 0;
        $feature = $this->feature([
            'variablesSchema' => [
                'one' => ['type' => 'string', 'defaultValue' => 'one'],
                'two' => ['type' => 'string', 'defaultValue' => 'two'],
            ],
        ]);
        $featurevisor = Featurevisor::createFeaturevisor([
            'logLevel' => 'debug',
            'onDiagnostic' => static function (array $diagnostic) use (&$evaluationDiagnostics): void {
                if (is_array($diagnostic['details']) && isset($diagnostic['details']['evaluation'])) {
                    $evaluationDiagnostics++;
                }
            },
            'modules' => [[
                'name' => 'counter',
                'before' => static function (array $options) use (&$beforeCalls): array {
                    $beforeCalls++;
                    return $options;
                },
            ]],
            'datafile' => $this->datafile(['test' => $feature]),
        ]);

        $featurevisor->getAllEvaluations(['userId' => '1']);
        self::assertSame(4, $beforeCalls);
        self::assertSame(7, $evaluationDiagnostics);
    }

    public function testClosedInstanceDoesNotAcceptNewEventListeners(): void
    {
        $called = false;
        $featurevisor = Featurevisor::createFeaturevisor(['logLevel' => 'fatal']);
        $featurevisor->close();
        $unsubscribe = $featurevisor->on('context_set', static function () use (&$called): void {
            $called = true;
        });
        $featurevisor->setContext(['country' => 'nl']);
        $unsubscribe();

        self::assertFalse($called);
    }
}
