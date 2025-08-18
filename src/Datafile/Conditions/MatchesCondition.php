<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


final class MatchesCondition implements ConditionInterface
{
    use ContextLookup;

    private string $attribute;
    private string $regex;

    public function __construct(string $attribute, string $regex)
    {
        $this->attribute = $attribute;
        $this->regex = $regex;
    }

    public function isSatisfiedBy(array $context): bool
    {
        return preg_match($this->regex, $this->getValueFromContext($context, $this->attribute)) === 1;
    }
}
