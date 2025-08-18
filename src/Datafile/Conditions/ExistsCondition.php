<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


final class ExistsCondition implements ConditionInterface
{
    private string $attribute;

    public function __construct(string $attribute)
    {
        $this->attribute = $attribute;
    }

    public function isSatisfiedBy(array $context): bool
    {
        if (strpos($this->attribute, '.') === false) {
            return array_key_exists($this->attribute, $context);
        }

        $keys = explode('.', $this->attribute);
        $current = $context;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return false;
            }
            $current = $current[$key];
        }

        return true;
    }
}
