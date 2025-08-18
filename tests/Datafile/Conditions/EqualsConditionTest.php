<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\EqualsCondition;
use PHPUnit\Framework\TestCase;

class EqualsConditionTest extends TestCase
{
    public function testEqualsConditionWithSimpleAttributeMatching(): void
    {
        $context = [
            'country' => 'us',
        ];

        $condition = new EqualsCondition('country', 'us');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testEqualsConditionWithSimpleAttributeNotMatching(): void
    {
        $context = [
            'country' => 'ca',
        ];

        $condition = new EqualsCondition('country', 'us');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testEqualsConditionWithMissingAttribute(): void
    {
        $context = [
            'other_attribute' => 'value',
        ];

        $condition = new EqualsCondition('country', 'us');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testEqualsConditionWithNestedAttributeMatching(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'country' => 'us',
                ],
            ],
        ];

        $condition = new EqualsCondition('user.profile.country', 'us');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testEqualsConditionWithNestedAttributeNotMatching(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'country' => 'ca',
                ],
            ],
        ];

        $condition = new EqualsCondition('user.profile.country', 'us');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testEqualsConditionWithMissingNestedAttribute(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'name' => 'John',
                ],
            ],
        ];

        $condition = new EqualsCondition('user.profile.country', 'us');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testEqualsConditionWithDifferentTypes(): void
    {
        $context = [
            'age' => '25', // string
        ];

        $condition = new EqualsCondition('age', 25); // integer

        self::assertFalse($condition->isSatisfiedBy($context));
    }
}
