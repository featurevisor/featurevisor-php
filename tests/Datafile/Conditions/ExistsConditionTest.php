<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\ExistsCondition;
use PHPUnit\Framework\TestCase;

class ExistsConditionTest extends TestCase
{
    public function testExistsConditionWithSimpleAttribute(): void
    {
        $context = [
            'attribute' => 'value',
        ];

        $condition = new ExistsCondition('attribute');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testExistsConditionWithMissingAttribute(): void
    {
        $context = [
            'other_attribute' => 'value',
        ];

        $condition = new ExistsCondition('attribute');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testExistsConditionWithNestedAttribute(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'age' => 25,
                ],
            ],
        ];

        $condition = new ExistsCondition('user.profile.age');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testExistsConditionWithMissingNestedAttribute(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'name' => 'John',
                ],
            ],
        ];

        $condition = new ExistsCondition('user.profile.age');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testExistsConditionWithPartiallyMissingNestedAttribute(): void
    {
        $context = [
            'user' => [
                'name' => 'John',
            ],
        ];

        $condition = new ExistsCondition('user.profile.age');

        self::assertFalse($condition->isSatisfiedBy($context));
    }
}
