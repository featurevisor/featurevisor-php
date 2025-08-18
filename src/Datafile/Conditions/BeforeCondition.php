<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


use DateTimeImmutable;
use DateTimeInterface;

final class BeforeCondition implements ConditionInterface
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
        try {
            $valueFromContext = $this->getValueFromContext($context, $this->attribute);
            if ($valueFromContext === null) {
                return false;
            }
            if ($valueFromContext instanceof DateTimeInterface) {
                $contextDate = DateTimeImmutable::createFromFormat(
                    DateTimeInterface::RFC3339,
                    $valueFromContext->format(DateTimeInterface::RFC3339)
                );
            } else {
                $contextDate = new DateTimeImmutable($valueFromContext);
            }

            return $contextDate < $this->value;
        } catch (\Exception $e) {
            return false;
        }
    }
}
