<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


use Featurevisor\Datafile\Semver;
use InvalidArgumentException;

final class SemverEqualsCondition implements ConditionInterface
{
    use ContextLookup;

    private string $attribute;
    private Semver $value;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(string $attribute, string $value)
    {
        $this->attribute = $attribute;
        $this->value = new Semver($value);
    }

    public function isSatisfiedBy(array $context): bool
    {
        $valueFromContext = $this->getValueFromContext($context, $this->attribute);
        if ($valueFromContext === null) {
            return false;
        }
        $comparator = new VersionComparator();

        return $comparator(
            new Semver($valueFromContext),
            $this->value
        ) === 0;
    }
}
