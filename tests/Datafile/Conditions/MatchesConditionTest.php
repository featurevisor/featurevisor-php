<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\MatchesCondition;
use PHPUnit\Framework\TestCase;

class MatchesConditionTest extends TestCase
{
    public function testMatchesConditionWithSimpleAttributeMatching(): void
    {
        $context = [
            'email' => 'user@example.com',
        ];

        $condition = new MatchesCondition('email', '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testMatchesConditionWithSimpleAttributeNotMatching(): void
    {
        $context = [
            'email' => 'invalid-email',
        ];

        $condition = new MatchesCondition('email', '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testMatchesConditionWithMissingAttribute(): void
    {
        $context = [
            'other_attribute' => 'user@example.com',
        ];

        $condition = new MatchesCondition('email', '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testMatchesConditionWithNestedAttributeMatching(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'email' => 'user@example.com',
                ],
            ],
        ];

        $condition = new MatchesCondition('user.profile.email', '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testMatchesConditionWithNestedAttributeNotMatching(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'email' => 'invalid-email',
                ],
            ],
        ];

        $condition = new MatchesCondition('user.profile.email', '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testMatchesConditionWithSimpleRegex(): void
    {
        $context = [
            'zipcode' => '12345',
        ];

        $condition = new MatchesCondition('zipcode', '/^\d{5}$/');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testMatchesConditionWithCaseInsensitiveFlag(): void
    {
        $context = [
            'text' => 'Hello World',
        ];

        $condition = new MatchesCondition('text', '/hello/i');

        self::assertTrue($condition->isSatisfiedBy($context));
    }
}
