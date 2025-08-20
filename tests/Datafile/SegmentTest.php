<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile;

use Featurevisor\Datafile\Conditions;
use Featurevisor\Datafile\Conditions\GreaterThanCondition;
use Featurevisor\Datafile\Segment;
use Featurevisor\Tests\Datafile\Fixture\SegmentFixture;
use PHPUnit\Framework\TestCase;

class SegmentTest extends TestCase
{
    /**
     * @dataProvider segmentDataProvider
     */
    public function testCreateFromArray(array $segmentData, Segment $expectedResult): void
    {
        $result = Segment::createFromArray($segmentData);

        self::assertEquals($expectedResult, $result);
    }

    public function segmentDataProvider(): iterable
    {
        yield 'everyone' => [
            SegmentFixture::everyone(),
            new Segment(
                'Everyone',
                new Conditions(new Conditions\EveryoneCondition()),
                false
            )
        ];

        yield 'simple_conditions' => [
            SegmentFixture::simpleCondition(),
            new Segment(
                'Simple condition',
                new Conditions(new GreaterThanCondition('age', 21)),
                false
            )
        ];

        yield 'complex_expressions' => [
            SegmentFixture::complexExpressions(),
            new Segment(
                'Complex expressions',
                new Conditions(new Conditions\AndCondition(
                    new Conditions\AndCondition(
                        new Conditions\StartsWithCondition('device', 'iPhone'),
                        new Conditions\NotCondition(
                            new Conditions\OrCondition(
                                new Conditions\EqualsCondition('country', 'us'),
                                new Conditions\EqualsCondition('country', 'ca'),
                            )
                        )
                    ),
                    new Conditions\NotCondition(
                        new Conditions\LessThanCondition('age', 21)
                    )
                )),
                false
            )
        ];

        yield 'switzerland' => [
            SegmentFixture::switzerland(),
            new Segment(
                'users from Switzerland',
                new Conditions(new Conditions\AndCondition(new Conditions\EqualsCondition('country', 'ch'))),
                false
            )
        ];
    }
}
