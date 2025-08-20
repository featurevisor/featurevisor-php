<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\AttributeException;
use Featurevisor\Datafile\Conditions\IncludesCondition;
use PHPUnit\Framework\TestCase;

class IncludesConditionTest extends TestCase
{
    public function testIncludesConditionWithArrayIncludingValue(): void
    {
        $context = [
            'countries' => ['us', 'ca', 'uk'],
        ];

        $condition = new IncludesCondition('countries', 'us');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testIncludesConditionWithArrayNotIncludingValue(): void
    {
        $context = [
            'countries' => ['ca', 'uk', 'fr'],
        ];

        $condition = new IncludesCondition('countries', 'us');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testIncludesConditionWithMissingAttribute(): void
    {
        $this->expectException(AttributeException::class);

        $context = [
            'other_attribute' => ['us', 'ca', 'uk'],
        ];
        $condition = new IncludesCondition('countries', 'us');

        $condition->isSatisfiedBy($context);
    }

    public function testIncludesConditionWithNestedArrayIncludingValue(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'countries' => ['us', 'ca', 'uk'],
                ],
            ],
        ];

        $condition = new IncludesCondition('user.profile.countries', 'us');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testIncludesConditionWithNestedArrayNotIncludingValue(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'countries' => ['ca', 'uk', 'fr'],
                ],
            ],
        ];

        $condition = new IncludesCondition('user.profile.countries', 'us');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testIncludesConditionWithEmptyArray(): void
    {
        $context = [
            'countries' => [],
        ];

        $condition = new IncludesCondition('countries', 'us');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testIncludesConditionWithNonArrayValue(): void
    {
        $context = [
            'country' => 'us',
        ];

        $condition = new IncludesCondition('country', 'us');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testIncludesConditionWithStrictComparison(): void
    {
        $context = [
            'values' => ['42', '43'],
        ];

        $condition = new IncludesCondition('values', '42');

        self::assertTrue($condition->isSatisfiedBy($context));

        // This should be false because of strict comparison (string vs integer)
        $context = [
            'values' => [42, 43],
        ];

        self::assertFalse($condition->isSatisfiedBy($context));
    }
}
