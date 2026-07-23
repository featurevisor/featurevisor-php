<?php

namespace Featurevisor;

final class Conditions
{
    /** @param mixed $left @param mixed $right */
    private static function primitiveEquals($left, $right): bool
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return (float) $left === (float) $right;
        }

        if ($left === null || is_string($left) || is_bool($left)) {
            return $left === $right;
        }

        return false;
    }

    /** @param array<mixed> $value */
    private static function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    /**
     * @param array<string, mixed> $context
     * @return mixed
     */
    public static function getValueFromContext(array $context, string $path)
    {
        $current = $context;

        foreach (explode('.', $path) as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }

            $current = $current[$key];
        }

        return $current;
    }

    /** @param array<string, mixed> $context */
    public static function pathExists(array $context, string $path): bool
    {
        $current = $context;

        foreach (explode('.', $path) as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return false;
            }

            $current = $current[$key];
        }

        return true;
    }

    /**
     * @param mixed $condition
     * @param array<string, mixed> $context
     */
    public static function conditionIsMatched($condition, array $context, callable $getRegex): bool
    {
        if ($condition === '*') {
            return true;
        }

        if (!is_array($condition)) {
            return false;
        }

        $attribute = $condition['attribute'] ?? '';
        $operator = $condition['operator'] ?? '';
        $value = $condition['value'] ?? null;
        $contextValue = self::getValueFromContext($context, $attribute);

        if ($operator === 'equals') {
            return self::pathExists($context, $attribute) && self::primitiveEquals($contextValue, $value);
        }

        if ($operator === 'notEquals') {
            return !self::pathExists($context, $attribute) || !self::primitiveEquals($contextValue, $value);
        }

        if ($operator === 'before' || $operator === 'after') {
            $contextDate = self::portableDate($contextValue);
            $conditionDate = self::portableDate($value);
            if ($contextDate === null || $conditionDate === null) {
                return false;
            }

            return $operator === 'before' ? $contextDate < $conditionDate : $contextDate > $conditionDate;
        }

        if (is_array($value) && (is_string($contextValue) || is_int($contextValue) || is_float($contextValue) || $contextValue === null)) {
            if (!self::pathExists($context, $attribute)) {
                return false;
            }

            if ($operator === 'in') {
                return count(array_filter($value, fn ($candidate) => self::primitiveEquals($candidate, $contextValue))) > 0;
            }

            if ($operator === 'notIn') {
                return count(array_filter($value, fn ($candidate) => self::primitiveEquals($candidate, $contextValue))) === 0;
            }
        }

        if (is_string($contextValue) && is_string($value)) {
            if ($operator === 'contains') {
                return strpos($contextValue, $value) !== false;
            }
            if ($operator === 'notContains') {
                return strpos($contextValue, $value) === false;
            }
            if ($operator === 'startsWith') {
                return strpos($contextValue, $value) === 0;
            }
            if ($operator === 'endsWith') {
                return $value === '' || substr($contextValue, -strlen($value)) === $value;
            }
            if ($operator === 'semverEquals') {
                return CompareVersions::compare($contextValue, $value) === 0;
            }
            if ($operator === 'semverNotEquals') {
                return CompareVersions::compare($contextValue, $value) !== 0;
            }
            if ($operator === 'semverGreaterThan') {
                return CompareVersions::compare($contextValue, $value) === 1;
            }
            if ($operator === 'semverGreaterThanOrEquals') {
                return CompareVersions::compare($contextValue, $value) >= 0;
            }
            if ($operator === 'semverLessThan') {
                return CompareVersions::compare($contextValue, $value) === -1;
            }
            if ($operator === 'semverLessThanOrEquals') {
                return CompareVersions::compare($contextValue, $value) <= 0;
            }
            if ($operator === 'matches' || $operator === 'notMatches') {
                $result = @preg_match($getRegex($value, (string) ($condition['regexFlags'] ?? '')), $contextValue);
                if ($result === false) {
                    throw new \RuntimeException('Invalid regular expression');
                }

                return $operator === 'matches' ? $result === 1 : $result === 0;
            }
        }

        if ((is_int($contextValue) || is_float($contextValue)) && (is_int($value) || is_float($value))) {
            if ($operator === 'greaterThan') {
                return $contextValue > $value;
            }
            if ($operator === 'greaterThanOrEquals') {
                return $contextValue >= $value;
            }
            if ($operator === 'lessThan') {
                return $contextValue < $value;
            }
            if ($operator === 'lessThanOrEquals') {
                return $contextValue <= $value;
            }
        }

        if ($operator === 'exists') {
            return self::pathExists($context, $attribute);
        }
        if ($operator === 'notExists') {
            return !self::pathExists($context, $attribute);
        }

        if (is_array($contextValue) && (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null)) {
            if ($operator === 'includes') {
                return count(array_filter($contextValue, fn ($candidate) => self::primitiveEquals($candidate, $value))) > 0;
            }
            if ($operator === 'notIncludes') {
                return count(array_filter($contextValue, fn ($candidate) => self::primitiveEquals($candidate, $value))) === 0;
            }
        }

        return false;
    }

    private static function portableDate($value): ?\DateTimeInterface
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }
        if (!is_string($value) || !preg_match('/T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+\-]\d{2}:\d{2})$/', $value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $error) {
            return null;
        }
    }

    /**
     * @param mixed $conditions
     * @param array<string, mixed> $context
     */
    public static function allConditionsAreMatched($conditions, array $context, callable $getRegex, ?callable $reportDiagnostic = null): bool
    {
        if (is_string($conditions)) {
            return $conditions === '*';
        }

        if (!is_array($conditions)) {
            return false;
        }

        if (array_key_exists('attribute', $conditions)) {
            try {
                return self::conditionIsMatched($conditions, $context, $getRegex);
            } catch (\Throwable $error) {
                if ($reportDiagnostic) {
                    $reportDiagnostic([
                        'level' => 'warn',
                        'code' => 'condition_match_error',
                        'message' => $error->getMessage(),
                        'originalError' => $error,
                        'details' => ['condition' => $conditions, 'context' => $context],
                    ]);
                }

                return false;
            }
        }

        if (array_key_exists('and', $conditions) && is_array($conditions['and'])) {
            foreach ($conditions['and'] as $condition) {
                if (!self::allConditionsAreMatched($condition, $context, $getRegex, $reportDiagnostic)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('or', $conditions) && is_array($conditions['or'])) {
            foreach ($conditions['or'] as $condition) {
                if (self::allConditionsAreMatched($condition, $context, $getRegex, $reportDiagnostic)) {
                    return true;
                }
            }

            return false;
        }

        if (array_key_exists('not', $conditions) && is_array($conditions['not'])) {
            if ($conditions['not'] === []) {
                return false;
            }

            return !self::allConditionsAreMatched(['and' => $conditions['not']], $context, $getRegex, $reportDiagnostic);
        }

        if (self::isList($conditions)) {
            foreach ($conditions as $condition) {
                if (!self::allConditionsAreMatched($condition, $context, $getRegex, $reportDiagnostic)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /** @param mixed $conditions @return mixed */
    public static function parseConditionsIfStringified($conditions, ?callable $reportDiagnostic = null)
    {
        if (!is_string($conditions) || $conditions === '*') {
            return $conditions;
        }

        try {
            return json_decode($conditions, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            if ($reportDiagnostic) {
                $reportDiagnostic([
                    'level' => 'error',
                    'code' => 'conditions_parse_error',
                    'message' => 'Error parsing conditions',
                    'originalError' => $error,
                    'details' => ['conditions' => $conditions],
                ]);
            }

            return $conditions;
        }
    }

    /** @param mixed $segments @return mixed */
    public static function parseSegmentsIfStringified($segments)
    {
        if (is_string($segments) && ($segments[0] ?? '') !== '{' && ($segments[0] ?? '') !== '[') {
            return $segments;
        }

        return is_string($segments)
            ? json_decode($segments, true, 512, JSON_THROW_ON_ERROR)
            : $segments;
    }

    /**
     * @param mixed $groupSegments
     * @param array<string, mixed> $context
     */
    public static function allSegmentsAreMatched($groupSegments, array $context, callable $getSegment, callable $getRegex, ?callable $reportDiagnostic = null): bool
    {
        if ($groupSegments === '*') {
            return true;
        }

        if (is_string($groupSegments)) {
            $segment = $getSegment($groupSegments);

            return $segment
                ? self::allConditionsAreMatched(
                    self::parseConditionsIfStringified($segment['conditions'], $reportDiagnostic),
                    $context,
                    $getRegex,
                    $reportDiagnostic
                )
                : false;
        }

        if (!is_array($groupSegments)) {
            return false;
        }

        if (array_key_exists('and', $groupSegments) && is_array($groupSegments['and'])) {
            foreach ($groupSegments['and'] as $segment) {
                if (!self::allSegmentsAreMatched($segment, $context, $getSegment, $getRegex, $reportDiagnostic)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('or', $groupSegments) && is_array($groupSegments['or'])) {
            foreach ($groupSegments['or'] as $segment) {
                if (self::allSegmentsAreMatched($segment, $context, $getSegment, $getRegex, $reportDiagnostic)) {
                    return true;
                }
            }

            return false;
        }

        if (array_key_exists('not', $groupSegments) && is_array($groupSegments['not'])) {
            if ($groupSegments['not'] === []) {
                return false;
            }

            return !self::allSegmentsAreMatched(['and' => $groupSegments['not']], $context, $getSegment, $getRegex, $reportDiagnostic);
        }

        if (self::isList($groupSegments)) {
            foreach ($groupSegments as $segment) {
                if (!self::allSegmentsAreMatched($segment, $context, $getSegment, $getRegex, $reportDiagnostic)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }
}
