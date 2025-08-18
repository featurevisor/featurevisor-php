<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


use DateTimeImmutable;

final class AfterCondition implements ConditionInterface
{
    use ContextLookup;

    private string $attribute;
    private DateTimeImmutable $value;

    public function __construct(string $attribute, DateTimeImmutable $value)
    {
        $this->attribute = $attribute;
        $this->value = $value;
    }

    public function isSatisfiedBy(array $context): bool
    {
        $contextDate = new DateTimeImmutable($this->getValueFromContext($context, $this->attribute));

        return $contextDate > $this->value;
    }
}
