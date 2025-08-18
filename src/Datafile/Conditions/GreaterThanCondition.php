<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


final class GreaterThanCondition implements ConditionInterface
{
    use ContextLookup, CompositeCondition;

    private string $attribute;

    /** @var float|int */
    private $value;

    public function __construct(string $attribute, $value)
    {
        if (is_int($value) === false && is_float($value) === false) {
            throw new \InvalidArgumentException('GreaterThanCondition value must be float or integer');
        }

        $this->attribute = $attribute;
        $this->value = $value;
    }

    public function isSatisfiedBy(array $context): bool
    {
        $valueFromContext = $this->getValueFromContext($context, $this->attribute);
        if ($valueFromContext === null) {
            return false;
        }

        return $valueFromContext > $this->value;
    }
}
