<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


use Featurevisor\Datafile\Semver;
use InvalidArgumentException;

final class SemverLessThanCondition implements ConditionInterface
{
    use ContextLookup, CompositeCondition;

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
        $comparator = new VersionComparator();

        return $comparator(
            new Semver($this->getValueFromContext($context, $this->attribute)),
            $this->value
        ) === -1;
    }
}
