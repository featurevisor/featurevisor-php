<?php

namespace Featurevisor\Datafile\Conditions;

interface ConditionInterface
{
    public function isSatisfiedBy(array $context): bool;
}
