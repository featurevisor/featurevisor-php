<?php

namespace Featurevisor;

class CompareVersions
{
    private static $semver = '/^[v^~<>=]*?(\d+)(?:\.([x*]|\d+)(?:\.([x*]|\d+)(?:\.([x*]|\d+))?(?:-([\da-z\-]+(?:\.[\da-z\-]+)*))?(?:\+[\da-z\-]+(?:\.[\da-z\-]+)*)?)?)?$/i';

    public static function compare(string $v1, string $v2): int
    {
        // validate input and split into segments
        $n1 = self::validateAndParse($v1);
        $n2 = self::validateAndParse($v2);

        // pop off the patch
        $p1 = array_pop($n1);
        $p2 = array_pop($n2);

        // validate numbers
        $r = self::compareSegments($n1, $n2);
        if ($r !== 0) return $r;

        // validate pre-release
        if ($p1 && $p2) {
            return self::compareSegments(explode('.', $p1), explode('.', $p2));
        } elseif ($p1 || $p2) {
            return $p1 ? -1 : 1;
        }

        return 0;
    }

    private static function validateAndParse(string $version): array
    {
        if (!is_string($version)) {
            throw new \TypeError('Invalid argument expected string');
        }

        if (!preg_match(self::$semver, $version, $match)) {
            throw new \Exception("Invalid argument not valid semver ('$version' received)");
        }

        array_shift($match);
        return $match;
    }

    private static function isWildcard(string $s): bool
    {
        return $s === '*' || $s === 'x' || $s === 'X';
    }

    private static function forceType($a, $b): array
    {
        return gettype($a) !== gettype($b) ? [strval($a), strval($b)] : [$a, $b];
    }

    private static function tryParse(string $v)
    {
        $n = intval($v);
        return is_nan($n) ? $v : $n;
    }

    private static function compareStrings(string $a, string $b): int
    {
        if (self::isWildcard($a) || self::isWildcard($b)) return 0;

        list($ap, $bp) = self::forceType(self::tryParse($a), self::tryParse($b));

        if ($ap > $bp) return 1;
        if ($ap < $bp) return -1;
        return 0;
    }

    private static function compareSegments($a, $b): int
    {
        $maxLength = max(count($a), count($b));

        for ($i = 0; $i < $maxLength; $i++) {
            $r = self::compareStrings($a[$i] ?? '0', $b[$i] ?? '0');
            if ($r !== 0) return $r;
        }

        return 0;
    }
}
