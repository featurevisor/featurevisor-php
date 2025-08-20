<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


use Featurevisor\Datafile\AttributeException;

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
            try {
                if ($condition->isSatisfiedBy($context) === false) {
                    return false;
                }
            } catch (AttributeException $e) {
                return false;
            }
        }

        return true;
    }
}
