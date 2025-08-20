<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\SemverLessThanOrEqualsCondition;
use PHPUnit\Framework\TestCase;

class SemverLessThanOrEqualsConditionTest extends TestCase
{
    public function testSemverLessThanOrEqualsConditionWithLesserVersion(): void
    {
        $context = [
            'version' => '1.2.2',
        ];

        $condition = new SemverLessThanOrEqualsCondition('version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanOrEqualsConditionWithEqualVersion(): void
    {
        $context = [
            'version' => '1.2.3',
        ];

        $condition = new SemverLessThanOrEqualsCondition('version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanOrEqualsConditionWithGreaterVersion(): void
    {
        $context = [
            'version' => '1.2.4',
        ];

        $condition = new SemverLessThanOrEqualsCondition('version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanOrEqualsConditionWithLesserMajorVersion(): void
    {
        $context = [
            'version' => '1.0.0',
        ];

        $condition = new SemverLessThanOrEqualsCondition('version', '2.0.0');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanOrEqualsConditionWithLesserMinorVersion(): void
    {
        $context = [
            'version' => '1.1.0',
        ];

        $condition = new SemverLessThanOrEqualsCondition('version', '1.2.0');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanOrEqualsConditionWithLesserPatchVersion(): void
    {
        $context = [
            'version' => '1.2.2',
        ];

        $condition = new SemverLessThanOrEqualsCondition('version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanOrEqualsConditionWithNestedAttributeLesser(): void
    {
        $context = [
            'app' => [
                'info' => [
                    'version' => '1.2.2',
                ],
            ],
        ];

        $condition = new SemverLessThanOrEqualsCondition('app.info.version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanOrEqualsConditionWithNestedAttributeEqual(): void
    {
        $context = [
            'app' => [
                'info' => [
                    'version' => '1.2.3',
                ],
            ],
        ];

        $condition = new SemverLessThanOrEqualsCondition('app.info.version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanOrEqualsConditionWithNestedAttributeGreater(): void
    {
        $context = [
            'app' => [
                'info' => [
                    'version' => '1.2.4',
                ],
            ],
        ];

        $condition = new SemverLessThanOrEqualsCondition('app.info.version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanOrEqualsConditionWithMissingAttribute(): void
    {
        $context = [
            'other_attribute' => '1.2.2',
        ];

        $condition = new SemverLessThanOrEqualsCondition('version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverLessThanOrEqualsConditionWithInvalidVersionFormat(): void
    {
        $context = [
            'version' => 'not-a-version',
        ];

        $condition = new SemverLessThanOrEqualsCondition('version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }
}
