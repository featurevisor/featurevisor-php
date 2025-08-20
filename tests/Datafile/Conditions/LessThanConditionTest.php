<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\AttributeException;
use Featurevisor\Datafile\Conditions\LessThanCondition;
use PHPUnit\Framework\TestCase;

class LessThanConditionTest extends TestCase
{
    public function testLessThanConditionWithSimpleAttributeLess(): void
    {
        $context = [
            'age' => 18,
        ];

        $condition = new LessThanCondition('age', 21);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testLessThanConditionWithSimpleAttributeEqual(): void
    {
        $context = [
            'age' => 21,
        ];

        $condition = new LessThanCondition('age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testLessThanConditionWithSimpleAttributeGreater(): void
    {
        $context = [
            'age' => 25,
        ];

        $condition = new LessThanCondition('age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testLessThanConditionWithMissingAttribute(): void
    {
        $this->expectException(AttributeException::class);

        $context = [
            'other_attribute' => 18,
        ];
        $condition = new LessThanCondition('age', 21);

        $condition->isSatisfiedBy($context);
    }

    public function testLessThanConditionWithNestedAttributeLess(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'age' => 18,
                ],
            ],
        ];

        $condition = new LessThanCondition('user.profile.age', 21);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testLessThanConditionWithNestedAttributeEqual(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'age' => 21,
                ],
            ],
        ];

        $condition = new LessThanCondition('user.profile.age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testLessThanConditionWithNestedAttributeGreater(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'age' => 25,
                ],
            ],
        ];

        $condition = new LessThanCondition('user.profile.age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testLessThanConditionWithFloatValues(): void
    {
        $context = [
            'score' => 8.5,
        ];

        $condition = new LessThanCondition('score', 9.0);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testLessThanConditionWithInvalidValueType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LessThanCondition value must be float or integer');

        new LessThanCondition('age', 'not_a_number');
    }
}
