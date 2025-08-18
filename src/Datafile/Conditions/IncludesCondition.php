<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


final class IncludesCondition implements ConditionInterface
{
    use ContextLookup;

    private string $attribute;
    private string $value;

    public function __construct(string $attribute, string $value)
    {
        $this->attribute = $attribute;
        $this->value = $value;
    }

    public function isSatisfiedBy(array $context): bool
    {
        $valueFromContext = $this->getValueFromContext($context, $this->attribute);
        if (is_array($valueFromContext) === false) {
            return false;
        }

        return in_array($this->value, $valueFromContext,true);
    }
}
