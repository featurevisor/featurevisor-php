<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


final class EveryoneCondition implements ConditionInterface
{
    public function isSatisfiedBy(array $context): bool
    {
        return true;
    }
}
