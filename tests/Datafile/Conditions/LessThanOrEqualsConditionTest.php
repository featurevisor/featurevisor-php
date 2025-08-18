<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\LessThanOrEqualsCondition;
use PHPUnit\Framework\TestCase;

class LessThanOrEqualsConditionTest extends TestCase
{
    public function testLessThanOrEqualsConditionWithSimpleAttributeLess(): void
    {
        $context = [
            'age' => 18,
        ];

        $condition = new LessThanOrEqualsCondition('age', 21);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testLessThanOrEqualsConditionWithSimpleAttributeEqual(): void
    {
        $context = [
            'age' => 21,
        ];

        $condition = new LessThanOrEqualsCondition('age', 21);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testLessThanOrEqualsConditionWithSimpleAttributeGreater(): void
    {
        $context = [
            'age' => 25,
        ];

        $condition = new LessThanOrEqualsCondition('age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testLessThanOrEqualsConditionWithMissingAttribute(): void
    {
        $context = [
            'other_attribute' => 18,
        ];

        $condition = new LessThanOrEqualsCondition('age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testLessThanOrEqualsConditionWithNestedAttributeLess(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'age' => 18,
                ],
            ],
        ];

        $condition = new LessThanOrEqualsCondition('user.profile.age', 21);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testLessThanOrEqualsConditionWithNestedAttributeEqual(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'age' => 21,
                ],
            ],
        ];

        $condition = new LessThanOrEqualsCondition('user.profile.age', 21);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testLessThanOrEqualsConditionWithNestedAttributeGreater(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'age' => 25,
                ],
            ],
        ];

        $condition = new LessThanOrEqualsCondition('user.profile.age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testLessThanOrEqualsConditionWithFloatValues(): void
    {
        $context = [
            'score' => 8.5,
        ];

        $condition = new LessThanOrEqualsCondition('score', 9.0);

        self::assertTrue($condition->isSatisfiedBy($context));

        $context = [
            'score' => 9.0,
        ];

        self::assertTrue($condition->isSatisfiedBy($context));

        $context = [
            'score' => 9.5,
        ];

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testLessThanOrEqualsConditionWithInvalidValueType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LessThanOrEqualsCondition value must be float or integer');

        new LessThanOrEqualsCondition('age', 'not_a_number');
    }
}
