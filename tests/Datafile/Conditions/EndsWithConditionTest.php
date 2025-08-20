<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\AttributeException;
use Featurevisor\Datafile\Conditions\EndsWithCondition;
use PHPUnit\Framework\TestCase;

class EndsWithConditionTest extends TestCase
{
    public function testEndsWithConditionWithSimpleAttributeEndingWith(): void
    {
        $context = [
            'device' => 'My iPhone',
        ];

        $condition = new EndsWithCondition('device', 'iPhone');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testEndsWithConditionWithSimpleAttributeNotEndingWith(): void
    {
        $context = [
            'device' => 'iPhone 12',
        ];

        $condition = new EndsWithCondition('device', 'iPhone');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testEndsWithConditionWithMissingAttribute(): void
    {
        $this->expectException(AttributeException::class);

        $context = [
            'other_attribute' => 'My iPhone',
        ];
        $condition = new EndsWithCondition('device', 'iPhone');

        $condition->isSatisfiedBy($context);
    }

    public function testEndsWithConditionWithNestedAttributeEndingWith(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'device' => 'My iPhone',
                ],
            ],
        ];

        $condition = new EndsWithCondition('user.profile.device', 'iPhone');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testEndsWithConditionWithNestedAttributeNotEndingWith(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'device' => 'iPhone 12',
                ],
            ],
        ];

        $condition = new EndsWithCondition('user.profile.device', 'iPhone');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testEndsWithConditionWithEmptyString(): void
    {
        $context = [
            'device' => 'iPhone 12',
        ];

        $condition = new EndsWithCondition('device', '');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testEndsWithConditionWithCaseSensitivity(): void
    {
        $context = [
            'device' => 'My iPhone',
        ];

        $condition = new EndsWithCondition('device', 'iphone');

        self::assertFalse($condition->isSatisfiedBy($context));
    }
}
