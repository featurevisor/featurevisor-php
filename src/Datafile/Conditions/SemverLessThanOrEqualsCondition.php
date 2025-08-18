<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


use InvalidArgumentException;

final class SemverLessThanOrEqualsCondition implements ConditionInterface
{
    use ContextLookup;

    private string $attribute;
    private string $value;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(string $attribute, string $value)
    {
        $this->attribute = $attribute;
        $this->value = $value;
    }

    public function isSatisfiedBy(array $context): bool
    {
        return (new SemverLessThanCondition($this->attribute, $this->value))
            ->or(new SemverEqualsCondition($this->attribute, $this->value))
            ->isSatisfiedBy($context);
    }
}
