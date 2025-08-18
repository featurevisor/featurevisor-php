<?php

declare(strict_types=1);

namespace Featurevisor\Datafile\Conditions;


use Featurevisor\Datafile\Semver;

final class VersionComparator
{
    public function __invoke(Semver $v1, Semver $v2): int
    {
        $n1 = $v1->getSegments();
        $n2 = $v2->getSegments();

        // pop off the patch
        $p1 = array_pop($n1);
        $p2 = array_pop($n2);

        // validate numbers
        $r = $this->compareSegments($n1, $n2);
        if ($r !== 0) return $r;

        // validate pre-release
        if ($p1 && $p2) {
            return $this->compareSegments(explode('.', $p1), explode('.', $p2));
        }

        if ($p1 || $p2) {
            return $p1 ? -1 : 1;
        }

        return 0;
    }

    private function isWildcard(string $s): bool
    {
        return $s === '*' || $s === 'x' || $s === 'X';
    }

    private function forceType($a, $b): array
    {
        return gettype($a) !== gettype($b) ? [strval($a), strval($b)] : [$a, $b];
    }

    private function tryParse(string $v)
    {
        $n = (int) $v;
        return is_nan($n) ? $v : $n;
    }

    private function compareStrings(string $a, string $b): int
    {
        if ($this->isWildcard($a) || $this->isWildcard($b)) return 0;

        list($ap, $bp) = $this->forceType($this->tryParse($a), $this->tryParse($b));

        if ($ap > $bp) return 1;
        if ($ap < $bp) return -1;
        return 0;
    }

    private function compareSegments($a, $b): int
    {
        $maxLength = max(count($a), count($b));

        for ($i = 0; $i < $maxLength; $i++) {
            $r = $this->compareStrings($a[$i] ?? '0', $b[$i] ?? '0');
            if ($r !== 0) return $r;
        }

        return 0;
    }
}
