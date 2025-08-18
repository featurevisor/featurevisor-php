<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\GreaterThanCondition;
use PHPUnit\Framework\TestCase;

class GreaterThanConditionTest extends TestCase
{
    public function testGreaterThanConditionWithSimpleAttributeGreater(): void
    {
        $context = [
            'age' => 25,
        ];

        $condition = new GreaterThanCondition('age', 21);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanConditionWithSimpleAttributeEqual(): void
    {
        $context = [
            'age' => 21,
        ];

        $condition = new GreaterThanCondition('age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanConditionWithSimpleAttributeLess(): void
    {
        $context = [
            'age' => 18,
        ];

        $condition = new GreaterThanCondition('age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanConditionWithMissingAttribute(): void
    {
        $context = [
            'other_attribute' => 25,
        ];

        $condition = new GreaterThanCondition('age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanConditionWithNestedAttributeGreater(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'age' => 25,
                ],
            ],
        ];

        $condition = new GreaterThanCondition('user.profile.age', 21);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanConditionWithNestedAttributeEqual(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'age' => 21,
                ],
            ],
        ];

        $condition = new GreaterThanCondition('user.profile.age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanConditionWithNestedAttributeLess(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'age' => 18,
                ],
            ],
        ];

        $condition = new GreaterThanCondition('user.profile.age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanConditionWithFloatValues(): void
    {
        $context = [
            'score' => 9.5,
        ];

        $condition = new GreaterThanCondition('score', 9.0);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanConditionWithInvalidValueType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('GreaterThanCondition value must be float or integer');

        new GreaterThanCondition('age', 'not_a_number');
    }
}
