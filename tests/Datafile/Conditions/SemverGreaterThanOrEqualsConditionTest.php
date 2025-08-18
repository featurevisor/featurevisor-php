<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\SemverGreaterThanOrEqualsCondition;
use PHPUnit\Framework\TestCase;

class SemverGreaterThanOrEqualsConditionTest extends TestCase
{
    public function testSemverGreaterThanOrEqualsConditionWithGreaterVersion(): void
    {
        $context = [
            'version' => '1.2.4',
        ];

        $condition = new SemverGreaterThanOrEqualsCondition('version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanOrEqualsConditionWithEqualVersion(): void
    {
        $context = [
            'version' => '1.2.3',
        ];

        $condition = new SemverGreaterThanOrEqualsCondition('version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanOrEqualsConditionWithLesserVersion(): void
    {
        $context = [
            'version' => '1.2.2',
        ];

        $condition = new SemverGreaterThanOrEqualsCondition('version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanOrEqualsConditionWithGreaterMajorVersion(): void
    {
        $context = [
            'version' => '2.0.0',
        ];

        $condition = new SemverGreaterThanOrEqualsCondition('version', '1.0.0');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanOrEqualsConditionWithGreaterMinorVersion(): void
    {
        $context = [
            'version' => '1.3.0',
        ];

        $condition = new SemverGreaterThanOrEqualsCondition('version', '1.2.0');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanOrEqualsConditionWithGreaterPatchVersion(): void
    {
        $context = [
            'version' => '1.2.4',
        ];

        $condition = new SemverGreaterThanOrEqualsCondition('version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanOrEqualsConditionWithNestedAttributeGreater(): void
    {
        $context = [
            'app' => [
                'info' => [
                    'version' => '1.2.4',
                ],
            ],
        ];

        $condition = new SemverGreaterThanOrEqualsCondition('app.info.version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanOrEqualsConditionWithNestedAttributeEqual(): void
    {
        $context = [
            'app' => [
                'info' => [
                    'version' => '1.2.3',
                ],
            ],
        ];

        $condition = new SemverGreaterThanOrEqualsCondition('app.info.version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanOrEqualsConditionWithNestedAttributeLesser(): void
    {
        $context = [
            'app' => [
                'info' => [
                    'version' => '1.2.2',
                ],
            ],
        ];

        $condition = new SemverGreaterThanOrEqualsCondition('app.info.version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanOrEqualsConditionWithMissingAttribute(): void
    {
        $context = [
            'other_attribute' => '1.2.4',
        ];

        $condition = new SemverGreaterThanOrEqualsCondition('version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanOrEqualsConditionWithInvalidVersionFormat(): void
    {
        $context = [
            'version' => 'not-a-version',
        ];

        $condition = new SemverGreaterThanOrEqualsCondition('version', '1.2.3');

        $this->expectException(\InvalidArgumentException::class);
        $condition->isSatisfiedBy($context);
    }
}
