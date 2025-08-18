<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\GreaterThanOrEqualsCondition;
use PHPUnit\Framework\TestCase;

class GreaterThanOrEqualsConditionTest extends TestCase
{
    public function testGreaterThanOrEqualsConditionWithSimpleAttributeGreater(): void
    {
        $context = [
            'age' => 25,
        ];

        $condition = new GreaterThanOrEqualsCondition('age', 21);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanOrEqualsConditionWithSimpleAttributeEqual(): void
    {
        $context = [
            'age' => 21,
        ];

        $condition = new GreaterThanOrEqualsCondition('age', 21);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanOrEqualsConditionWithSimpleAttributeLess(): void
    {
        $context = [
            'age' => 18,
        ];

        $condition = new GreaterThanOrEqualsCondition('age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanOrEqualsConditionWithMissingAttribute(): void
    {
        $context = [
            'other_attribute' => 25,
        ];

        $condition = new GreaterThanOrEqualsCondition('age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanOrEqualsConditionWithNestedAttributeGreater(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'age' => 25,
                ],
            ],
        ];

        $condition = new GreaterThanOrEqualsCondition('user.profile.age', 21);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanOrEqualsConditionWithNestedAttributeEqual(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'age' => 21,
                ],
            ],
        ];

        $condition = new GreaterThanOrEqualsCondition('user.profile.age', 21);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanOrEqualsConditionWithNestedAttributeLess(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'age' => 18,
                ],
            ],
        ];

        $condition = new GreaterThanOrEqualsCondition('user.profile.age', 21);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanOrEqualsConditionWithFloatValues(): void
    {
        $context = [
            'score' => 9.0,
        ];

        $condition = new GreaterThanOrEqualsCondition('score', 9.0);

        self::assertTrue($condition->isSatisfiedBy($context));

        $context = [
            'score' => 9.5,
        ];

        self::assertTrue($condition->isSatisfiedBy($context));

        $context = [
            'score' => 8.5,
        ];

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testGreaterThanOrEqualsConditionWithInvalidValueType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('GreaterThanOrEqualCondition value must be float or integer');

        new GreaterThanOrEqualsCondition('age', 'not_a_number');
    }
}
