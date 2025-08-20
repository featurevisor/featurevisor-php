<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\AttributeException;
use Featurevisor\Datafile\Conditions\EqualsCondition;
use Featurevisor\Datafile\Conditions\GreaterThanCondition;
use Featurevisor\Datafile\Conditions\NotCondition;
use PHPUnit\Framework\TestCase;

class NotConditionTest extends TestCase
{
    public function testNotConditionWithSatisfiedCondition(): void
    {
        $context = [
            'country' => 'us',
        ];

        $condition = new NotCondition(
            new EqualsCondition('country', 'us')
        );

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testNotConditionWithUnsatisfiedCondition(): void
    {
        $context = [
            'country' => 'ca',
        ];

        $condition = new NotCondition(
            new EqualsCondition('country', 'us')
        );

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testNotConditionWithMissingAttribute(): void
    {
        $this->expectException(AttributeException::class);

        $context = [
            'other_attribute' => 'value',
        ];
        $condition = new NotCondition(
            new EqualsCondition('country', 'us')
        );

        $condition->isSatisfiedBy($context);
    }

    public function testNotConditionWithNestedCondition(): void
    {
        $context = [
            'age' => 18,
        ];

        $condition = new NotCondition(
            new GreaterThanCondition('age', 21)
        );

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testDoubleNotCondition(): void
    {
        $context = [
            'country' => 'us',
        ];

        $condition = new NotCondition(
            new NotCondition(
                new EqualsCondition('country', 'us')
            )
        );

        self::assertTrue($condition->isSatisfiedBy($context));
    }
}
