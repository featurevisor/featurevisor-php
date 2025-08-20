<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\InCondition;
use Featurevisor\Datafile\Conditions\NotCondition;
use PHPUnit\Framework\TestCase;

class InConditionTest extends TestCase
{
    public function testInConditionWithSimpleAttributeInArray(): void
    {
        $context = [
            'country' => 'us',
        ];

        $condition = new InCondition('country', ['us', 'ca', 'uk']);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testInConditionWithSimpleAttributeNotInArray(): void
    {
        $context = [
            'country' => 'fr',
        ];

        $condition = new InCondition('country', ['us', 'ca', 'uk']);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testInConditionWithMissingAttribute(): void
    {
        $context = [
            'other_attribute' => 'us',
        ];

        $condition = new InCondition('country', ['us', 'ca', 'uk']);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testInConditionWithNestedAttributeInArray(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'country' => 'us',
                ],
            ],
        ];

        $condition = new InCondition('user.profile.country', ['us', 'ca', 'uk']);

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testInConditionWithNestedAttributeNotInArray(): void
    {
        $context = [
            'user' => [
                'profile' => [
                    'country' => 'fr',
                ],
            ],
        ];

        $condition = new InCondition('user.profile.country', ['us', 'ca', 'uk']);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testInConditionWithEmptyArray(): void
    {
        $context = [
            'country' => 'us',
        ];

        $condition = new InCondition('country', []);

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testInConditionWithStrictComparison(): void
    {
        $context = [
            'value' => '42',
        ];

        $condition = new InCondition('value', ['42', '43']);

        self::assertTrue($condition->isSatisfiedBy($context));

        // This should be false because of strict comparison (string vs integer)
        $context = [
            'value' => 42,
        ];

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testInConditionWithInvalidValueType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('InCondition value must be array of strings');

        new InCondition('country', ['us', 42]);
    }

    public function testNotInConditionWithMissingAttribute(): void
    {
        $context = [
            'country' => 'us',
        ];

        $condition = new NotCondition(new InCondition('continent', ['europe']));

        self::assertFalse($condition->isSatisfiedBy($context));
    }
}
