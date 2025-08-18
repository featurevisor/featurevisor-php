<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use DateTimeImmutable;
use Featurevisor\Datafile\Conditions\BeforeCondition;
use PHPUnit\Framework\TestCase;

class BeforeConditionTest extends TestCase
{
    public function testBeforeConditionWithDateBefore(): void
    {
        $context = [
            'date' => '2022-12-15',
        ];

        $condition = new BeforeCondition('date', new DateTimeImmutable('2023-01-01'));

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testBeforeConditionWithDateEqual(): void
    {
        $context = [
            'date' => '2023-01-01',
        ];

        $condition = new BeforeCondition('date', new DateTimeImmutable('2023-01-01'));

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testBeforeConditionWithDateAfter(): void
    {
        $context = [
            'date' => '2023-01-15',
        ];

        $condition = new BeforeCondition('date', new DateTimeImmutable('2023-01-01'));

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testBeforeConditionWithNestedAttributeBefore(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'registrationDate' => '2022-12-15',
                ],
            ],
        ];

        $condition = new BeforeCondition('user.profile.registrationDate', new DateTimeImmutable('2023-01-01'));

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testBeforeConditionWithNestedAttributeAfter(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'registrationDate' => '2023-01-15',
                ],
            ],
        ];

        $condition = new BeforeCondition('user.profile.registrationDate', new DateTimeImmutable('2023-01-01'));

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testBeforeConditionWithMissingAttribute(): void
    {
        $context = [
            'other_attribute' => '2022-12-15',
        ];

        $condition = new BeforeCondition('date', new DateTimeImmutable('2023-01-01'));

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testBeforeConditionWithInvalidDateFormat(): void
    {
        $context = [
            'date' => 'not-a-date',
        ];

        $condition = new BeforeCondition('date', new DateTimeImmutable('2023-01-01'));

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testBeforeConditionWithDateTimeFormat(): void
    {
        $context = [
            'date' => '2022-12-15 12:30:45',
        ];

        $condition = new BeforeCondition('date', new DateTimeImmutable('2023-01-01 00:00:00'));

        self::assertTrue($condition->isSatisfiedBy($context));
    }
}
