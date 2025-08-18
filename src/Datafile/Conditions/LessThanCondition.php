<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


final class LessThanCondition implements ConditionInterface
{
    use ContextLookup, CompositeCondition;

    private string $attribute;

    /** @var float|int */
    private $value;

    public function __construct(string $attribute, $value)
    {
        if (is_int($value) === false && is_float($value) === false) {
            throw new \InvalidArgumentException('LessThanCondition value must be float or integer');
        }

        $this->attribute = $attribute;
        $this->value = $value;
    }

    public function isSatisfiedBy(array $context): bool
    {
        return $this->getValueFromContext($context, $this->attribute) < $this->value;
    }
}
