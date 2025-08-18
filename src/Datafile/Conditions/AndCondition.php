<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


final class AndCondition implements ConditionInterface
{
    /** @var array<ConditionInterface> */
    private array $conditions;

    public function __construct(ConditionInterface ...$conditions)
    {
        $this->conditions = $conditions;
    }

    public function isSatisfiedBy(array $context): bool
    {
        foreach ($this->conditions as $condition) {
            if ($condition->isSatisfiedBy($context) === false) {
                return false;
            }
        }

        return true;
    }
}
