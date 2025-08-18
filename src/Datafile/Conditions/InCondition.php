<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


final class InCondition implements ConditionInterface
{
    use ContextLookup;

    private string $attribute;
    /** @var array<string> */
    private array $value;

    /**
     * @param array<string> $value
     */
    public function __construct(string $attribute, array $value)
    {
        foreach ($value as $item) {
            if (is_string($item) === false) {
                throw new \InvalidArgumentException('InCondition value must be array of strings');
            }
        }

        $this->attribute = $attribute;
        $this->value = $value;
    }

    public function isSatisfiedBy(array $context): bool
    {
        return in_array($this->getValueFromContext($context, $this->attribute), $this->value, true);
    }
}
