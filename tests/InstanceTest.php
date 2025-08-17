<?php

namespace Featurevisor\Tests;

use PHPUnit\Framework\TestCase;

use Psr\Log\LogLevel;
use function Featurevisor\createInstance;
use function Featurevisor\createLogger;

class InstanceTest extends TestCase
{
    public function testShouldCreateInstanceWithDatafileContent()
    {
        $sdk = createInstance([
            'datafile' => [
                'schemaVersion' => '2',
                'revision' => '1.0',
                'features' => [],
                'segments' => [],
            ],
        ]);

        self::assertTrue(method_exists($sdk, 'getVariation'));
    }

    public function testShouldConfigurePlainBucketBy()
    {
        $capturedBucketKey = '';

        $sdk = createInstance([
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
            'hooks' => [
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

        $sdk = createInstance([
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
            'hooks' => [
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

        $sdk = createInstance([
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
            'hooks' => [
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

    public function testShouldInterceptContextBeforeHook()
    {
        $intercepted = false;
        $interceptedFeatureKey = '';
        $interceptedVariableKey = '';

        $sdk = createInstance([
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
            'hooks' => [
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

    public function testShouldInterceptValueAfterHook()
    {
        $intercepted = false;
        $interceptedFeatureKey = '';
        $interceptedVariableKey = '';

        $sdk = createInstance([
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
            'hooks' => [
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

        $sdk = createInstance([
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
        $sdk = createInstance([
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
        $sdk2 = createInstance([
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
        $sdk = createInstance([
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
        $sdk2 = createInstance([
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

        $sdk = createInstance([
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
            'logger' => createLogger([
                'handler' => function($level, $message) use (&$deprecatedCount) {
                    if ($level === LogLevel::WARNING && strpos($message, 'is deprecated') !== false) {
                        $deprecatedCount += 1;
                    }
                },
            ]),
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
        $sdk = createInstance([
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

        $sdk = createInstance([
            'hooks' => [
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
        $sdk = createInstance([
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
        $sdk = createInstance([
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
        $sdk = createInstance([
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

    public function testShouldCheckIfEnabledForIndividuallyNamedSegments()
    {
        $sdk = createInstance([
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
}
