<?php

namespace Featurevisor\Tests;

use Featurevisor\Events;
use PHPUnit\Framework\TestCase;

final class EventsTest extends TestCase
{
    public function testGetParamsForStickyFeaturesSetEventEmptyToNew(): void
    {
        self::assertSame([
            'features' => ['feature2', 'feature3'],
            'replaced' => true,
        ], Events::getParamsForStickyFeaturesSetEvent([], [
            'feature2' => ['enabled' => true],
            'feature3' => ['enabled' => true],
        ], true));
    }

    public function testGetParamsForStickyFeaturesSetEventAddChangeRemove(): void
    {
        self::assertSame([
            'features' => ['feature1', 'feature2', 'feature3'],
            'replaced' => true,
        ], Events::getParamsForStickyFeaturesSetEvent([
            'feature1' => ['enabled' => true],
            'feature2' => ['enabled' => true],
        ], [
            'feature2' => ['enabled' => true],
            'feature3' => ['enabled' => true],
        ], true));
    }

    /** @param array<string, array<string, mixed>> $features */
    private function datafile(string $revision, array $features): array
    {
        return [
            'schemaVersion' => '2',
            'revision' => $revision,
            'features' => $features,
            'segments' => [],
        ];
    }

    public function testGetParamsForDatafileSetEventEmptyToNew(): void
    {
        $result = Events::getParamsForDatafileSetEvent(
            $this->datafile('1', []),
            $this->datafile('2', [
                'feature1' => ['hash' => 'hash1'],
                'feature2' => ['hash' => 'hash2'],
            ])
        );

        self::assertSame([
            'revision' => '2',
            'previousRevision' => '1',
            'revisionChanged' => true,
            'features' => ['feature1', 'feature2'],
            'variables' => [],
            'replaced' => false,
        ], $result);
    }

    public function testGetParamsForDatafileSetEventChangeHashAddition(): void
    {
        $result = Events::getParamsForDatafileSetEvent(
            $this->datafile('1', [
                'feature1' => ['hash' => 'same'],
                'feature2' => ['hash' => 'old'],
            ]),
            $this->datafile('2', [
                'feature1' => ['hash' => 'same'],
                'feature2' => ['hash' => 'new'],
                'feature3' => ['hash' => 'added'],
            ])
        );

        self::assertSame(['feature2', 'feature3'], $result['features']);
    }

    public function testGetParamsForDatafileSetEventChangeHashRemoval(): void
    {
        $result = Events::getParamsForDatafileSetEvent(
            $this->datafile('1', [
                'feature1' => ['hash' => 'same'],
                'feature2' => ['hash' => 'old'],
            ]),
            $this->datafile('2', [
                'feature2' => ['hash' => 'new'],
            ])
        );

        self::assertSame(['feature1', 'feature2'], $result['features']);
    }
}
