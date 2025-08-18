<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\EqualsCondition;
use Featurevisor\Datafile\Conditions\GreaterThanCondition;
use Featurevisor\Datafile\Conditions\OrCondition;
use PHPUnit\Framework\TestCase;

class OrConditionTest extends TestCase
{
    public function testOrConditionWithAllConditionsSatisfied(): void
    {
        $context = [
            'country' => 'us',
            'age' => 25,
        ];

        $condition = new OrCondition(
            new EqualsCondition('country', 'us'),
            new GreaterThanCondition('age', 21)
        );

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testOrConditionWithOneConditionSatisfied(): void
    {
        $context = [
            'country' => 'ca',
            'age' => 25,
        ];

        $condition = new OrCondition(
            new EqualsCondition('country', 'us'),
            new GreaterThanCondition('age', 21)
        );

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testOrConditionWithAnotherConditionSatisfied(): void
    {
        $context = [
            'country' => 'us',
            'age' => 18,
        ];

        $condition = new OrCondition(
            new EqualsCondition('country', 'us'),
            new GreaterThanCondition('age', 21)
        );

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testOrConditionWithNoConditionsSatisfied(): void
    {
        $context = [
            'country' => 'ca',
            'age' => 18,
        ];

        $condition = new OrCondition(
            new EqualsCondition('country', 'us'),
            new GreaterThanCondition('age', 21)
        );

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testOrConditionWithNestedOrCondition(): void
    {
        $context = [
            'country' => 'ca',
            'age' => 18,
            'device' => 'iPhone',
        ];

        $condition = new OrCondition(
            new OrCondition(
                new EqualsCondition('country', 'us'),
                new GreaterThanCondition('age', 21)
            ),
            new EqualsCondition('device', 'iPhone')
        );

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testOrConditionWithNestedOrConditionNotSatisfied(): void
    {
        $context = [
            'country' => 'ca',
            'age' => 18,
            'device' => 'Android',
        ];

        $condition = new OrCondition(
            new OrCondition(
                new EqualsCondition('country', 'us'),
                new GreaterThanCondition('age', 21)
            ),
            new EqualsCondition('device', 'iPhone')
        );

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testOrConditionWithNoConditions(): void
    {
        $context = [
            'country' => 'us',
            'age' => 25,
        ];

        $condition = new OrCondition();

        self::assertFalse($condition->isSatisfiedBy($context));
    }
}
