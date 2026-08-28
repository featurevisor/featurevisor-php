<?php

namespace Featurevisor\Tests;

use Featurevisor\Conditions;
use Featurevisor\Bucketer;
use Featurevisor\Featurevisor;
use Featurevisor\Helpers;
use PHPUnit\Framework\TestCase;

final class InstanceDatafileTest extends TestCase
{
    /** @return array<string, mixed> */
    private function fixture(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../conformance/sdk-v3.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function datafile(array $segments = [], array $features = []): array
    {
        return [
            'schemaVersion' => '2',
            'revision' => '1',
            'segments' => $segments,
            'features' => $features,
        ];
    }

    public function testSharedV3ConformanceFixture(): void
    {
        $fixture = $this->fixture();
        self::assertSame(5, $fixture['version']);

        $bucketValue = 0;
        $featurevisor = Featurevisor::createFeaturevisor([
            'logLevel' => 'fatal',
            'modules' => [[
                'name' => 'fixed-bucket',
                'bucketValue' => static function () use (&$bucketValue): int {
                    return $bucketValue;
                },
            ]],
            'datafile' => $this->datafile([], [
                'test' => [
                    'key' => 'test',
                    'bucketBy' => 'userId',
                    'variations' => [['value' => 'control'], ['value' => 'treatment']],
                    'traffic' => [[
                        'key' => 'everyone',
                        'segments' => '*',
                        'percentage' => 100000,
                        'allocation' => $fixture['bucketing']['allocations'],
                    ]],
                ],
            ]),
        ]);

        foreach ($fixture['bucketing']['allocationExpectations'] as $bucket => $expected) {
            $bucketValue = (int) $bucket;
            self::assertSame($expected, $featurevisor->getVariation('test', ['userId' => 'user']));
        }

        $condition = [
            'attribute' => 'browser',
            'operator' => 'matches',
            'value' => $fixture['regularExpressions']['pattern'],
            'regexFlags' => $fixture['regularExpressions']['flags'],
        ];
        $regex = static fn(string $pattern, string $flags): string => '~'.$pattern.'~'.str_replace(['g', 'y'], '', $flags);
        foreach ($fixture['regularExpressions']['values'] as $index => $value) {
            self::assertSame(
                $fixture['regularExpressions']['matches'][$index],
                Conditions::conditionIsMatched($condition, ['browser' => $value], $regex)
            );
        }
        foreach ($fixture['regularExpressions']['portableCases'] as $testCase) {
            $portableCondition = [
                'attribute' => 'value',
                'operator' => 'matches',
                'value' => $testCase['pattern'],
                'regexFlags' => $testCase['flags'],
            ];
            self::assertSame(
                $testCase['expected'],
                Conditions::conditionIsMatched(
                    $portableCondition,
                    ['value' => $testCase['value']],
                    $regex
                ),
                sprintf('pattern %s, flags %s', $testCase['pattern'], $testCase['flags'])
            );
        }
        foreach ($fixture['conditionCases'] as $testCase) {
            self::assertSame(
                $testCase['expected'],
                Conditions::allConditionsAreMatched($testCase['condition'], $testCase['context'], $regex),
                $testCase['name']
            );
        }

        $aggregateCase = $fixture['defaults']['aggregateCase'];
        $aggregateFeaturevisor = Featurevisor::createFeaturevisor([
            'logLevel' => 'fatal',
            'datafile' => $aggregateCase['datafile'],
        ]);
        $evaluated = $aggregateFeaturevisor->getAllEvaluations(
            [],
            [],
            ['defaultVariationValue' => $aggregateCase['defaultVariationValue']]
        )['experiment'];
        self::assertSame($aggregateCase['expected']['enabled'], $evaluated['enabled']);
        self::assertSame($aggregateCase['expected']['variation'], $evaluated['variation']);

        foreach ($fixture['typedVariables'] as $typedVariable) {
            $actual = Helpers::getValueByType($typedVariable['value'], $typedVariable['type']);
            self::assertSame($typedVariable['valid'], $actual !== null);
        }

        foreach ($fixture['numericBucketKeys'] as $testCase) {
            self::assertSame(
                $testCase['expected'].'.feature',
                Bucketer::getBucketKey([
                    'featureKey' => 'feature',
                    'bucketBy' => 'value',
                    'context' => ['value' => $testCase['value']],
                ])
            );
        }

        $diagnostics = [];
        $schemaFeaturevisor = Featurevisor::createFeaturevisor([
            'logLevel' => 'debug',
            'onDiagnostic' => static function (array $diagnostic) use (&$diagnostics): void {
                $diagnostics[] = $diagnostic;
            },
            'datafile' => array_merge($this->datafile(), ['schemaVersion' => 'informational']),
        ]);
        self::assertSame('informational', $schemaFeaturevisor->getSchemaVersion());
        foreach ($diagnostics as $diagnostic) {
            foreach ($fixture['diagnostics']['requiredFields'] as $field) {
                self::assertArrayHasKey($field, $diagnostic);
            }
        }
        $initialized = array_values(array_filter($diagnostics, static fn(array $diagnostic): bool => $diagnostic['code'] === 'sdk_initialized'))[0];
        self::assertSame('{}', json_encode($initialized['details']));
    }

    public function testGlobalVariablesAndRequiredFeaturesConformance(): void
    {
        $fixture = $this->fixture();
        $globals = $fixture['globalVariables'];
        foreach ($globals['cases'] as $case) {
            $f = Featurevisor::createFeaturevisor([
                'datafile' => $globals['datafile'],
                'stickyVariables' => $case['stickyVariables'] ?? [],
                'logLevel' => 'fatal',
            ]);
            $options = [];
            if (array_key_exists('defaultVariableValue', $case)) $options['defaultVariableValue'] = $case['defaultVariableValue'];
            $evaluation = $f->evaluateVariable($case['key'], $case['context'] ?? [], $options);
            self::assertSame($case['expectedValue'] ?? null, $evaluation['variableValue'] ?? null, $case['name']);
            self::assertSame($case['expectedReason'], $evaluation['reason'], $case['name']);
            self::assertSame($case['expectedOverrideIndex'] ?? null, $evaluation['variableOverrideIndex'] ?? null, $case['name']);
            self::assertSame($case['expectedOverrideKey'] ?? null, $evaluation['variableOverrideKey'] ?? null, $case['name']);
            self::assertSame($case['expectedOverridePath'] ?? null, $evaluation['variableOverridePath'] ?? null, $case['name']);
        }
        $overload = $globals['overloadCase'];
        $f = Featurevisor::createFeaturevisor(['datafile' => $globals['datafile'], 'logLevel' => 'fatal']);
        self::assertSame($overload['expectedGlobalValue'], $f->getVariable($overload['sharedKey']));
        self::assertSame($overload['expectedFeatureValue'], $f->getVariable($overload['sharedKey'], $overload['featureVariableKey']));

        $required = $fixture['requiredFeatures'];
        $f = Featurevisor::createFeaturevisor(['datafile' => $required['datafile'], 'logLevel' => 'fatal']);
        foreach ($required['cases'] as $case) self::assertSame($case['expectedEnabled'], $f->isEnabled($case['feature']), $case['name']);
        $case = $required['featureVariableCase'];
        $evaluation = $f->evaluateVariable($case['feature'], $case['variable']);
        self::assertSame($case['expectedValue'], $evaluation['variableValue']);
        self::assertSame($case['expectedOverrideKey'], $evaluation['variableOverrideKey']);
    }

    public function testGlobalVariableChildrenModulesAndDependencyEvents(): void
    {
        $globals = $this->fixture()['globalVariables'];
        $observed = [];
        $f = Featurevisor::createFeaturevisor([
            'datafile' => $globals['datafile'],
            'stickyVariables' => ['stringValue' => 'parent'],
            'modules' => [[
                'name' => 'unified',
                'beforeEvaluation' => static function(array $options) use (&$observed): array { $observed[] = 'before'; return $options; },
                'afterEvaluation' => static function(array $evaluation) use (&$observed): array { $observed[] = 'after'; return $evaluation; },
            ]],
            'logLevel' => 'fatal',
        ]);
        self::assertSame('parent', $f->getVariable('stringValue'));
        self::assertSame(['before', 'after'], $observed);
        $child = $f->spawn([], ['stickyVariables' => ['stringValue' => 'child']]);
        self::assertSame('child', $child->getVariable('stringValue'));
        self::assertSame(['integerValue' => 1], $child->getVariableEvaluations([], ['integerValue']));

        $dependency = $globals['dependencyUpdateCase'];
        foreach ($dependency['modes'] as $mode) {
            $f = Featurevisor::createFeaturevisor(['datafile' => $dependency['initial'], 'logLevel' => 'fatal']);
            $events = [];
            $f->on('datafile_set', static function(array $event) use (&$events): void { $events[] = $event; });
            $f->setDatafile($dependency['updated'], $mode['replace']);
            sort($events[0]['features']);
            sort($events[0]['variables']);
            self::assertSame($dependency['expectedChangedFeatures'], $events[0]['features']);
            self::assertSame($dependency['expectedChangedVariables'], $events[0]['variables']);
        }
    }

    public function testDatafileAccessLivesOnFeaturevisorInstance(): void
    {
        $datafile = $this->datafile([
            'germany' => [
                'key' => 'germany',
                'conditions' => json_encode([['attribute' => 'country', 'operator' => 'equals', 'value' => 'de']]),
            ],
        ], [
            'test' => [
                'key' => 'test',
                'bucketBy' => 'userId',
                'traffic' => [],
            ],
        ]);
        $featurevisor = Featurevisor::createFeaturevisor(['logLevel' => 'fatal', 'datafile' => $datafile]);

        self::assertSame('1', $featurevisor->getRevision());
        self::assertSame('2', $featurevisor->getSchemaVersion());
        self::assertSame('de', $featurevisor->getSegment('germany')['conditions'][0]['value']);
        self::assertNull($featurevisor->getSegment('belgium'));
        self::assertSame($datafile['features']['test'], $featurevisor->getFeature('test'));
        self::assertNull($featurevisor->getFeature('missing'));
        self::assertFalse(class_exists('Featurevisor\\Internal\\DatafileReader'));
    }

    public function testSegmentExpressionsMatchJavaScriptSemantics(): void
    {
        $segments = [
            'mobile' => ['conditions' => [['attribute' => 'device', 'operator' => 'equals', 'value' => 'mobile']]],
            'dutch' => ['conditions' => [['attribute' => 'country', 'operator' => 'equals', 'value' => 'nl']]],
        ];
        $getSegment = static fn(string $key): ?array => $segments[$key] ?? null;
        $getRegex = static fn(string $pattern, string $flags): string => '~'.$pattern.'~'.str_replace(['g', 'y'], '', $flags);

        self::assertFalse(Conditions::allSegmentsAreMatched(['not' => ['mobile', 'dutch']], ['device' => 'mobile', 'country' => 'nl'], $getSegment, $getRegex));
        self::assertTrue(Conditions::allSegmentsAreMatched(['not' => ['mobile', 'dutch']], ['device' => 'desktop', 'country' => 'nl'], $getSegment, $getRegex));
        self::assertFalse(Conditions::allSegmentsAreMatched(['not' => []], [], $getSegment, $getRegex));
        self::assertFalse(Conditions::allSegmentsAreMatched('missing', [], $getSegment, $getRegex));
    }
}
