<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../src/Child.php';
require_once __DIR__ . '/../src/Instance.php';
require_once __DIR__ . '/../src/index.php';

use function Featurevisor\createInstance;

class ChildTest extends TestCase {
    public function testCreateChildInstanceAndAllBehaviors() {
        $f = createInstance([
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
                                'defaultValue' => [ 'key' => 'value' ],
                            ],
                            'nestedConfig' => [
                                'key' => 'nestedConfig',
                                'type' => 'json',
                                'defaultValue' => json_encode([ 'key' => [ 'nested' => 'value' ] ]),
                            ],
                        ],
                        'variations' => [
                            [ 'value' => 'control' ],
                            [
                                'value' => 'treatment',
                                'variables' => [
                                    'showSidebar' => true,
                                    'sidebarTitle' => 'sidebar title from variation',
                                ],
                                'variableOverrides' => [
                                    'showSidebar' => [
                                        [ 'segments' => ['netherlands'], 'value' => false ],
                                        [ 'conditions' => [ [ 'attribute' => 'country', 'operator' => 'equals', 'value' => 'de' ] ], 'value' => false ],
                                    ],
                                    'sidebarTitle' => [
                                        [ 'segments' => ['netherlands'], 'value' => 'Dutch title' ],
                                        [ 'conditions' => [ [ 'attribute' => 'country', 'operator' => 'equals', 'value' => 'de' ] ], 'value' => 'German title' ],
                                    ],
                                ],
                            ],
                        ],
                        'force' => [
                            [
                                'conditions' => [ [ 'attribute' => 'userId', 'operator' => 'equals', 'value' => 'user-ch' ] ],
                                'enabled' => true,
                                'variation' => 'control',
                                'variables' => [ 'color' => 'red and white' ],
                            ],
                            [
                                'conditions' => [ [ 'attribute' => 'userId', 'operator' => 'equals', 'value' => 'user-gb' ] ],
                                'enabled' => false,
                            ],
                            [
                                'conditions' => [ [ 'attribute' => 'userId', 'operator' => 'equals', 'value' => 'user-forced-variation' ] ],
                                'enabled' => true,
                                'variation' => 'treatment',
                            ],
                        ],
                        'traffic' => [
                            [
                                'key' => '2',
                                'segments' => ['belgium'],
                                'percentage' => 100000,
                                'allocation' => [
                                    [ 'variation' => 'control', 'range' => [0, 0] ],
                                    [ 'variation' => 'treatment', 'range' => [0, 100000] ],
                                ],
                                'variation' => 'control',
                                'variables' => [ 'color' => 'black' ],
                            ],
                            [
                                'key' => '1',
                                'segments' => '*',
                                'percentage' => 100000,
                                'allocation' => [
                                    [ 'variation' => 'control', 'range' => [0, 0] ],
                                    [ 'variation' => 'treatment', 'range' => [0, 100000] ],
                                ],
                            ],
                        ],
                    ],
                    'anotherTest' => [
                        'key' => 'test',
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
                'segments' => [
                    'netherlands' => [
                        'key' => 'netherlands',
                        'conditions' => json_encode([
                            [ 'attribute' => 'country', 'operator' => 'equals', 'value' => 'nl' ]
                        ]),
                    ],
                    'belgium' => [
                        'key' => 'belgium',
                        'conditions' => json_encode([
                            [ 'attribute' => 'country', 'operator' => 'equals', 'value' => 'be' ]
                        ]),
                    ],
                ],
            ],
            'context' => [ 'appVersion' => '1.0.0' ],
        ]);

        $this->assertNotNull($f);
        $this->assertEquals(['appVersion' => '1.0.0'], $f->getContext());

        $childF = $f->spawn([
            'userId' => '123',
            'country' => 'nl',
        ]);
        $this->assertNotNull($childF);
        $this->assertEquals([
            'appVersion' => '1.0.0',
            'userId' => '123',
            'country' => 'nl',
        ], $childF->getContext());

        $contextUpdated = false;
        $unsubscribeContext = $childF->on('context_set', function () use (&$contextUpdated) {
            $contextUpdated = true;
        });

        $childF->setContext(['country' => 'be']);
        $this->assertEquals([
            'appVersion' => '1.0.0',
            'userId' => '123',
            'country' => 'be',
        ], $childF->getContext());

        $this->assertTrue($childF->isEnabled('test'));
        $this->assertEquals('control', $childF->getVariation('test'));

        $this->assertEquals('black', $childF->getVariable('test', 'color'));
        $this->assertEquals('black', $childF->getVariableString('test', 'color'));

        $this->assertEquals(false, $childF->getVariable('test', 'showSidebar'));
        $this->assertEquals(false, $childF->getVariableBoolean('test', 'showSidebar'));

        $this->assertEquals('sidebar title', $childF->getVariable('test', 'sidebarTitle'));
        $this->assertEquals('sidebar title', $childF->getVariableString('test', 'sidebarTitle'));

        $this->assertEquals(0, $childF->getVariable('test', 'count'));
        $this->assertEquals(0, $childF->getVariableInteger('test', 'count'));

        $this->assertEquals(9.99, $childF->getVariable('test', 'price'));
        $this->assertEquals(9.99, $childF->getVariableDouble('test', 'price'));

        $this->assertEquals(['paypal', 'creditcard'], $childF->getVariable('test', 'paymentMethods'));
        $this->assertEquals(['paypal', 'creditcard'], $childF->getVariableArray('test', 'paymentMethods'));

        $this->assertEquals(['key' => 'value'], $childF->getVariable('test', 'flatConfig'));
        $this->assertEquals(['key' => 'value'], $childF->getVariableObject('test', 'flatConfig'));

        $this->assertEquals(['key' => ['nested' => 'value']], $childF->getVariable('test', 'nestedConfig'));
        $this->assertEquals(['key' => ['nested' => 'value']], $childF->getVariableJSON('test', 'nestedConfig'));

        $this->assertTrue($contextUpdated);
        $unsubscribeContext();

        $this->assertFalse($childF->isEnabled('newFeature'));
        $childF->setSticky([
            'newFeature' => [ 'enabled' => true ]
        ]);
        $this->assertTrue($childF->isEnabled('newFeature'));

        $allEvaluations = $childF->getAllEvaluations();
        $this->assertEquals(['test', 'anotherTest'], array_keys($allEvaluations));

        $childF->close();
    }
}
