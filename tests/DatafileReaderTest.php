<?php

namespace Featurevisor\Tests;

use PHPUnit\Framework\TestCase;

use Featurevisor\DatafileReader;
use function Featurevisor\createLogger;

class DatafileReaderTest extends TestCase {

    public function testV2DatafileSchemaEntities() {
        $datafileJson = [
            'schemaVersion' => '2',
            'revision' => '1',
            'segments' => [
                'netherlands' => [
                    'key' => 'netherlands',
                    'conditions' => [
                        [ 'attribute' => 'country', 'operator' => 'equals', 'value' => 'nl' ],
                    ],
                ],
                'germany' => [
                    'key' => 'germany',
                    'conditions' => json_encode([
                        [ 'attribute' => 'country', 'operator' => 'equals', 'value' => 'de' ],
                    ]),
                ],
            ],
            'features' => [
                'test' => [
                    'key' => 'test',
                    'bucketBy' => 'userId',
                    'variations' => [
                        [ 'value' => 'control' ],
                        [ 'value' => 'treatment', 'variables' => [ 'showSidebar' => true ] ],
                    ],
                    'traffic' => [
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
            ],
        ];
        $logger = createLogger();
        $reader = new DatafileReader([
            'datafile' => $datafileJson,
            'logger' => $logger,
        ]);
        self::assertEquals('1', $reader->getRevision());
        self::assertEquals('2', $reader->getSchemaVersion());
        self::assertEquals($datafileJson['segments']['netherlands'], $reader->getSegment('netherlands'));
        self::assertEquals('de', $reader->getSegment('germany')['conditions'][0]['value']);
        self::assertNull($reader->getSegment('belgium'));
        self::assertEquals($datafileJson['features']['test'], $reader->getFeature('test'));
        self::assertNull($reader->getFeature('test2'));
    }

    public function testSegmentsMatching() {
        $groups = [
            [ 'key' => '*', 'segments' => '*' ],
            [ 'key' => 'dutchMobileUsers', 'segments' => ['mobileUsers', 'netherlands'] ],
            [ 'key' => 'dutchMobileUsers2', 'segments' => [ 'and' => ['mobileUsers', 'netherlands'] ] ],
            [ 'key' => 'dutchMobileOrDesktopUsers', 'segments' => ['netherlands', [ 'or' => ['mobileUsers', 'desktopUsers'] ]] ],
            [ 'key' => 'dutchMobileOrDesktopUsers2', 'segments' => [ 'and' => ['netherlands', [ 'or' => ['mobileUsers', 'desktopUsers'] ]] ] ],
            [ 'key' => 'germanMobileUsers', 'segments' => [ [ 'and' => ['mobileUsers', 'germany'] ] ] ],
            [ 'key' => 'germanNonMobileUsers', 'segments' => [ [ 'and' => ['germany', [ 'not' => ['mobileUsers'] ]] ] ] ],
            [ 'key' => 'notVersion5.5', 'segments' => [ [ 'not' => ['version_5.5'] ] ] ],
        ];
        $datafileContent = [
            'schemaVersion' => '2',
            'revision' => '1',
            'features' => [],
            'segments' => [
                'mobileUsers' => [
                    'key' => 'mobileUsers',
                    'conditions' => [ [ 'attribute' => 'deviceType', 'operator' => 'equals', 'value' => 'mobile' ] ],
                ],
                'desktopUsers' => [
                    'key' => 'desktopUsers',
                    'conditions' => [ [ 'attribute' => 'deviceType', 'operator' => 'equals', 'value' => 'desktop' ] ],
                ],
                'chromeBrowser' => [
                    'key' => 'chromeBrowser',
                    'conditions' => [ [ 'attribute' => 'browser', 'operator' => 'equals', 'value' => 'chrome' ] ],
                ],
                'firefoxBrowser' => [
                    'key' => 'firefoxBrowser',
                    'conditions' => [ [ 'attribute' => 'browser', 'operator' => 'equals', 'value' => 'firefox' ] ],
                ],
                'netherlands' => [
                    'key' => 'netherlands',
                    'conditions' => [ [ 'attribute' => 'country', 'operator' => 'equals', 'value' => 'nl' ] ],
                ],
                'germany' => [
                    'key' => 'germany',
                    'conditions' => [ [ 'attribute' => 'country', 'operator' => 'equals', 'value' => 'de' ] ],
                ],
                'version_5.5' => [
                    'key' => 'version_5.5',
                    'conditions' => [ [ 'or' => [
                        [ 'attribute' => 'version', 'operator' => 'equals', 'value' => '5.5' ],
                        [ 'attribute' => 'version', 'operator' => 'equals', 'value' => 5.5 ],
                    ] ] ],
                ],
            ],
        ];
        $logger = createLogger();
        $datafileReader = new DatafileReader([
            'datafile' => $datafileContent,
            'logger' => $logger,
        ]);
        // everyone
        $group = $groups[0];
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], []));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['foo' => 'foo']));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['bar' => 'bar']));
        // dutchMobileUsers
        $group = $groups[1];
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'mobile']));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'mobile', 'browser' => 'chrome']));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], []));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'de', 'deviceType' => 'mobile']));
        // dutchMobileUsers2 (same as above)
        $group = $groups[1];
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'mobile']));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'mobile', 'browser' => 'chrome']));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], []));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'de', 'deviceType' => 'mobile']));
        // dutchMobileOrDesktopUsers
        $group = $groups[3];
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'mobile']));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'mobile', 'browser' => 'chrome']));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'desktop']));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'desktop', 'browser' => 'chrome']));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], []));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'de', 'deviceType' => 'mobile']));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'de', 'deviceType' => 'desktop']));
        // dutchMobileOrDesktopUsers2
        $group = $groups[4];
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'mobile']));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'mobile', 'browser' => 'chrome']));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'desktop']));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'desktop', 'browser' => 'chrome']));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], []));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'de', 'deviceType' => 'mobile']));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'de', 'deviceType' => 'desktop']));
        // germanMobileUsers
        $group = $groups[5];
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'de', 'deviceType' => 'mobile']));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'de', 'deviceType' => 'mobile', 'browser' => 'chrome']));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], []));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'mobile']));
        // germanNonMobileUsers
        $group = $groups[6];
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'de', 'deviceType' => 'desktop']));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'de', 'deviceType' => 'desktop', 'browser' => 'chrome']));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], []));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], ['country' => 'nl', 'deviceType' => 'desktop']));
        // notVersion5.5
        $group = $groups[7];
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], []));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], []));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['version' => '5.6']));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['version' => 5.6]));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['version' => '5.7']));
        self::assertTrue($datafileReader->allSegmentsAreMatched($group['segments'], ['version' => 5.7]));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], ['version' => '5.5']));
        self::assertFalse($datafileReader->allSegmentsAreMatched($group['segments'], ['version' => 5.5]));
    }
}
