<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\StartsWithCondition;
use PHPUnit\Framework\TestCase;

class StartsWithConditionTest extends TestCase
{
    public function testStartsWithConditionWithSimpleAttributeStartingWith(): void
    {
        $context = [
            'device' => 'iPhone 12',
        ];

        $condition = new StartsWithCondition('device', 'iPhone');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testStartsWithConditionWithSimpleAttributeNotStartingWith(): void
    {
        $context = [
            'device' => 'Android iPhone',
        ];

        $condition = new StartsWithCondition('device', 'iPhone');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testStartsWithConditionWithMissingAttribute(): void
    {
        $context = [
            'other_attribute' => 'iPhone 12',
        ];

        $condition = new StartsWithCondition('device', 'iPhone');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testStartsWithConditionWithNestedAttributeStartingWith(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'device' => 'iPhone 12',
                ],
            ],
        ];

        $condition = new StartsWithCondition('user.profile.device', 'iPhone');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testStartsWithConditionWithNestedAttributeNotStartingWith(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'device' => 'Android iPhone',
                ],
            ],
        ];

        $condition = new StartsWithCondition('user.profile.device', 'iPhone');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testStartsWithConditionWithEmptyString(): void
    {
        $context = [
            'device' => 'iPhone 12',
        ];

        $condition = new StartsWithCondition('device', '');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testStartsWithConditionWithCaseSensitivity(): void
    {
        $context = [
            'device' => 'iPhone 12',
        ];

        $condition = new StartsWithCondition('device', 'iphone');

        self::assertFalse($condition->isSatisfiedBy($context));
    }
}
