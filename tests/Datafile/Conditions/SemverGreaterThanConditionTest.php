<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\AttributeException;
use Featurevisor\Datafile\Conditions\SemverGreaterThanCondition;
use PHPUnit\Framework\TestCase;

class SemverGreaterThanConditionTest extends TestCase
{
    public function testSemverGreaterThanConditionWithGreaterVersion(): void
    {
        $context = [
            'version' => '1.2.4',
        ];

        $condition = new SemverGreaterThanCondition('version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanConditionWithEqualVersion(): void
    {
        $context = [
            'version' => '1.2.3',
        ];

        $condition = new SemverGreaterThanCondition('version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanConditionWithLesserVersion(): void
    {
        $context = [
            'version' => '1.2.2',
        ];

        $condition = new SemverGreaterThanCondition('version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanConditionWithGreaterMajorVersion(): void
    {
        $context = [
            'version' => '2.0.0',
        ];

        $condition = new SemverGreaterThanCondition('version', '1.0.0');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanConditionWithGreaterMinorVersion(): void
    {
        $context = [
            'version' => '1.3.0',
        ];

        $condition = new SemverGreaterThanCondition('version', '1.2.0');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanConditionWithGreaterPatchVersion(): void
    {
        $context = [
            'version' => '1.2.4',
        ];

        $condition = new SemverGreaterThanCondition('version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanConditionWithNestedAttributeGreater(): void
    {
        $context = [
            'app' => [
                'info' => [
                    'version' => '1.2.4',
                ],
            ],
        ];

        $condition = new SemverGreaterThanCondition('app.info.version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanConditionWithNestedAttributeLesser(): void
    {
        $context = [
            'app' => [
                'info' => [
                    'version' => '1.2.2',
                ],
            ],
        ];

        $condition = new SemverGreaterThanCondition('app.info.version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverGreaterThanConditionWithMissingAttribute(): void
    {
        $this->expectException(AttributeException::class);

        $context = [
            'other_attribute' => '1.2.4',
        ];
        $condition = new SemverGreaterThanCondition('version', '1.2.3');

        $condition->isSatisfiedBy($context);
    }

    public function testSemverGreaterThanConditionWithInvalidVersionFormat(): void
    {
        $context = [
            'version' => 'not-a-version',
        ];

        $condition = new SemverGreaterThanCondition('version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }
}
