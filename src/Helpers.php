<?php

namespace Featurevisor;

class Helpers
{
    public static function getValueByType($value, string $fieldType)
    {
        try {
            if ($value === null) {
                return null;
            }

            switch ($fieldType) {
                case 'string':
                    return is_string($value) ? $value : null;
                case 'integer':
                    if (is_int($value)) {
                        return $value;
                    }
                    if (is_float($value) && is_finite($value) && floor($value) === $value) {
                        return (int) $value;
                    }
                    return null;
                case 'double':
                    return (is_int($value) || is_float($value)) && is_finite((float) $value)
                        ? (float) $value
                        : null;
                case 'boolean':
                    return is_bool($value) ? $value : null;
                case 'array':
                    return is_array($value) ? $value : null;
                case 'object':
                    return is_array($value) || is_object($value) ? $value : null;
                // @NOTE: `json` is not handled here intentionally
                default:
                    return $value;
            }
        } catch (\Exception $e) {
            return null;
        }
    }
}
