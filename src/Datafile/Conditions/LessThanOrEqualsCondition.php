<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


final class LessThanOrEqualsCondition implements ConditionInterface
{
    private string $attribute;

    /**
     * @var float|int
     */
    private $value;

    public function __construct(string $attribute, $value)
    {
        if (is_int($value) === false && is_float($value) === false) {
            throw new \InvalidArgumentException('LessThanOrEqualsCondition value must be float or integer');
        }

        $this->attribute = $attribute;
        $this->value = $value;
    }

    public function isSatisfiedBy(array $context): bool
    {
        return (new LessThanCondition($this->attribute, $this->value))
            ->or(new EqualsCondition($this->attribute, $this->value))
            ->isSatisfiedBy($context);
    }
}
