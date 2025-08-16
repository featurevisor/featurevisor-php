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

        $this->assertTrue(method_exists($sdk, 'getVariation'));
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

        $this->assertTrue($sdk->isEnabled($featureKey, $context));
        $this->assertEquals('control', $sdk->getVariation($featureKey, $context));
        $this->assertEquals('123.test', $capturedBucketKey);
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

        $this->assertEquals('control', $sdk->getVariation($featureKey, $context));
        $this->assertEquals('123.456.test', $capturedBucketKey);
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

        $this->assertTrue($sdk->isEnabled('test', [
            'userId' => '123',
            'deviceId' => '456',
        ]));
        $this->assertEquals('control', $sdk->getVariation('test', [
            'userId' => '123',
            'deviceId' => '456',
        ]));
        $this->assertEquals('123.test', $capturedBucketKey);

        $this->assertEquals('control', $sdk->getVariation('test', [
            'deviceId' => '456',
        ]));
        $this->assertEquals('456.test', $capturedBucketKey);
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

        $this->assertEquals('control', $variation);
        $this->assertTrue($intercepted);
        $this->assertEquals('test', $interceptedFeatureKey);
        $this->assertEquals('', $interceptedVariableKey);
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

        $this->assertEquals('control_intercepted', $variation); // should not be "control" any more
        $this->assertTrue($intercepted);
        $this->assertEquals('test', $interceptedFeatureKey);
        $this->assertEquals('', $interceptedVariableKey);
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
        $this->assertEquals('control', $sdk->getVariation('test', [
            'userId' => '123',
        ]));
        $this->assertEquals('red', $sdk->getVariable('test', 'color', [
            'userId' => '123',
        ]));

        $sdk->setDatafile($datafileContent);

        // still control after setting datafile
        $this->assertEquals('control', $sdk->getVariation('test', [
            'userId' => '123',
        ]));

        // unsetting sticky features will make it treatment
        $sdk->setSticky([], true);
        $this->assertEquals('treatment', $sdk->getVariation('test', [
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
        $this->assertFalse($sdk->isEnabled('myKey'));

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
        $this->assertTrue($sdk2->isEnabled('myKey'));
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

        $this->assertFalse($sdk->isEnabled('myKey'));

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
        $this->assertTrue($sdk2->isEnabled('myKey'));
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

        $this->assertEquals('control', $testVariation);
        $this->assertEquals('control', $deprecatedTestVariation);
        $this->assertEquals(1, $deprecatedCount);
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

        $this->assertTrue($sdk->isEnabled('test', ['userId' => 'user-123', 'country' => 'de']));
        $this->assertFalse($sdk->isEnabled('test', ['userId' => 'user-123', 'country' => 'nl']));
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

        $this->assertFalse($sdk->isEnabled('test'));
        $this->assertFalse($sdk->isEnabled('test', ['userId' => '123']));

        $bucketValue = 40000;
        $this->assertTrue($sdk->isEnabled('mutex', ['userId' => '123']));

        $bucketValue = 60000;
        $this->assertFalse($sdk->isEnabled('mutex', ['userId' => '123']));
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

        $this->assertEquals('treatment', $sdk->getVariation('test', $context));
        $this->assertEquals('treatment', $sdk->getVariation('test', ['userId' => 'user-ch']));

        // non existing
        $this->assertNull($sdk->getVariation('nonExistingFeature', $context));

        // disabled
        $this->assertNull($sdk->getVariation('test', ['userId' => 'user-gb']));
        $this->assertNull($sdk->getVariation('test', ['userId' => 'user-gb']));
        $this->assertNull($sdk->getVariation('test', ['userId' => '123', 'country' => 'nl']));

        // no variation
        $this->assertNull($sdk->getVariation('testWithNoVariation', $context));
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
        $this->assertEquals([
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

        $this->assertEquals('treatment', $sdk->getVariation('test', $context));
        $this->assertEquals('control', $sdk->getVariation('test', array_merge($context, ['country' => 'be'])));
        $this->assertEquals('control', $sdk->getVariation('test', ['userId' => 'user-ch']));

        $this->assertEquals('red', $sdk->getVariable('test', 'color', $context));
        $this->assertEquals('red', $sdk->getVariableString('test', 'color', $context));
        $this->assertEquals('black', $sdk->getVariable('test', 'color', array_merge($context, ['country' => 'be'])));
        $this->assertEquals('red and white', $sdk->getVariable('test', 'color', ['userId' => 'user-ch']));

        $this->assertEquals(true, $sdk->getVariable('test', 'showSidebar', $context));
        $this->assertEquals(true, $sdk->getVariableBoolean('test', 'showSidebar', $context));
        $this->assertEquals(false, $sdk->getVariableBoolean('test', 'showSidebar', array_merge($context, ['country' => 'nl'])));
        $this->assertEquals(false, $sdk->getVariableBoolean('test', 'showSidebar', array_merge($context, ['country' => 'de'])));

        $this->assertEquals('German title', $sdk->getVariableString('test', 'sidebarTitle', [
            'userId' => 'user-forced-variation',
            'country' => 'de',
        ]));
        $this->assertEquals('Dutch title', $sdk->getVariableString('test', 'sidebarTitle', [
            'userId' => 'user-forced-variation',
            'country' => 'nl',
        ]));
        $this->assertEquals('sidebar title from variation', $sdk->getVariableString('test', 'sidebarTitle', [
            'userId' => 'user-forced-variation',
            'country' => 'be',
        ]));

        $this->assertEquals(0, $sdk->getVariable('test', 'count', $context));
        $this->assertEquals(0, $sdk->getVariableInteger('test', 'count', $context));

        $this->assertEquals(9.99, $sdk->getVariable('test', 'price', $context));
        $this->assertEquals(9.99, $sdk->getVariableDouble('test', 'price', $context));

        $this->assertEquals(['paypal', 'creditcard'], $sdk->getVariable('test', 'paymentMethods', $context));
        $this->assertEquals(['paypal', 'creditcard'], $sdk->getVariableArray('test', 'paymentMethods', $context));

        $this->assertEquals([
            'key' => 'value',
        ], $sdk->getVariable('test', 'flatConfig', $context));
        $this->assertEquals([
            'key' => 'value',
        ], $sdk->getVariableObject('test', 'flatConfig', $context));

        $this->assertEquals([
            'key' => [
                'nested' => 'value',
            ],
        ], $sdk->getVariable('test', 'nestedConfig', $context));
        $this->assertEquals([
            'key' => [
                'nested' => 'value',
            ],
        ], $sdk->getVariableJSON('test', 'nestedConfig', $context));

        // non existing
        $this->assertNull($sdk->getVariable('test', 'nonExisting', $context));
        $this->assertNull($sdk->getVariable('nonExistingFeature', 'nonExisting', $context));

        // disabled
        $this->assertNull($sdk->getVariable('test', 'color', ['userId' => 'user-gb']));
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
        $this->assertEquals('red', $sdk->getVariable('test', 'color', $defaultContext));

        // test override
        $this->assertEquals('orange', $sdk->getVariable('test', 'color', array_merge($defaultContext, ['country' => 'nl'])));
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

        $this->assertFalse($sdk->isEnabled('test'));
        $this->assertFalse($sdk->isEnabled('test', ['userId' => '123']));
        $this->assertFalse($sdk->isEnabled('test', ['userId' => '123', 'country' => 'de']));
        $this->assertFalse($sdk->isEnabled('test', ['userId' => '123', 'country' => 'us']));

        $this->assertTrue($sdk->isEnabled('test', ['userId' => '123', 'country' => 'nl']));
        $this->assertTrue($sdk->isEnabled('test', ['userId' => '123', 'country' => 'us', 'device' => 'iphone']));
    }
}
