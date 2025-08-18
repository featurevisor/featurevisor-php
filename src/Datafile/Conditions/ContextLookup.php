<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


trait ContextLookup
{
    /**
     * @return mixed|null
     */
    public function getValueFromContext(array $context, string $attribute)
    {
        if (strpos($attribute, '.') === false) {
            return $context[$attribute] ?? null;
        }

        $keys = explode('.', $attribute);
        $current = $context;

        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }
}
