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
                    return intval($value);
                case 'double':
                    return floatval($value);
                case 'boolean':
                    return $value === true;
                case 'array':
                    return is_array($value) ? $value : null;
                case 'object':
                    return is_object($value) ? $value : null;
                // @NOTE: `json` is not handled here intentionally
                default:
                    return $value;
            }
        } catch (\Exception $e) {
            return null;
        }
    }
}
