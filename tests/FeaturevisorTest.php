<?php

namespace Featurevisor\Tests;

use Featurevisor\Featurevisor;
use Featurevisor\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class FeaturevisorTest extends TestCase
{
    public function testV3FactoryIsTheOnlyFactory()
    {
        self::assertTrue(method_exists(Featurevisor::class, 'createFeaturevisor'));
        self::assertFalse(method_exists(Featurevisor::class, 'createInstance'));
    }

    public function testShouldCreateInstanceWithDatafileContent()
    {
        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [],
                'segments' => [],
            ],
        ]);

        self::assertTrue(method_exists($sdk, 'getVariation'));
        self::assertSame('2', $sdk->getSchemaVersion());
        self::assertSame([], $sdk->getFeatureKeys());
    }

    public function testShouldReportLifecycleMutationDiagnostics()
    {
        $diagnostics = [];
        $sdk = Featurevisor::createFeaturevisor([
            'logLevel' => LogLevel::DEBUG,
            'onDiagnostic' => function(array $diagnostic) use (&$diagnostics) {
                $diagnostics[] = $diagnostic;
            },
        ]);

        $sdk->setDatafile([
            'schemaVersion' => '2',
            'revision' => '1',
            'segments' => [],
            'features' => [],
        ]);
        $sdk->setSticky(['test' => ['enabled' => true]]);
        $sdk->setContext(['country' => 'nl']);

        $codes = array_column($diagnostics, 'code');
        self::assertContains('datafile_set', $codes);
        self::assertContains('sticky_set', $codes);
        self::assertContains('context_set', $codes);
    }

    public function testShouldCreateInstanceWithLogLevel()
    {
        $diagnostics = [];
        $sdk = Featurevisor::createFeaturevisor([
            'logLevel' => LogLevel::DEBUG,
            'onDiagnostic' => function (array $diagnostic) use (&$diagnostics) {
                $diagnostics[] = $diagnostic;
            },
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [],
                'segments' => [],
            ],
        ]);

        $sdk->setContext(['userId' => '123']);

        self::assertContains('context_set', array_column($diagnostics, 'code'));
    }

    public function testShouldSetLogLevelAfterInitialization()
    {
        $diagnostics = [];
        $sdk = Featurevisor::createFeaturevisor([
            'logLevel' => LogLevel::ERROR,
            'onDiagnostic' => function (array $diagnostic) use (&$diagnostics) {
                $diagnostics[] = $diagnostic;
            },
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [],
                'segments' => [],
            ],
        ]);

        $sdk->setContext(['userId' => '123']);
        self::assertNotContains('context_set', array_column($diagnostics, 'code'));

        $sdk->setLogLevel(LogLevel::DEBUG);
        $sdk->setContext(['country' => 'nl']);
        self::assertContains('context_set', array_column($diagnostics, 'code'));
    }

    public function testShouldConfigurePlainBucketBy()
    {
        $capturedBucketKey = '';

        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'test' => [
                        'key' => 'test',
                        'bucketBy' => 'userId',
                        'variations' => [['value' => 'control'], ['value' => 'treatment']],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [
                                    ['variation' => 'control', 'range' => [0, 100000]],
                                    ['variation' => 'treatment', 'range' => [0, 0]],
                                ],
                            ],
                        ],
                    ],
                ],
                'segments' => [],
            ],
            'modules' => [
                [
                    'name' => 'unit-test',
                    'bucketKey' => function($options) use (&$capturedBucketKey) {
                        $capturedBucketKey = $options['bucketKey'];
                        return $options['bucketKey'];
                    },
                ],
            ],
        ]);

        $featureKey = 'test';
        $context = [
            'userId' => '123',
        ];

        self::assertTrue($sdk->isEnabled($featureKey, $context));
        self::assertEquals('control', $sdk->getVariation($featureKey, $context));
        self::assertEquals('123.test', $capturedBucketKey);
    }

    public function testShouldConfigureAndBucketBy()
    {
        $capturedBucketKey = '';

        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'test' => [
                        'key' => 'test',
                        'bucketBy' => ['userId', 'organizationId'],
                        'variations' => [['value' => 'control'], ['value' => 'treatment']],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [
                                    ['variation' => 'control', 'range' => [0, 100000]],
                                    ['variation' => 'treatment', 'range' => [0, 0]],
                                ],
                            ],
                        ],
                    ],
                ],
                'segments' => [],
            ],
            'modules' => [
                [
                    'name' => 'unit-test',
                    'bucketKey' => function($options) use (&$capturedBucketKey) {
                        $capturedBucketKey = $options['bucketKey'];
                        return $options['bucketKey'];
                    },
                ],
            ],
        ]);

        $featureKey = 'test';
        $context = [
            'userId' => '123',
            'organizationId' => '456',
        ];

        self::assertEquals('control', $sdk->getVariation($featureKey, $context));
        self::assertEquals('123.456.test', $capturedBucketKey);
    }

    public function testShouldConfigureOrBucketBy()
    {
        $capturedBucketKey = '';

        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'test' => [
                        'key' => 'test',
                        'bucketBy' => ['or' => ['userId', 'deviceId']],
                        'variations' => [['value' => 'control'], ['value' => 'treatment']],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [
                                    ['variation' => 'control', 'range' => [0, 100000]],
                                    ['variation' => 'treatment', 'range' => [0, 0]],
                                ],
                            ],
                        ],
                    ],
                ],
                'segments' => [],
            ],
            'modules' => [
                [
                    'name' => 'unit-test',
                    'bucketKey' => function($options) use (&$capturedBucketKey) {
                        $capturedBucketKey = $options['bucketKey'];
                        return $options['bucketKey'];
                    },
                ],
            ],
        ]);

        self::assertTrue($sdk->isEnabled('test', [
            'userId' => '123',
            'deviceId' => '456',
        ]));
        self::assertEquals('control', $sdk->getVariation('test', [
            'userId' => '123',
            'deviceId' => '456',
        ]));
        self::assertEquals('123.test', $capturedBucketKey);

        self::assertEquals('control', $sdk->getVariation('test', [
            'deviceId' => '456',
        ]));
        self::assertEquals('456.test', $capturedBucketKey);
    }

    public function testShouldInterceptContextBeforeModule()
    {
        $intercepted = false;
        $interceptedFeatureKey = '';
        $interceptedVariableKey = '';

        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'test' => [
                        'key' => 'test',
                        'bucketBy' => 'userId',
                        'variations' => [['value' => 'control'], ['value' => 'treatment']],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [
                                    ['variation' => 'control', 'range' => [0, 100000]],
                                    ['variation' => 'treatment', 'range' => [0, 0]],
                                ],
                            ],
                        ],
                    ],
                ],
                'segments' => [],
            ],
            'modules' => [
                [
                    'name' => 'unit-test',
                    'before' => function($options) use (&$intercepted, &$interceptedFeatureKey, &$interceptedVariableKey) {
                        $intercepted = true;
                        $interceptedFeatureKey = $options['featureKey'];
                        $interceptedVariableKey = $options['variableKey'] ?? '';
                        return $options;
                    },
                ],
            ],
        ]);

        $variation = $sdk->getVariation('test', [
            'userId' => '123',
        ]);

        self::assertEquals('control', $variation);
        self::assertTrue($intercepted);
        self::assertEquals('test', $interceptedFeatureKey);
        self::assertEquals('', $interceptedVariableKey);
    }

    public function testShouldInterceptValueAfterModule()
    {
        $intercepted = false;
        $interceptedFeatureKey = '';
        $interceptedVariableKey = '';

        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'test' => [
                        'key' => 'test',
                        'bucketBy' => 'userId',
                        'variations' => [['value' => 'control'], ['value' => 'treatment']],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [
                                    ['variation' => 'control', 'range' => [0, 100000]],
                                    ['variation' => 'treatment', 'range' => [0, 0]],
                                ],
                            ],
                        ],
                    ],
                ],
                'segments' => [],
            ],
            'modules' => [
                [
                    'name' => 'unit-test',
                    'after' => function($options) use (&$intercepted, &$interceptedFeatureKey, &$interceptedVariableKey) {
                        $intercepted = true;
                        $interceptedFeatureKey = $options['featureKey'];
                        $interceptedVariableKey = $options['variableKey'] ?? '';
                        $options['variationValue'] = 'control_intercepted'; // manipulating value here
                        return $options;
                    },
                ],
            ],
        ]);

        $variation = $sdk->getVariation('test', [
            'userId' => '123',
        ]);

        self::assertEquals('control_intercepted', $variation); // should not be "control" any more
        self::assertTrue($intercepted);
        self::assertEquals('test', $interceptedFeatureKey);
        self::assertEquals('', $interceptedVariableKey);
    }

    public function testShouldInitializeWithStickyFeatures()
    {
        $datafileContent = [
            'schemaVersion' => '2',
            'revision' => '1.0',
            'features' => [
                'test' => [
                    'key' => 'test',
                    'bucketBy' => 'userId',
                    'variations' => [['value' => 'control'], ['value' => 'treatment']],
                    'traffic' => [
                        [
                            'key' => '1',
                            'segments' => '*',
                            'percentage' => 100000,
                            'allocation' => [
                                ['variation' => 'control', 'range' => [0, 0]],
                                ['variation' => 'treatment', 'range' => [0, 100000]],
                            ],
                        ],
                    ],
                ],
            ],
            'segments' => [],
        ];

        $sdk = Featurevisor::createFeaturevisor([
            'sticky' => [
                'test' => [
                    'enabled' => true,
                    'variation' => 'control',
                    'variables' => [
                        'color' => 'red',
                    ],
                ],
            ],
        ]);

        // initially control
        self::assertEquals('control', $sdk->getVariation('test', [
            'userId' => '123',
        ]));
        self::assertEquals('red', $sdk->getVariable('test', 'color', [
            'userId' => '123',
        ]));

        $sdk->setDatafile($datafileContent);

        // still control after setting datafile
        self::assertEquals('control', $sdk->getVariation('test', [
            'userId' => '123',
        ]));

        // unsetting sticky features will make it treatment
        $sdk->setSticky([], true);
        self::assertEquals('treatment', $sdk->getVariation('test', [
            'userId' => '123',
        ]));
    }

    public function testShouldHonourSimpleRequiredFeatures()
    {
        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'requiredKey' => [
                        'key' => 'requiredKey',
                        'bucketBy' => 'userId',
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 0, // disabled
                                'allocation' => [],
                            ],
                        ],
                    ],
                    'myKey' => [
                        'key' => 'myKey',
                        'bucketBy' => 'userId',
                        'required' => ['requiredKey'],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [],
                            ],
                        ],
                    ],
                ],
                'segments' => [],
            ],
        ]);

        // should be disabled because required is disabled
        self::assertFalse($sdk->isEnabled('myKey'));

        // enabling required should enable the feature too
        $sdk2 = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'requiredKey' => [
                        'key' => 'requiredKey',
                        'bucketBy' => 'userId',
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000, // enabled
                                'allocation' => [],
                            ],
                        ],
                    ],
                    'myKey' => [
                        'key' => 'myKey',
                        'bucketBy' => 'userId',
                        'required' => ['requiredKey'],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [],
                            ],
                        ],
                    ],
                ],
                'segments' => [],
            ],
        ]);
        self::assertTrue($sdk2->isEnabled('myKey'));
    }

    public function testShouldHonourRequiredFeaturesWithVariation()
    {
        // should be disabled because required has different variation
        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'requiredKey' => [
                        'key' => 'requiredKey',
                        'bucketBy' => 'userId',
                        'variations' => [['value' => 'control'], ['value' => 'treatment']],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [
                                    ['variation' => 'control', 'range' => [0, 0]],
                                    ['variation' => 'treatment', 'range' => [0, 100000]],
                                ],
                            ],
                        ],
                    ],
                    'myKey' => [
                        'key' => 'myKey',
                        'bucketBy' => 'userId',
                        'required' => [
                            [
                                'key' => 'requiredKey',
                                'variation' => 'control', // different variation
                            ],
                        ],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [],
                            ],
                        ],
                    ],
                ],
                'segments' => [],
            ],
        ]);

        self::assertFalse($sdk->isEnabled('myKey'));

        // child should be enabled because required has desired variation
        $sdk2 = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'requiredKey' => [
                        'key' => 'requiredKey',
                        'bucketBy' => 'userId',
                        'variations' => [['value' => 'control'], ['value' => 'treatment']],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [
                                    ['variation' => 'control', 'range' => [0, 0]],
                                    ['variation' => 'treatment', 'range' => [0, 100000]],
                                ],
                            ],
                        ],
                    ],
                    'myKey' => [
                        'key' => 'myKey',
                        'bucketBy' => 'userId',
                        'required' => [
                            [
                                'key' => 'requiredKey',
                                'variation' => 'treatment', // desired variation
                            ],
                        ],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [],
                            ],
                        ],
                    ],
                ],
                'segments' => [],
            ],
        ]);
        self::assertTrue($sdk2->isEnabled('myKey'));
    }

    public function testShouldEmitWarningsForDeprecatedFeature()
    {
        $deprecatedCount = 0;

        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'test' => [
                        'key' => 'test',
                        'bucketBy' => 'userId',
                        'variations' => [['value' => 'control'], ['value' => 'treatment']],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [
                                    ['variation' => 'control', 'range' => [0, 100000]],
                                    ['variation' => 'treatment', 'range' => [0, 0]],
                                ],
                            ],
                        ],
                    ],
                    'deprecatedTest' => [
                        'key' => 'deprecatedTest',
                        'deprecated' => true,
                        'bucketBy' => 'userId',
                        'variations' => [['value' => 'control'], ['value' => 'treatment']],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [
                                    ['variation' => 'control', 'range' => [0, 100000]],
                                    ['variation' => 'treatment', 'range' => [0, 0]],
                                ],
                            ],
                        ],
                    ],
                ],
                'segments' => [],
            ],
            'onDiagnostic' => function(array $diagnostic) use (&$deprecatedCount) {
                    if ($diagnostic['code'] === 'deprecated_feature') {
                        $deprecatedCount += 1;
                    }
                },
        ]);

        $testVariation = $sdk->getVariation('test', [
            'userId' => '123',
        ]);
        $deprecatedTestVariation = $sdk->getVariation('deprecatedTest', [
            'userId' => '123',
        ]);

        self::assertEquals('control', $testVariation);
        self::assertEquals('control', $deprecatedTestVariation);
        self::assertEquals(1, $deprecatedCount);
    }

    public function testShouldCheckIfEnabledForOverriddenFlagsFromRules()
    {
        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'test' => [
                        'key' => 'test',
                        'bucketBy' => 'userId',
                        'traffic' => [
                            [
                                'key' => '2',
                                'segments' => ['netherlands'],
                                'percentage' => 100000,
                                'enabled' => false,
                                'allocation' => [],
                            ],
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [],
                            ],
                        ],
                    ],
                ],
                'segments' => [
                    'netherlands' => [
                        'key' => 'netherlands',
                        'conditions' => json_encode([
                            [
                                'attribute' => 'country',
                                'operator' => 'equals',
                                'value' => 'nl',
                            ],
                        ]),
                    ],
                ],
            ],
        ]);

        self::assertTrue($sdk->isEnabled('test', ['userId' => 'user-123', 'country' => 'de']));
        self::assertFalse($sdk->isEnabled('test', ['userId' => 'user-123', 'country' => 'nl']));
    }

    public function testShouldCheckIfEnabledForMutuallyExclusiveFeatures()
    {
        $bucketValue = 10000;

        $sdk = Featurevisor::createFeaturevisor([
            'modules' => [
                [
                    'name' => 'unit-test',
                    'bucketValue' => function() use (&$bucketValue) {
                        return $bucketValue;
                    },
                ],
            ],
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'mutex' => [
                        'key' => 'mutex',
                        'bucketBy' => 'userId',
                        'ranges' => [[0, 50000]],
                        'traffic' => [['key' => '1', 'segments' => '*', 'percentage' => 50000, 'allocation' => []]],
                    ],
                ],
                'segments' => [],
            ],
        ]);

        self::assertFalse($sdk->isEnabled('test'));
        self::assertFalse($sdk->isEnabled('test', ['userId' => '123']));

        $bucketValue = 40000;
        self::assertTrue($sdk->isEnabled('mutex', ['userId' => '123']));

        $bucketValue = 60000;
        self::assertFalse($sdk->isEnabled('mutex', ['userId' => '123']));
    }

    public function testShouldGetVariation()
    {
        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'test' => [
                        'key' => 'test',
                        'bucketBy' => 'userId',
                        'variations' => [['value' => 'control'], ['value' => 'treatment']],
                        'force' => [
                            [
                                'conditions' => [['attribute' => 'userId', 'operator' => 'equals', 'value' => 'user-gb']],
                                'enabled' => false,
                            ],
                            [
                                'segments' => ['netherlands'],
                                'enabled' => false,
                            ],
                        ],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [
                                    ['variation' => 'control', 'range' => [0, 0]],
                                    ['variation' => 'treatment', 'range' => [0, 100000]],
                                ],
                            ],
                        ],
                    ],
                    'testWithNoVariation' => [
                        'key' => 'testWithNoVariation',
                        'bucketBy' => 'userId',
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [],
                            ],
                        ],
                    ],
                ],
                'segments' => [
                    'netherlands' => [
                        'key' => 'netherlands',
                        'conditions' => json_encode([
                            [
                                'attribute' => 'country',
                                'operator' => 'equals',
                                'value' => 'nl',
                            ],
                        ]),
                    ],
                ],
            ],
        ]);

        $context = [
            'userId' => '123',
        ];

        self::assertEquals('treatment', $sdk->getVariation('test', $context));
        self::assertEquals('treatment', $sdk->getVariation('test', ['userId' => 'user-ch']));

        // non existing
        self::assertNull($sdk->getVariation('nonExistingFeature', $context));

        // disabled
        self::assertNull($sdk->getVariation('test', ['userId' => 'user-gb']));
        self::assertNull($sdk->getVariation('test', ['userId' => 'user-gb']));
        self::assertNull($sdk->getVariation('test', ['userId' => '123', 'country' => 'nl']));

        // no variation
        self::assertNull($sdk->getVariation('testWithNoVariation', $context));
    }

    public function testShouldGetVariable()
    {
        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'test' => [
                        'key' => 'test',
                        'bucketBy' => 'userId',
                        'variablesSchema' => [
                            'color' => [
                                'key' => 'color',
                                'type' => 'string',
                                'defaultValue' => 'red',
                            ],
                            'showSidebar' => [
                                'key' => 'showSidebar',
                                'type' => 'boolean',
                                'defaultValue' => false,
                            ],
                            'sidebarTitle' => [
                                'key' => 'sidebarTitle',
                                'type' => 'string',
                                'defaultValue' => 'sidebar title',
                            ],
                            'count' => [
                                'key' => 'count',
                                'type' => 'integer',
                                'defaultValue' => 0,
                            ],
                            'price' => [
                                'key' => 'price',
                                'type' => 'double',
                                'defaultValue' => 9.99,
                            ],
                            'paymentMethods' => [
                                'key' => 'paymentMethods',
                                'type' => 'array',
                                'defaultValue' => ['paypal', 'creditcard'],
                            ],
                            'flatConfig' => [
                                'key' => 'flatConfig',
                                'type' => 'object',
                                'defaultValue' => [
                                    'key' => 'value',
                                ],
                            ],
                            'nestedConfig' => [
                                'key' => 'nestedConfig',
                                'type' => 'json',
                                'defaultValue' => json_encode([
                                    'key' => [
                                        'nested' => 'value',
                                    ],
                                ]),
                            ],
                        ],
                        'variations' => [
                            ['value' => 'control'],
                            [
                                'value' => 'treatment',
                                'variables' => [
                                    'showSidebar' => true,
                                    'sidebarTitle' => 'sidebar title from variation',
                                ],
                                'variableOverrides' => [
                                    'showSidebar' => [
                                        [
                                            'segments' => ['netherlands'],
                                            'value' => false,
                                        ],
                                        [
                                            'conditions' => [
                                                [
                                                    'attribute' => 'country',
                                                    'operator' => 'equals',
                                                    'value' => 'de',
                                                ],
                                            ],
                                            'value' => false,
                                        ],
                                    ],
                                    'sidebarTitle' => [
                                        [
                                            'segments' => ['netherlands'],
                                            'value' => 'Dutch title',
                                        ],
                                        [
                                            'conditions' => [
                                                [
                                                    'attribute' => 'country',
                                                    'operator' => 'equals',
                                                    'value' => 'de',
                                                ],
                                            ],
                                            'value' => 'German title',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'force' => [
                            [
                                'conditions' => [['attribute' => 'userId', 'operator' => 'equals', 'value' => 'user-ch']],
                                'enabled' => true,
                                'variation' => 'control',
                                'variables' => [
                                    'color' => 'red and white',
                                ],
                            ],
                            [
                                'conditions' => [['attribute' => 'userId', 'operator' => 'equals', 'value' => 'user-gb']],
                                'enabled' => false,
                            ],
                            [
                                'conditions' => [
                                    ['attribute' => 'userId', 'operator' => 'equals', 'value' => 'user-forced-variation'],
                                ],
                                'enabled' => true,
                                'variation' => 'treatment',
                            ],
                        ],
                        'traffic' => [
                            // belgium
                            [
                                'key' => '2',
                                'segments' => ['belgium'],
                                'percentage' => 100000,
                                'allocation' => [
                                    ['variation' => 'control', 'range' => [0, 0]],
                                    [
                                        'variation' => 'treatment',
                                        'range' => [0, 100000],
                                    ],
                                ],
                                'variation' => 'control',
                                'variables' => [
                                    'color' => 'black',
                                ],
                            ],
                            // everyone
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [
                                    ['variation' => 'control', 'range' => [0, 0]],
                                    [
                                        'variation' => 'treatment',
                                        'range' => [0, 100000],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'anotherTest' => [
                        'key' => 'test',
                        'bucketBy' => 'userId',
                        'traffic' => [
                            // everyone
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                            ],
                        ],
                    ],
                ],
                'segments' => [
                    'netherlands' => [
                        'key' => 'netherlands',
                        'conditions' => json_encode([
                            [
                                'attribute' => 'country',
                                'operator' => 'equals',
                                'value' => 'nl',
                            ],
                        ]),
                    ],
                    'belgium' => [
                        'key' => 'belgium',
                        'conditions' => json_encode([
                            [
                                'attribute' => 'country',
                                'operator' => 'equals',
                                'value' => 'be',
                            ],
                        ]),
                    ],
                ],
            ],
        ]);

        $context = [
            'userId' => '123',
        ];

        $evaluatedFeatures = $sdk->getAllEvaluations($context);
        self::assertEquals([
            'test' => [
                'enabled' => true,
                'variation' => 'treatment',
                'variables' => [
                    'color' => 'red',
                    'showSidebar' => true,
                    'sidebarTitle' => 'sidebar title from variation',
                    'count' => 0,
                    'price' => 9.99,
                    'paymentMethods' => ['paypal', 'creditcard'],
                    'flatConfig' => [
                        'key' => 'value',
                    ],
                    'nestedConfig' => [
                        'key' => [
                            'nested' => 'value',
                        ],
                    ],
                ],
            ],
            'anotherTest' => [
                'enabled' => true,
            ],
        ], $evaluatedFeatures);

        self::assertEquals('treatment', $sdk->getVariation('test', $context));
        self::assertEquals('control', $sdk->getVariation('test', array_merge($context, ['country' => 'be'])));
        self::assertEquals('control', $sdk->getVariation('test', ['userId' => 'user-ch']));

        self::assertEquals('red', $sdk->getVariable('test', 'color', $context));
        self::assertEquals('red', $sdk->getVariableString('test', 'color', $context));
        self::assertEquals('black', $sdk->getVariable('test', 'color', array_merge($context, ['country' => 'be'])));
        self::assertEquals('red and white', $sdk->getVariable('test', 'color', ['userId' => 'user-ch']));

        self::assertEquals(true, $sdk->getVariable('test', 'showSidebar', $context));
        self::assertEquals(true, $sdk->getVariableBoolean('test', 'showSidebar', $context));
        self::assertEquals(false, $sdk->getVariableBoolean('test', 'showSidebar', array_merge($context, ['country' => 'nl'])));
        self::assertEquals(false, $sdk->getVariableBoolean('test', 'showSidebar', array_merge($context, ['country' => 'de'])));

        self::assertEquals('German title', $sdk->getVariableString('test', 'sidebarTitle', [
            'userId' => 'user-forced-variation',
            'country' => 'de',
        ]));
        self::assertEquals('Dutch title', $sdk->getVariableString('test', 'sidebarTitle', [
            'userId' => 'user-forced-variation',
            'country' => 'nl',
        ]));
        self::assertEquals('sidebar title from variation', $sdk->getVariableString('test', 'sidebarTitle', [
            'userId' => 'user-forced-variation',
            'country' => 'be',
        ]));

        self::assertEquals(0, $sdk->getVariable('test', 'count', $context));
        self::assertEquals(0, $sdk->getVariableInteger('test', 'count', $context));

        self::assertEquals(9.99, $sdk->getVariable('test', 'price', $context));
        self::assertEquals(9.99, $sdk->getVariableDouble('test', 'price', $context));

        self::assertEquals(['paypal', 'creditcard'], $sdk->getVariable('test', 'paymentMethods', $context));
        self::assertEquals(['paypal', 'creditcard'], $sdk->getVariableArray('test', 'paymentMethods', $context));

        self::assertEquals([
            'key' => 'value',
        ], $sdk->getVariable('test', 'flatConfig', $context));
        self::assertEquals([
            'key' => 'value',
        ], $sdk->getVariableObject('test', 'flatConfig', $context));

        self::assertEquals([
            'key' => [
                'nested' => 'value',
            ],
        ], $sdk->getVariable('test', 'nestedConfig', $context));
        self::assertEquals([
            'key' => [
                'nested' => 'value',
            ],
        ], $sdk->getVariableJSON('test', 'nestedConfig', $context));

        // non existing
        self::assertNull($sdk->getVariable('test', 'nonExisting', $context));
        self::assertNull($sdk->getVariable('nonExistingFeature', 'nonExisting', $context));

        // disabled
        self::assertNull($sdk->getVariable('test', 'color', ['userId' => 'user-gb']));
    }

    public function testShouldGetVariablesWithoutAnyVariations()
    {
        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'segments' => [
                    'netherlands' => [
                        'key' => 'netherlands',
                        'conditions' => json_encode([
                            [
                                'attribute' => 'country',
                                'operator' => 'equals',
                                'value' => 'nl',
                            ],
                        ]),
                    ],
                ],
                'features' => [
                    'test' => [
                        'key' => 'test',
                        'bucketBy' => 'userId',
                        'variablesSchema' => [
                            'color' => [
                                'key' => 'color',
                                'type' => 'string',
                                'defaultValue' => 'red',
                            ],
                        ],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => 'netherlands',
                                'percentage' => 100000,
                                'variables' => [
                                    'color' => 'orange',
                                ],
                                'allocation' => [],
                            ],
                            [
                                'key' => '2',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $defaultContext = [
            'userId' => '123',
        ];

        // test default value
        self::assertEquals('red', $sdk->getVariable('test', 'color', $defaultContext));

        // test override
        self::assertEquals('orange', $sdk->getVariable('test', 'color', array_merge($defaultContext, ['country' => 'nl'])));
    }

    public function testShouldApplyRuleVariableOverridesOnTopOfRuleVariables()
    {
        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'segments' => [
                    'germany' => [
                        'key' => 'germany',
                        'conditions' => json_encode([
                            [
                                'attribute' => 'country',
                                'operator' => 'equals',
                                'value' => 'de',
                            ],
                        ]),
                    ],
                    'mobile' => [
                        'key' => 'mobile',
                        'conditions' => json_encode([
                            [
                                'attribute' => 'device',
                                'operator' => 'equals',
                                'value' => 'mobile',
                            ],
                        ]),
                    ],
                ],
                'features' => [
                    'test' => [
                        'key' => 'test',
                        'bucketBy' => 'userId',
                        'variablesSchema' => [
                            'config' => [
                                'key' => 'config',
                                'type' => 'object',
                                'defaultValue' => [
                                    'source' => 'default',
                                    'nested' => ['value' => 0],
                                ],
                            ],
                        ],
                        'traffic' => [
                            [
                                'key' => 'germany',
                                'segments' => 'germany',
                                'percentage' => 100000,
                                'variables' => [
                                    'config' => [
                                        'source' => 'rule',
                                        'nested' => ['value' => 10],
                                        'flag' => true,
                                    ],
                                ],
                                'variableOverrides' => [
                                    'config' => [
                                        [
                                            'segments' => 'mobile',
                                            'value' => [
                                                'source' => 'rule',
                                                'nested' => ['value' => 20],
                                                'flag' => true,
                                            ],
                                        ],
                                        [
                                            'conditions' => [
                                                [
                                                    'attribute' => 'country',
                                                    'operator' => 'equals',
                                                    'value' => 'de',
                                                ],
                                            ],
                                            'value' => [
                                                'source' => 'rule',
                                                'nested' => ['value' => 30],
                                                'flag' => true,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'key' => 'everyone',
                                'segments' => '*',
                                'percentage' => 100000,
                                'variables' => [
                                    'config' => [
                                        'source' => 'everyone',
                                        'nested' => ['value' => 1],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertEquals(
            [
                'source' => 'rule',
                'nested' => ['value' => 30],
                'flag' => true,
            ],
            $sdk->getVariableObject('test', 'config', [
                'userId' => 'user-1',
                'country' => 'de',
            ])
        );

        self::assertEquals(
            [
                'source' => 'rule',
                'nested' => ['value' => 20],
                'flag' => true,
            ],
            $sdk->getVariableObject('test', 'config', [
                'userId' => 'user-1',
                'country' => 'de',
                'device' => 'mobile',
            ])
        );

        self::assertEquals(
            [
                'source' => 'everyone',
                'nested' => ['value' => 1],
            ],
            $sdk->getVariableObject('test', 'config', [
                'userId' => 'user-1',
                'country' => 'nl',
            ])
        );
    }

    public function testShouldCheckIfEnabledForIndividuallyNamedSegments()
    {
        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'test' => [
                        'key' => 'test',
                        'bucketBy' => 'userId',
                        'traffic' => [
                            ['key' => '1', 'segments' => 'netherlands', 'percentage' => 100000, 'allocation' => []],
                            [
                                'key' => '2',
                                'segments' => json_encode(['iphone', 'unitedStates']),
                                'percentage' => 100000,
                                'allocation' => [],
                            ],
                        ],
                    ],
                ],
                'segments' => [
                    'netherlands' => [
                        'key' => 'netherlands',
                        'conditions' => json_encode([
                            [
                                'attribute' => 'country',
                                'operator' => 'equals',
                                'value' => 'nl',
                            ],
                        ]),
                    ],
                    'iphone' => [
                        'key' => 'iphone',
                        'conditions' => json_encode([
                            [
                                'attribute' => 'device',
                                'operator' => 'equals',
                                'value' => 'iphone',
                            ],
                        ]),
                    ],
                    'unitedStates' => [
                        'key' => 'unitedStates',
                        'conditions' => json_encode([
                            [
                                'attribute' => 'country',
                                'operator' => 'equals',
                                'value' => 'us',
                            ],
                        ]),
                    ],
                ],
            ],
        ]);

        self::assertFalse($sdk->isEnabled('test'));
        self::assertFalse($sdk->isEnabled('test', ['userId' => '123']));
        self::assertFalse($sdk->isEnabled('test', ['userId' => '123', 'country' => 'de']));
        self::assertFalse($sdk->isEnabled('test', ['userId' => '123', 'country' => 'us']));

        self::assertTrue($sdk->isEnabled('test', ['userId' => '123', 'country' => 'nl']));
        self::assertTrue($sdk->isEnabled('test', ['userId' => '123', 'country' => 'us', 'device' => 'iphone']));
    }

    public function testShouldGetArrayAndObjectVariables()
    {
        $sdk = Featurevisor::createFeaturevisor([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [
                    'withArray' => [
                        'key' => 'withArray',
                        'bucketBy' => 'userId',
                        'variablesSchema' => [
                            'simpleArray' => [
                                'key' => 'simpleArray',
                                'type' => 'array',
                                'defaultValue' => ['red', 'blue', 'green'],
                            ],
                            'simpleStringArray' => [
                                'key' => 'simpleStringArray',
                                'type' => 'array',
                                'defaultValue' => ['red', 'blue', 'green'],
                            ],
                            'objectArray' => [
                                'key' => 'objectArray',
                                'type' => 'array',
                                'defaultValue' => [
                                    ['color' => 'red', 'opacity' => 100],
                                    ['color' => 'blue', 'opacity' => 90],
                                    ['color' => 'green', 'opacity' => 95],
                                ],
                            ],
                        ],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [],
                            ],
                        ],
                    ],
                    'withObject' => [
                        'key' => 'withObject',
                        'bucketBy' => 'userId',
                        'variablesSchema' => [
                            'themeConfig' => [
                                'key' => 'themeConfig',
                                'type' => 'object',
                                'defaultValue' => [
                                    'theme' => 'light',
                                    'darkMode' => false,
                                ],
                            ],
                            'headerConfig' => [
                                'key' => 'headerConfig',
                                'type' => 'object',
                                'defaultValue' => [
                                    'style' => ['fontSize' => 18, 'bold' => true],
                                    'title' => 'Welcome',
                                ],
                            ],
                            'mixedConfig' => [
                                'key' => 'mixedConfig',
                                'type' => 'object',
                                'defaultValue' => [
                                    'name' => 'mixed',
                                    'enabled' => true,
                                    'meta' => ['score' => 0.95, 'items' => ['a', 'b']],
                                ],
                            ],
                        ],
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [],
                            ],
                        ],
                    ],
                ],
                'segments' => [],
            ],
        ]);

        $context = ['userId' => 'user-1'];

        self::assertEquals(['red', 'blue', 'green'], $sdk->getVariable('withArray', 'simpleArray', $context));
        self::assertEquals(['red', 'blue', 'green'], $sdk->getVariableArray('withArray', 'simpleArray', $context));
        self::assertEquals(
            [
                ['color' => 'red', 'opacity' => 100],
                ['color' => 'blue', 'opacity' => 90],
                ['color' => 'green', 'opacity' => 95],
            ],
            $sdk->getVariableArray('withArray', 'objectArray', $context)
        );

        self::assertEquals(
            ['theme' => 'light', 'darkMode' => false],
            $sdk->getVariableObject('withObject', 'themeConfig', $context)
        );
        self::assertEquals(
            [
                'style' => ['fontSize' => 18, 'bold' => true],
                'title' => 'Welcome',
            ],
            $sdk->getVariableObject('withObject', 'headerConfig', $context)
        );
        self::assertEquals(
            [
                'name' => 'mixed',
                'enabled' => true,
                'meta' => ['score' => 0.95, 'items' => ['a', 'b']],
            ],
            $sdk->getVariableObject('withObject', 'mixedConfig', $context)
        );

        self::assertNull($sdk->getVariableArray('withArray', 'nonExisting', $context));
        self::assertNull($sdk->getVariableObject('withObject', 'nonExisting', $context));
        self::assertNull($sdk->getVariableArray('nonExistingFeature', 'simpleArray', $context));
        self::assertNull($sdk->getVariableObject('nonExistingFeature', 'themeConfig', $context));

        $all = $sdk->getAllEvaluations($context);
        self::assertTrue($all['withArray']['enabled']);
        self::assertEquals(['red', 'blue', 'green'], $all['withArray']['variables']['simpleArray']);
        self::assertEquals(
            [
                'theme' => 'light',
                'darkMode' => false,
            ],
            $all['withObject']['variables']['themeConfig']
        );
    }

    public function testShouldSetDatafileByMergingByDefaultAndReplacingWhenRequested()
    {
        $events = [];
        $sdk = Featurevisor::createFeaturevisor([
            'logLevel' => LogLevel::ERROR,
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => 'base',
                'segments' => [],
                'features' => [
                    'first' => [
                        'key' => 'first',
                        'bucketBy' => 'userId',
                        'traffic' => [
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $sdk->on('datafile_set', function(array $details) use (&$events) {
            $events[] = $details;
        });

        $sdk->setDatafile([
            'schemaVersion' => '2',
            'revision' => 'merged',
            'segments' => [],
            'features' => [
                'second' => [
                    'key' => 'second',
                    'bucketBy' => 'userId',
                    'traffic' => [
                        [
                            'key' => '1',
                            'segments' => '*',
                            'percentage' => 100000,
                        ],
                    ],
                ],
            ],
        ]);

        self::assertTrue($sdk->isEnabled('first', ['userId' => '123']));
        self::assertTrue($sdk->isEnabled('second', ['userId' => '123']));
        self::assertFalse($events[0]['replaced']);

        $sdk->setDatafile([
            'schemaVersion' => '2',
            'revision' => 'replaced',
            'segments' => [],
            'features' => [
                'third' => [
                    'key' => 'third',
                    'bucketBy' => 'userId',
                    'traffic' => [
                        [
                            'key' => '1',
                            'segments' => '*',
                            'percentage' => 100000,
                        ],
                    ],
                ],
            ],
        ], true);

        self::assertFalse($sdk->isEnabled('first', ['userId' => '123']));
        self::assertTrue($sdk->isEnabled('third', ['userId' => '123']));
        self::assertTrue($events[1]['replaced']);
    }

    public function testShouldManageModulesAndDuplicateDiagnostics()
    {
        $diagnostics = [];
        $setupCalls = 0;
        $closeCalls = 0;

        $sdk = Featurevisor::createFeaturevisor([
            'logLevel' => LogLevel::ERROR,
            'onDiagnostic' => function(array $diagnostic) use (&$diagnostics) {
                $diagnostics[] = $diagnostic;
            },
            'modules' => [
                [
                    'name' => 'module-a',
                    'setup' => function(array $api) use (&$setupCalls) {
                        $setupCalls++;
                        self::assertSame('unknown', $api['getRevision']());
                    },
                    'close' => function() use (&$closeCalls) {
                        $closeCalls++;
                    },
                ],
            ],
        ]);

        $removeModule = $sdk->addModule([
            'name' => 'module-b',
            'close' => function() use (&$closeCalls) {
                $closeCalls++;
            },
        ]);
        $duplicate = $sdk->addModule(['name' => 'module-b']);

        self::assertSame(1, $setupCalls);
        self::assertNull($duplicate);
        self::assertSame('duplicate_module', $diagnostics[0]['code']);

        $removeModule();
        $sdk->addModule([
            'name' => 'module-c',
            'close' => function() use (&$closeCalls) {
                $closeCalls++;
            },
        ]);
        $sdk->removeModule('module-c');
        $sdk->close();

        self::assertSame(3, $closeCalls);
    }

    public function testShouldReportModuleCloseErrorsAndContinueCleanup()
    {
        $diagnostics = [];
        $errors = [];
        $closed = [];

        $sdk = Featurevisor::createFeaturevisor([
            'logLevel' => LogLevel::ERROR,
            'onDiagnostic' => function(array $diagnostic) use (&$diagnostics) {
                $diagnostics[] = $diagnostic;
            },
            'modules' => [
                [
                    'name' => 'first',
                    'close' => function() use (&$closed) {
                        $closed[] = 'first';
                        throw new \RuntimeException('first close failed');
                    },
                ],
                [
                    'name' => 'second',
                    'close' => function() use (&$closed) {
                        $closed[] = 'second';
                    },
                ],
            ],
        ]);
        $sdk->on('error', function(array $event) use (&$errors) {
            $errors[] = $event;
        });

        $sdk->close();

        self::assertSame(['first', 'second'], $closed);
        self::assertTrue(count(array_filter($diagnostics, fn($diagnostic) =>
            ($diagnostic['code'] ?? null) === 'module_close_error'
            && ($diagnostic['moduleName'] ?? null) === 'first'
            && ($diagnostic['level'] ?? null) === 'error'
            && ($diagnostic['originalError'] ?? null) instanceof \RuntimeException
        )) > 0);
        self::assertTrue(count(array_filter($errors, fn($event) =>
            ($event['code'] ?? null) === 'module_close_error'
            && ($event['moduleName'] ?? null) === 'first'
        )) > 0);
    }

    public function testShouldReportModuleCloseErrorsFromUnsubscribeOnce()
    {
        $diagnostics = [];

        $sdk = Featurevisor::createFeaturevisor([
            'logLevel' => LogLevel::ERROR,
            'onDiagnostic' => function(array $diagnostic) use (&$diagnostics) {
                $diagnostics[] = $diagnostic;
            },
        ]);

        $removeModule = $sdk->addModule([
            'name' => 'dynamic',
            'close' => function() {
                throw new \RuntimeException('dynamic close failed');
            },
        ]);

        $removeModule();
        $removeModule();

        self::assertSame(1, count(array_filter($diagnostics, fn($diagnostic) =>
            ($diagnostic['code'] ?? null) === 'module_close_error'
            && ($diagnostic['moduleName'] ?? null) === 'dynamic'
        )));
    }

    public function testShouldSupportModuleDiagnosticsSubscriptions()
    {
        $received = [];
        $reporter = null;

        $sdk = Featurevisor::createFeaturevisor([
            'logLevel' => LogLevel::ERROR,
            'modules' => [
                [
                    'name' => 'listener',
                    'setup' => function(array $api) use (&$received) {
                        $api['onDiagnostic'](function(array $diagnostic) use (&$received) {
                            $received[] = $diagnostic;
                        }, ['level' => LogLevel::WARNING]);
                    },
                ],
                [
                    'name' => 'reporter',
                    'setup' => function(array $api) use (&$reporter) {
                        $reporter = $api['reportDiagnostic'];
                    },
                ],
            ],
        ]);

        $reporter([
            'level' => 'warning',
            'code' => 'from_reporter',
            'message' => 'diagnostic from reporter',
        ]);

        self::assertCount(1, $received);
        self::assertSame('from_reporter', $received[0]['code']);

        $sdk->removeModule('listener');
        $reporter([
            'level' => 'warning',
            'code' => 'after_remove',
            'message' => 'after remove',
        ]);

        self::assertCount(1, $received);
    }
}
