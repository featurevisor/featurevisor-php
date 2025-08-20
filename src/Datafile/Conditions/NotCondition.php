<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


use Featurevisor\Datafile\AttributeException;

final class NotCondition implements ConditionInterface
{
    private ConditionInterface $specification;

    public function __construct(ConditionInterface $specification)
    {
        $this->specification = $specification;
    }

    public function isSatisfiedBy(array $context): bool
    {
        return $this->specification->isSatisfiedBy($context) === false;
    }
}
