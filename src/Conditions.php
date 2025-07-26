<?php

namespace Featurevisor;

class Conditions
{
    // Helper to check if an array is sequential (not associative)
    private static function isSequentialArray($array): bool
    {
        if (!is_array($array)) return false;
        return array_keys($array) === range(0, count($array) - 1);
    }

    private static function pathExists(array $array, string $path): bool
    {
        if (strpos($path, '.') === false) {
            return array_key_exists($path, $array);
        }

        $keys = explode('.', $path);
        $current = $array;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return false;
            }
            $current = $current[$key];
        }

        return true;
    }

    public static function getValueFromContext(array $obj, string $path)
    {
        if (strpos($path, '.') === false) {
            return $obj[$path] ?? null;
        }

        $keys = explode('.', $path);
        $current = $obj;

        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    public static function conditionIsMatched($condition, array $context, callable $getRegex): bool
    {
        // DEBUG: print condition and context
        // var_dump(['condition' => $condition, 'context' => $context]);
        // Match all via '*'
        if ($condition === '*') {
            return true;
        }

        // If not array, cannot match
        if (!is_array($condition)) {
            return false;
        }

        // Logical operators
        if (isset($condition['and'])) {
            $andConditions = self::isSequentialArray($condition['and']) ? $condition['and'] : [$condition['and']];
            foreach ($andConditions as $subCondition) {
                if (!self::conditionIsMatched($subCondition, $context, $getRegex)) {
                    return false;
                }
            }
            return true;
        }
        if (isset($condition['or'])) {
            $orConditions = self::isSequentialArray($condition['or']) ? $condition['or'] : [$condition['or']];
            foreach ($orConditions as $subCondition) {
                if (self::conditionIsMatched($subCondition, $context, $getRegex)) {
                    return true;
                }
            }
            return false;
        }
        if (isset($condition['not'])) {
            $notConditions = self::isSequentialArray($condition['not']) ? $condition['not'] : [$condition['not']];
            if (count($notConditions) === 0) {
                return true;
            }
            foreach ($notConditions as $subCondition) {
                if (self::conditionIsMatched($subCondition, $context, $getRegex)) {
                    return false;
                }
            }
            return true;
        }

        $attribute = $condition['attribute'] ?? '';
        $operator = $condition['operator'] ?? '';
        $value = $condition['value'] ?? null;
        $regexFlags = $condition['regexFlags'] ?? '';

        $contextValueFromPath = self::getValueFromContext($context, $attribute);

        if ($operator === 'equals') {
            return $contextValueFromPath === $value;
        } elseif ($operator === 'notEquals') {
            return $contextValueFromPath !== $value;
        } elseif ($operator === 'before' || $operator === 'after') {
            // date comparisons
            $valueInContext = $contextValueFromPath;

            $dateInContext = is_string($valueInContext) ? new \DateTime($valueInContext) : $valueInContext;
            $dateInCondition = is_string($value) ? new \DateTime($value) : $value;

            return $operator === 'before'
                ? $dateInContext < $dateInCondition
                : $dateInContext > $dateInCondition;
        } elseif (
            is_array($value) &&
            (is_string($contextValueFromPath) || is_numeric($contextValueFromPath) || $contextValueFromPath === null)
        ) {
            // in / notIn (where condition value is an array)
            $valueInContext = $contextValueFromPath;

            if ($operator === 'in') {
                return in_array($valueInContext, $value);
            } elseif (
                $operator === 'notIn' &&
                self::pathExists($context, $attribute)
            ) {
                return !in_array($valueInContext, $value);
            }

        } elseif (is_string($contextValueFromPath) && is_string($value)) {
            // string
            $valueInContext = $contextValueFromPath;

            if ($operator === 'contains') {
                return strpos($valueInContext, $value) !== false;
            } elseif ($operator === 'notContains') {
                return strpos($valueInContext, $value) === false;
            } elseif ($operator === 'startsWith') {
                return strpos($valueInContext, $value) === 0;
            } elseif ($operator === 'endsWith') {
                return substr($valueInContext, -strlen($value)) === $value;
            } elseif ($operator === 'semverEquals') {
                return CompareVersions::compare($valueInContext, $value) === 0;
            } elseif ($operator === 'semverNotEquals') {
                return CompareVersions::compare($valueInContext, $value) !== 0;
            } elseif ($operator === 'semverGreaterThan') {
                return CompareVersions::compare($valueInContext, $value) === 1;
            } elseif ($operator === 'semverGreaterThanOrEquals') {
                return CompareVersions::compare($valueInContext, $value) >= 0;
            } elseif ($operator === 'semverLessThan') {
                return CompareVersions::compare($valueInContext, $value) === -1;
            } elseif ($operator === 'semverLessThanOrEquals') {
                return CompareVersions::compare($valueInContext, $value) <= 0;
            } elseif ($operator === 'matches') {
                $regex = $getRegex($value, $regexFlags);
                return preg_match($regex, $valueInContext);
            } elseif ($operator === 'notMatches') {
                $regex = $getRegex($value, $regexFlags);
                return !preg_match($regex, $valueInContext);
            }
        } elseif (is_numeric($contextValueFromPath) && is_numeric($value)) {
            // numeric
            $valueInContext = $contextValueFromPath;

            if ($operator === 'greaterThan') {
                return $valueInContext > $value;
            } elseif ($operator === 'greaterThanOrEquals') {
                return $valueInContext >= $value;
            } elseif ($operator === 'lessThan') {
                return $valueInContext < $value;
            } elseif ($operator === 'lessThanOrEquals') {
                return $valueInContext <= $value;
            }
        } elseif ($operator === 'exists') {
            return self::pathExists($context, $attribute);
        } elseif ($operator === 'notExists') {
            return !self::pathExists($context, $attribute);
        } elseif (is_array($contextValueFromPath) && is_string($value)) {
            // includes / notIncludes (where context value is an array)
            $valueInContext = $contextValueFromPath;

            if ($operator === 'includes') {
                return in_array($value, $valueInContext);
            } elseif ($operator === 'notIncludes') {
                return !in_array($value, $valueInContext);
            }
        }

        return false;
    }
}
