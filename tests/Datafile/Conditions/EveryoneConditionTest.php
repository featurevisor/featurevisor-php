<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Conditions;

use Featurevisor\Datafile\Conditions\EveryoneCondition;
use PHPUnit\Framework\TestCase;

class EveryoneConditionTest extends TestCase
{
    public function testEveryoneConditionAlwaysReturnsTrue(): void
    {
        $condition = new EveryoneCondition();

        // Empty context
        self::assertTrue($condition->isSatisfiedBy([]));

        // Context with some attributes
        self::assertTrue($condition->isSatisfiedBy([
            'country' => 'us',
            'age' => 25,
        ]));

        // Context with nested attributes
        self::assertTrue($condition->isSatisfiedBy([
            'user' => [
                'profile' => [
                    'country' => 'us',
                    'age' => 25,
                ],
            ],
        ]));
    }
}
