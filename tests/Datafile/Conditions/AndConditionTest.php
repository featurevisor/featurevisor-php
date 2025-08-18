<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\AndCondition;
use Featurevisor\Datafile\Conditions\EqualsCondition;
use Featurevisor\Datafile\Conditions\GreaterThanCondition;
use PHPUnit\Framework\TestCase;

class AndConditionTest extends TestCase
{
    public function testAndConditionWithAllConditionsSatisfied(): void
    {
        $context = [
            'country' => 'us',
            'age' => 25,
        ];

        $condition = new AndCondition(
            new EqualsCondition('country', 'us'),
            new GreaterThanCondition('age', 21)
        );

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testAndConditionWithOneConditionNotSatisfied(): void
    {
        $context = [
            'country' => 'ca',
            'age' => 25,
        ];

        $condition = new AndCondition(
            new EqualsCondition('country', 'us'),
            new GreaterThanCondition('age', 21)
        );

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testAndConditionWithAllConditionsNotSatisfied(): void
    {
        $context = [
            'country' => 'ca',
            'age' => 18,
        ];

        $condition = new AndCondition(
            new EqualsCondition('country', 'us'),
            new GreaterThanCondition('age', 21)
        );

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testAndConditionWithNestedAndCondition(): void
    {
        $context = [
            'country' => 'us',
            'age' => 25,
            'device' => 'iPhone',
        ];

        $condition = new AndCondition(
            new AndCondition(
                new EqualsCondition('country', 'us'),
                new GreaterThanCondition('age', 21)
            ),
            new EqualsCondition('device', 'iPhone')
        );

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testAndConditionWithNestedAndConditionNotSatisfied(): void
    {
        $context = [
            'country' => 'us',
            'age' => 18,
            'device' => 'iPhone',
        ];

        $condition = new AndCondition(
            new AndCondition(
                new EqualsCondition('country', 'us'),
                new GreaterThanCondition('age', 21)
            ),
            new EqualsCondition('device', 'iPhone')
        );

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testAndConditionWithNoConditions(): void
    {
        $context = [
            'country' => 'us',
            'age' => 25,
        ];

        $condition = new AndCondition();

        self::assertTrue($condition->isSatisfiedBy($context));
    }
}
