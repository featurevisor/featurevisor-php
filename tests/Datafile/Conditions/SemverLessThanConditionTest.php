<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\AttributeException;
use Featurevisor\Datafile\Conditions\SemverLessThanCondition;
use PHPUnit\Framework\TestCase;

class SemverLessThanConditionTest extends TestCase
{
    public function testSemverLessThanConditionWithLesserVersion(): void
    {
        $context = [
            'version' => '1.2.2',
        ];

        $condition = new SemverLessThanCondition('version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanConditionWithEqualVersion(): void
    {
        $context = [
            'version' => '1.2.3',
        ];

        $condition = new SemverLessThanCondition('version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanConditionWithGreaterVersion(): void
    {
        $context = [
            'version' => '1.2.4',
        ];

        $condition = new SemverLessThanCondition('version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanConditionWithLesserMajorVersion(): void
    {
        $context = [
            'version' => '1.0.0',
        ];

        $condition = new SemverLessThanCondition('version', '2.0.0');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanConditionWithLesserMinorVersion(): void
    {
        $context = [
            'version' => '1.1.0',
        ];

        $condition = new SemverLessThanCondition('version', '1.2.0');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanConditionWithLesserPatchVersion(): void
    {
        $context = [
            'version' => '1.2.2',
        ];

        $condition = new SemverLessThanCondition('version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanConditionWithNestedAttributeLesser(): void
    {
        $context = [
            'app' => [
                'info' => [
                    'version' => '1.2.2',
                ],
            ],
        ];

        $condition = new SemverLessThanCondition('app.info.version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanConditionWithNestedAttributeGreater(): void
    {
        $context = [
            'app' => [
                'info' => [
                    'version' => '1.2.4',
                ],
            ],
        ];

        $condition = new SemverLessThanCondition('app.info.version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanConditionWithMissingAttribute(): void
    {
        $this->expectException(AttributeException::class);

        $context = [
            'other_attribute' => '1.2.2',
        ];
        $condition = new SemverLessThanCondition('version', '1.2.3');

        $condition->isSatisfiedBy($context);
    }

    public function testSemverLessThanConditionWithInvalidVersionFormat(): void
    {
        $context = [
            'version' => 'not-a-version',
        ];

        $condition = new SemverLessThanCondition('version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }
}
