<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


use Featurevisor\Datafile\Semver;
use InvalidArgumentException;

final class SemverEqualsCondition implements ConditionInterface
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
        if ($valueFromContext === null) {
            return false;
        }
        $comparator = new VersionComparator();

        try {
            return $comparator(
                    new Semver($valueFromContext),
                    new Semver($this->value)
                ) === 0;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }
}
