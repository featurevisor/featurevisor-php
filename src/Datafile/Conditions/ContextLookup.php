<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


use Featurevisor\Datafile\AttributeException;

trait ContextLookup
{
    /**
     * @return mixed|null
     * @throws AttributeException
     */
    public function getValueFromContext(array $context, string $attribute)
    {
        if (strpos($attribute, '.') === false) {
            if (array_key_exists($attribute, $context) === false) {
                throw AttributeException::createForNotFoundAttribute($attribute);
            }

            return $context[$attribute];
        }

        $keys = explode('.', $attribute);
        $current = $context;

        foreach ($keys as $key) {
            if (is_array($current) === false || array_key_exists($key, $current) === false) {
                throw AttributeException::createForNotFoundAttribute($attribute);
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * @param array<string> $allowedTypes
     * @param mixed $value
     * @throws AttributeException
     */
    private function validateType(array $allowedTypes, string $attribute, $value): void
    {
        if ($value !== null && in_array(gettype($value), $allowedTypes, true) === false) {
            throw AttributeException::createForInvalidType($attribute, $allowedTypes, gettype($value));
        }
    }
}
