<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\ContainsCondition;
use PHPUnit\Framework\TestCase;

class ContainsConditionTest extends TestCase
{
    public function testContainsConditionWithSimpleAttributeContaining(): void
    {
        $context = [
            'device' => 'iPhone 12',
        ];

        $condition = new ContainsCondition('device', 'iPhone');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testContainsConditionWithSimpleAttributeNotContaining(): void
    {
        $context = [
            'device' => 'Android',
        ];

        $condition = new ContainsCondition('device', 'iPhone');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testContainsConditionWithMissingAttribute(): void
    {
        $context = [
            'other_attribute' => 'iPhone 12',
        ];

        $condition = new ContainsCondition('device', 'iPhone');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testContainsConditionWithNestedAttributeContaining(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'device' => 'iPhone 12',
                ],
            ],
        ];

        $condition = new ContainsCondition('user.profile.device', 'iPhone');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testContainsConditionWithNestedAttributeNotContaining(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'device' => 'Android',
                ],
            ],
        ];

        $condition = new ContainsCondition('user.profile.device', 'iPhone');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testContainsConditionWithEmptyString(): void
    {
        $context = [
            'device' => 'iPhone 12',
        ];

        $condition = new ContainsCondition('device', '');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testContainsConditionWithCaseSensitivity(): void
    {
        $context = [
            'device' => 'iPhone 12',
        ];

        $condition = new ContainsCondition('device', 'iphone');

        self::assertFalse($condition->isSatisfiedBy($context));
    }
}
