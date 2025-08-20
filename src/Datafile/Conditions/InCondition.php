<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


use Featurevisor\Datafile\AttributeException;

final class InCondition implements ConditionInterface
{
    use ContextLookup;

    private const ALLOWED_TYPES = ['string', 'integer'];
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
        $valueFromContext = $this->getValueFromContext($context, $this->attribute);
        $this->validateType(self::ALLOWED_TYPES, $this->attribute, $valueFromContext);

        return in_array($valueFromContext, $this->value, true);
    }
}
