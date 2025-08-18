<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


final class ContainsCondition implements ConditionInterface
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
        return str_contains((string) $this->getValueFromContext($context, $this->attribute), $this->value);
    }
}
