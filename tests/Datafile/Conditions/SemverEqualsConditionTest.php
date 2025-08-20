<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\AttributeException;
use Featurevisor\Datafile\Conditions\SemverEqualsCondition;
use PHPUnit\Framework\TestCase;

class SemverEqualsConditionTest extends TestCase
{
    public function testSemverEqualsConditionWithEqualVersions(): void
    {
        $context = [
            'version' => '1.2.3',
        ];

        $condition = new SemverEqualsCondition('version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverEqualsConditionWithDifferentVersions(): void
    {
        $context = [
            'version' => '1.2.4',
        ];

        $condition = new SemverEqualsCondition('version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverEqualsConditionWithDifferentMajorVersions(): void
    {
        $context = [
            'version' => '2.0.0',
        ];

        $condition = new SemverEqualsCondition('version', '1.0.0');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverEqualsConditionWithDifferentMinorVersions(): void
    {
        $context = [
            'version' => '1.3.0',
        ];

        $condition = new SemverEqualsCondition('version', '1.2.0');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverEqualsConditionWithDifferentPatchVersions(): void
    {
        $context = [
            'version' => '1.2.3',
        ];

        $condition = new SemverEqualsCondition('version', '1.2.4');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverEqualsConditionWithNestedAttributeEqual(): void
    {
        $context = [
            'app' => [
                'info' => [
                    'version' => '1.2.3',
                ],
            ],
        ];

        $condition = new SemverEqualsCondition('app.info.version', '1.2.3');

        self::assertTrue($condition->isSatisfiedBy($context));
    }

    public function testSemverEqualsConditionWithNestedAttributeNotEqual(): void
    {
        $context = [
            'app' => [
                'info' => [
                    'version' => '1.2.4',
                ],
            ],
        ];

        $condition = new SemverEqualsCondition('app.info.version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }

    public function testSemverEqualsConditionWithMissingAttribute(): void
    {
        $this->expectException(AttributeException::class);

        $context = [
            'other_attribute' => '1.2.3',
        ];
        $condition = new SemverEqualsCondition('version', '1.2.3');

        $condition->isSatisfiedBy($context);
    }

    public function testSemverEqualsConditionWithInvalidVersionFormat(): void
    {
        $context = [
            'version' => 'not-a-version',
        ];
        $condition = new SemverEqualsCondition('version', '1.2.3');

        self::assertFalse($condition->isSatisfiedBy($context));
    }
}
