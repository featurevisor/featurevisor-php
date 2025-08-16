<?php

namespace Featurevisor\Tests;

use PHPUnit\Framework\TestCase;

use Featurevisor\Events;
use Featurevisor\DatafileReader;
use function Featurevisor\createLogger;

class EventsTest extends TestCase
{
    public function testGetParamsForStickySetEventEmptyToNew()
    {
        $previousStickyFeatures = [];
        $newStickyFeatures = [
            'feature2' => ['enabled' => true],
            'feature3' => ['enabled' => true],
        ];
        $replace = true;

        $result = Events::getParamsForStickySetEvent($previousStickyFeatures, $newStickyFeatures, $replace);

        $this->assertEquals([
            'features' => ['feature2', 'feature3'],
            'replaced' => $replace,
        ], $result);
    }

    public function testGetParamsForStickySetEventAddChangeRemove()
    {
        $previousStickyFeatures = [
            'feature1' => ['enabled' => true],
            'feature2' => ['enabled' => true],
        ];
        $newStickyFeatures = [
            'feature2' => ['enabled' => true],
            'feature3' => ['enabled' => true],
        ];
        $replace = true;

        $result = Events::getParamsForStickySetEvent($previousStickyFeatures, $newStickyFeatures, $replace);

        $this->assertEquals([
            'features' => ['feature1', 'feature2', 'feature3'],
            'replaced' => $replace,
        ], $result);
    }

    public function testGetParamsForDatafileSetEventEmptyToNew()
    {
        $logger = createLogger([
            'level' => 'error',
        ]);

        $previousDatafileReader = new DatafileReader([
            'datafile' => [
                'schemaVersion' => '1.0.0',
                'revision' => '1',
                'features' => [],
                'segments' => [],
            ],
            'logger' => $logger,
        ]);

        $newDatafileReader = new DatafileReader([
            'datafile' => [
                'schemaVersion' => '1.0.0',
                'revision' => '2',
                'features' => [
                    'feature1' => ['bucketBy' => 'userId', 'hash' => 'hash1', 'traffic' => []],
                    'feature2' => ['bucketBy' => 'userId', 'hash' => 'hash2', 'traffic' => []],
                ],
                'segments' => [],
            ],
            'logger' => $logger,
        ]);

        $result = Events::getParamsForDatafileSetEvent($previousDatafileReader, $newDatafileReader);

        $this->assertEquals([
            'revision' => '2',
            'previousRevision' => '1',
            'revisionChanged' => true,
            'features' => ['feature1', 'feature2'],
        ], $result);
    }

    public function testGetParamsForDatafileSetEventChangeHashAddition()
    {
        $logger = createLogger([
            'level' => 'error',
        ]);

        $previousDatafileReader = new DatafileReader([
            'datafile' => [
                'schemaVersion' => '1.0.0',
                'revision' => '1',
                'features' => [
                    'feature1' => ['bucketBy' => 'userId', 'hash' => 'hash-same', 'traffic' => []],
                    'feature2' => ['bucketBy' => 'userId', 'hash' => 'hash1-2', 'traffic' => []],
                ],
                'segments' => [],
            ],
            'logger' => $logger,
        ]);

        $newDatafileReader = new DatafileReader([
            'datafile' => [
                'schemaVersion' => '1.0.0',
                'revision' => '2',
                'features' => [
                    'feature1' => ['bucketBy' => 'userId', 'hash' => 'hash-same', 'traffic' => []],
                    'feature2' => ['bucketBy' => 'userId', 'hash' => 'hash2-2', 'traffic' => []],
                    'feature3' => ['bucketBy' => 'userId', 'hash' => 'hash2-3', 'traffic' => []],
                ],
                'segments' => [],
            ],
            'logger' => $logger,
        ]);

        $result = Events::getParamsForDatafileSetEvent($previousDatafileReader, $newDatafileReader);

        $this->assertEquals([
            'revision' => '2',
            'previousRevision' => '1',
            'revisionChanged' => true,
            'features' => ['feature2', 'feature3'],
        ], $result);
    }

    public function testGetParamsForDatafileSetEventChangeHashRemoval()
    {
        $logger = createLogger([
            'level' => 'error',
        ]);

        $previousDatafileReader = new DatafileReader([
            'datafile' => [
                'schemaVersion' => '1.0.0',
                'revision' => '1',
                'features' => [
                    'feature1' => ['bucketBy' => 'userId', 'hash' => 'hash-same', 'traffic' => []],
                    'feature2' => ['bucketBy' => 'userId', 'hash' => 'hash1-2', 'traffic' => []],
                ],
                'segments' => [],
            ],
            'logger' => $logger,
        ]);

        $newDatafileReader = new DatafileReader([
            'datafile' => [
                'schemaVersion' => '1.0.0',
                'revision' => '2',
                'features' => [
                    'feature2' => ['bucketBy' => 'userId', 'hash' => 'hash2-2', 'traffic' => []],
                ],
                'segments' => [],
            ],
            'logger' => $logger,
        ]);

        $result = Events::getParamsForDatafileSetEvent($previousDatafileReader, $newDatafileReader);

        $this->assertEquals([
            'revision' => '2',
            'previousRevision' => '1',
            'revisionChanged' => true,
            'features' => ['feature1', 'feature2'],
        ], $result);
    }
}
