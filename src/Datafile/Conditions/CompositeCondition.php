<?php

namespace Featurevisor\Datafile\Conditions;

use LogicException;

/**
 * @mixin ConditionInterface
 */
trait CompositeCondition
{
    public function and(ConditionInterface $specification): AndCondition
    {
        if ($this instanceof ConditionInterface) {
            return new AndCondition($this, $specification);
        }

        throw new LogicException('Composite specification must be an instance of SpecificationInterface');
    }

    public function or(ConditionInterface $specification): OrCondition
    {
        if ($this instanceof ConditionInterface) {
            return new OrCondition($this, $specification);
        }

        throw new LogicException('Composite specification must be an instance of SpecificationInterface');
    }

    public function not(): NotCondition
    {
        if ($this instanceof ConditionInterface) {
            return new NotCondition($this);
        }

        throw new LogicException('Composite specification must be an instance of SpecificationInterface');
    }
}
