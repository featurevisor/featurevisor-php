<?php

namespace Featurevisor;

use Featurevisor\Datafile\Conditions;
use Featurevisor\Datafile\Segment;
use Psr\Log\LoggerInterface;

class DatafileReader
{
    private string $schemaVersion;
    private string $revision;
    private array $segments;
    private array $features;
    private LoggerInterface $logger;
    private array $regexCache;

    public function __construct(array $options)
    {
        $datafile = $options['datafile'];
        $this->logger = $options['logger'];

        $this->schemaVersion = $datafile['schemaVersion'];
        $this->revision = $datafile['revision'];
        $this->segments = $datafile['segments'];
        $this->features = $datafile['features'];
        $this->regexCache = [];
    }

    public function getRevision(): string
    {
        return $this->revision;
    }

    public function getSchemaVersion(): string
    {
        return $this->schemaVersion;
    }

    public function findSegment(string $segmentKey): ?array
    {
        $segment = $this->segments[$segmentKey] ?? null;

        if (!$segment) {
            return null;
        }

        $segment['conditions'] = $this->parseConditionsIfStringified($segment['conditions']);

        return $segment;
    }

    public function getFeatureKeys(): array
    {
        return array_keys($this->features);
    }

    public function getFeature(string $featureKey): ?array
    {
        return $this->features[$featureKey] ?? null;
    }

    public function getVariableKeys(string $featureKey): array
    {
        $feature = $this->getFeature($featureKey);

        if (!$feature) {
            return [];
        }

        return array_keys($feature['variablesSchema'] ?? []);
    }

    public function hasVariations(string $featureKey): bool
    {
        $feature = $this->getFeature($featureKey);

        if (!$feature) {
            return false;
        }

        return isset($feature['variations']) && is_array($feature['variations']) && count($feature['variations']) > 0;
    }

    public function getRegex(string $regexString, string $regexFlags = ''): string
    {
        $key = $regexString . $regexFlags;

        if (!isset($this->regexCache[$key])) {
            $this->regexCache[$key] = '/' . $regexString . '/' . $regexFlags;
        }

        return $this->regexCache[$key];
    }

    public function allConditionsAreMatched($conditions, array $context): bool
    {
        return Conditions::createFromMixed($conditions)->isSatisfiedBy($context);
    }

    public function allSegmentsAreMatched($groupSegments, array $context): bool
    {
        if ($groupSegments === '*') {
            return true;
        }

        if (is_string($groupSegments)) {
            $segment = $this->findSegment($groupSegments);
            return $segment !== null ? Segment::createFromArray($segment)->allConditionsAreMatched($context) : false;
        }

        // Logical operators
        if (is_array($groupSegments)) {
            if (isset($groupSegments['and']) && is_array($groupSegments['and'])) {
                foreach ($groupSegments['and'] as $subSegment) {
                    if (!$this->allSegmentsAreMatched($subSegment, $context)) {
                        return false;
                    }
                }
                return true;
            }
            if (isset($groupSegments['or']) && is_array($groupSegments['or'])) {
                foreach ($groupSegments['or'] as $subSegment) {
                    if ($this->allSegmentsAreMatched($subSegment, $context)) {
                        return true;
                    }
                }
                return false;
            }
            if (isset($groupSegments['not']) && is_array($groupSegments['not'])) {
                foreach ($groupSegments['not'] as $subSegment) {
                    if ($this->allSegmentsAreMatched($subSegment, $context)) {
                        return false;
                    }
                }
                return true;
            }
            // If it's a plain array, treat as AND (all must match)
            if (array_keys($groupSegments) === range(0, count($groupSegments) - 1)) {
                foreach ($groupSegments as $subSegment) {
                    if (!$this->allSegmentsAreMatched($subSegment, $context)) {
                        return false;
                    }
                }
                return true;
            }
        }

        return false;
    }

    public function getMatchedTraffic(array $traffic, array $context): ?array
    {
        foreach ($traffic as $trafficItem) {
            $segments = $this->parseSegmentsIfStringified($trafficItem['segments']);
            if ($this->allSegmentsAreMatched($segments, $context)) {
                return $trafficItem;
            }
        }
        return null;
    }

    public function getMatchedAllocation(array $traffic, int $bucketValue): ?array
    {
        if (!isset($traffic['allocation'])) {
            return null;
        }
        foreach ($traffic['allocation'] as $allocation) {
            $range = $allocation['range'];
            if ($bucketValue >= $range[0] && $bucketValue <= $range[1]) {
                return $allocation;
            }
        }
        return null;
    }

    public function getMatchedForce($featureKey, array $context): array
    {
        $feature = is_string($featureKey) ? $this->getFeature($featureKey) : $featureKey;

        if (!$feature || !isset($feature['force'])) {
            return [];
        }

        foreach ($feature['force'] as $forceIndex => $force) {
            if (isset($force['conditions']) && $this->allConditionsAreMatched($this->parseConditionsIfStringified($force['conditions']), $context)) {
                return [
                    'force' => $force,
                    'forceIndex' => $forceIndex
                ];
            }
            if (isset($force['segments']) && $this->allSegmentsAreMatched($this->parseSegmentsIfStringified($force['segments']), $context)) {
                return [
                    'force' => $force,
                    'forceIndex' => $forceIndex
                ];
            }
        }
        return [];
    }

    public function parseConditionsIfStringified($conditions)
    {
        if (is_string($conditions)) {
            if ($conditions === '*') {
                return $conditions;
            }
            $trimmed = ltrim($conditions);
            if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                $parsed = json_decode($conditions, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $parsed;
                }
            }
            return $conditions;
        }
        if (is_array($conditions) && isset($conditions[0])) {
            return array_map(function($condition) {
                if (is_string($condition)) {
                    $trimmed = ltrim($condition);
                    if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                        $parsed = json_decode($condition, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            return $parsed;
                        }
                    }
                }
                return $condition;
            }, $conditions);
        }
        return $conditions;
    }

    public function parseSegmentsIfStringified($segments)
    {
        if (is_string($segments)) {
            $trimmed = ltrim($segments);
            if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                $parsed = json_decode($segments, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $parsed;
                }
            }
            return $segments;
        }
        if (is_array($segments) && isset($segments[0])) {
            return array_map(function($segment) {
                if (is_string($segment)) {
                    $trimmed = ltrim($segment);
                    if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                        $parsed = json_decode($segment, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            return $parsed;
                        }
                    }
                }
                return $segment;
            }, $segments);
        }
        return $segments;
    }
}
