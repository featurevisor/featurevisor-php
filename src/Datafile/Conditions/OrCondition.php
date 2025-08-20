<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


use Featurevisor\Datafile\AttributeException;

final class OrCondition implements ConditionInterface
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
                if ($condition->isSatisfiedBy($context) === true) {
                    return true;
                }
            } catch (AttributeException $e) {
                continue;
            }
        }

        return false;
    }
}
