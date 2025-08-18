<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


final class EqualsCondition implements ConditionInterface
{
    use ContextLookup;

    private string $attribute;

    /** @var mixed */
    private $value;

    public function __construct(string $attribute, $value)
    {
        $this->attribute = $attribute;
        $this->value = $value;
    }

    public function isSatisfiedBy(array $context): bool
    {
        return $this->getValueFromContext($context, $this->attribute) === $this->value;
    }
}
