<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use DateTimeImmutable;
use Featurevisor\Datafile\AttributeException;
use Featurevisor\Datafile\Conditions\AfterCondition;
use PHPUnit\Framework\TestCase;

class AfterConditionTest extends TestCase
{
    public function testAfterConditionWithDateAfter(): void
    {
        $context = [
            'date' => '2023-01-15',
        ];

        $condition = new AfterCondition('date', new DateTimeImmutable('2023-01-01'));

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testAfterConditionWithDateEqual(): void
    {
        $context = [
            'date' => '2023-01-01',
        ];

        $condition = new AfterCondition('date', new DateTimeImmutable('2023-01-01'));

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testAfterConditionWithDateBefore(): void
    {
        $context = [
            'date' => '2022-12-15',
        ];

        $condition = new AfterCondition('date', new DateTimeImmutable('2023-01-01'));

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testAfterConditionWithNestedAttributeAfter(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'registrationDate' => '2023-01-15',
                ],
            ],
        ];

        $condition = new AfterCondition('user.profile.registrationDate', new DateTimeImmutable('2023-01-01'));

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testAfterConditionWithNestedAttributeBefore(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'registrationDate' => '2022-12-15',
                ],
            ],
        ];

        $condition = new AfterCondition('user.profile.registrationDate', new DateTimeImmutable('2023-01-01'));

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testAfterConditionWithMissingAttribute(): void
    {
        $this->expectException(AttributeException::class);

        $context = [
            'other_attribute' => '2023-01-15',
        ];
        $condition = new AfterCondition('date', new DateTimeImmutable('2023-01-01'));

        $condition->isSatisfiedBy($context);
    }

    public function testAfterConditionWithInvalidDateFormat(): void
    {
        $context = [
            'date' => 'not-a-date',
        ];

        $condition = new AfterCondition('date', new DateTimeImmutable('2023-01-01'));

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testAfterConditionWithDateTimeFormat(): void
    {
        $context = [
            'date' => '2023-01-15 12:30:45',
        ];

        $condition = new AfterCondition('date', new DateTimeImmutable('2023-01-01 00:00:00'));

        self::assertTrue($condition->isSatisfiedBy($context));
    }
}
