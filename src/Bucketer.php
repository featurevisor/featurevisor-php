<?php

namespace Featurevisor;

class Bucketer
{
    private const HASH_SEED = 1;
    private const MAX_HASH_VALUE = 4294967296; // 2^32
    public const MAX_BUCKETED_NUMBER = 100000; // 100% * 1000 to include three decimal places in the same integer value
    private const DEFAULT_BUCKET_KEY_SEPARATOR = '.';

    public static function getBucketedNumber(string $bucketKey): int
    {
        $hashValue = MurmurHash::v3($bucketKey, self::HASH_SEED);
        $ratio = $hashValue / self::MAX_HASH_VALUE;

        return (int) floor($ratio * self::MAX_BUCKETED_NUMBER);
    }

    public static function getBucketKey(array $options): string
    {
        $featureKey = $options['featureKey'];
        $bucketBy = $options['bucketBy'];
        $context = $options['context'];
        $reportDiagnostic = $options['reportDiagnostic'] ?? null;

        $type = null;
        $attributeKeys = null;

        if (is_string($bucketBy)) {
            $type = 'plain';
            $attributeKeys = [$bucketBy];
        } elseif (is_array($bucketBy) && !isset($bucketBy['or'])) {
            $type = 'and';
            $attributeKeys = $bucketBy;
        } elseif (is_array($bucketBy) && isset($bucketBy['or']) && is_array($bucketBy['or'])) {
            $type = 'or';
            $attributeKeys = $bucketBy['or'];
        } else {
            if (is_callable($reportDiagnostic)) {
                $reportDiagnostic([
                    'level' => 'error',
                    'code' => 'invalid_bucket_by',
                    'message' => 'Invalid bucketBy',
                    'details' => ['featureKey' => $featureKey, 'bucketBy' => $bucketBy],
                ]);
            }
            throw new \Exception('invalid bucketBy');
        }

        $bucketKey = [];

        foreach ($attributeKeys as $attributeKey) {
            if (!Conditions::pathExists($context, $attributeKey)) {
                continue;
            }

            $attributeValue = Conditions::getValueFromContext($context, $attributeKey);

            if ($type === 'plain' || $type === 'and') {
                $bucketKey[] = self::toJavaScriptString($attributeValue);
            } else {
                // or
                if (empty($bucketKey)) {
                    $bucketKey[] = self::toJavaScriptString($attributeValue);
                }
            }
        }

        $bucketKey[] = $featureKey;

        return implode(self::DEFAULT_BUCKET_KEY_SEPARATOR, $bucketKey);
    }

    /** @param mixed $value */
    private static function toJavaScriptString($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_float($value)) {
            if (is_nan($value)) {
                return 'NaN';
            }
            if (is_infinite($value)) {
                return $value > 0 ? 'Infinity' : '-Infinity';
            }
            if ($value == 0.0) {
                return '0';
            }

            $absolute = abs($value);
            $shortest = strtolower(json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
            if ($absolute >= 1e-6 && $absolute < 1e21) {
                return str_contains($shortest, 'e')
                    ? self::expandScientificNotation($shortest)
                    : rtrim(rtrim($shortest, '0'), '.');
            }

            $parts = explode('e', $shortest);
            if (count($parts) === 2) {
                $coefficient = rtrim(rtrim($parts[0], '0'), '.');
                $exponent = (int) $parts[1];
                return $coefficient . 'e' . ($exponent >= 0 ? '+' : '') . $exponent;
            }
        }
        if (is_array($value)) {
            if ($value === [] || array_keys($value) === range(0, count($value) - 1)) {
                return implode(',', array_map([self::class, 'toJavaScriptString'], $value));
            }

            return '[object Object]';
        }
        if (is_object($value)) {
            return '[object Object]';
        }

        return (string) $value;
    }

    private static function expandScientificNotation(string $value): string
    {
        [$coefficient, $rawExponent] = explode('e', $value);
        $negative = str_starts_with($coefficient, '-');
        $digits = str_replace(['-', '.'], '', $coefficient);
        $decimalIndex = (strpos(ltrim($coefficient, '-'), '.') ?: strlen(ltrim($coefficient, '-')))
            + (int) $rawExponent;

        if ($decimalIndex <= 0) {
            $expanded = '0.'.str_repeat('0', -$decimalIndex).$digits;
        } elseif ($decimalIndex >= strlen($digits)) {
            $expanded = $digits.str_repeat('0', $decimalIndex - strlen($digits));
        } else {
            $expanded = substr($digits, 0, $decimalIndex).'.'.substr($digits, $decimalIndex);
        }

        if (str_contains($expanded, '.')) {
            $expanded = rtrim(rtrim($expanded, '0'), '.');
        }

        return ($negative ? '-' : '').$expanded;
    }
}
