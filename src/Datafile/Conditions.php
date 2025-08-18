<?php

declare(strict_types=1);

namespace Featurevisor\Datafile;


use Featurevisor\Datafile\Conditions\ConditionFactory;
use Featurevisor\Datafile\Conditions\ConditionInterface;
use Featurevisor\Datafile\Conditions\EveryoneCondition;

final class Conditions implements ConditionInterface
{
    private ConditionInterface $expression;

    public static function createFromMixed($conditions): self
    {
        if ($conditions === '*') {
            return new self(new EveryoneCondition());
        }

        if (is_array($conditions) === false) {
            throw new \InvalidArgumentException('Conditions must be array or string');
        }

        $factory = new ConditionFactory();

        return new self($factory->create($conditions));
    }

    public function __construct(ConditionInterface $expression)
    {
        $this->expression = $expression;
    }

    public function isSatisfiedBy(array $context): bool
    {
        return $this->expression->isSatisfiedBy($context);
    }
}
