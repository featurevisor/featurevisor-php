<?php

namespace Featurevisor\Datafile\Conditions;

use Featurevisor\Datafile\AttributeException;

interface ConditionInterface
{
    /**
     * @throws AttributeException
     */
    public function isSatisfiedBy(array $context): bool;
}
