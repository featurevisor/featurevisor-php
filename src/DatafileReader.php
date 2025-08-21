<?php

namespace Featurevisor;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class DatafileReader
{
    private const EMPTY_CONTENT = [
        'schemaVersion' => '2',
        'revision' => 'unknown',
        'segments' => [],
        'features' => []
    ];

    private string $schemaVersion;
    private string $revision;
    private array $segments;
    private array $features;
    private LoggerInterface $logger;
    private array $regexCache;

    public static function createEmpty(LoggerInterface $logger): self
    {
        return self::createFromOptions([
            'datafile' => self::EMPTY_CONTENT,
            'logger' => $logger,
        ]);
    }

    public static function createFromMixed($datafile, LoggerInterface $logger): self
    {
        return is_string($datafile)
            ? self::createFromJson($datafile, $logger)
            : self::createFromOptions([
                'datafile' => $datafile,
                'logger' => $logger,
            ]);
    }

    /**
     * @throws \JsonException
     */
    public static function createFromJson(string $json, LoggerInterface $logger): self
    {
        $decodedDatafile = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return self::createFromOptions([
            'datafile' => $decodedDatafile,
            'logger' => $logger
        ]);
    }

    public static function createFromOptions(array $data): self
    {
        if (array_key_exists('datafile', $data) === false ) {
            throw new \InvalidArgumentException('Missing datafile key in data array');
        }

        return new self(
            $data['datafile'],
            $data['logger'] ?? null
        );
    }

    public function __construct(array $datafileContent, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();

        $this->schemaVersion = $datafileContent['schemaVersion'];
        $this->revision = $datafileContent['revision'];
        $this->segments = $datafileContent['segments'];
        $this->features = $datafileContent['features'];
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

    public function getSegment(string $segmentKey): ?array
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
        if (is_string($conditions)) {
            if ($conditions === '*') {
                return true;
            }
            // Try to parse as JSON
            $parsed = json_decode($conditions, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $conditions = $parsed;
            } else {
                return false;
            }
        }

        $getRegex = function(string $regexString, string $regexFlags) {
            return $this->getRegex($regexString, $regexFlags);
        };

        if (is_array($conditions)) {
            // If it's an empty array, always match (true)
            if (count($conditions) === 0) {
                return true;
            }
            // Logical operators
            if (isset($conditions['and']) && is_array($conditions['and'])) {
                foreach ($conditions['and'] as $subCondition) {
                    if (!$this->allConditionsAreMatched($subCondition, $context)) {
                        return false;
                    }
                }
                return true;
            }
            if (isset($conditions['or']) && is_array($conditions['or'])) {
                foreach ($conditions['or'] as $subCondition) {
                    if ($this->allConditionsAreMatched($subCondition, $context)) {
                        return true;
                    }
                }
                return false;
            }
            if (isset($conditions['not']) && is_array($conditions['not'])) {
                foreach ($conditions['not'] as $subCondition) {
                    if ($this->allConditionsAreMatched($subCondition, $context)) {
                        return false;
                    }
                }
                return true;
            }
            // If it's a plain array, treat as AND (all must match)
            if (array_keys($conditions) === range(0, count($conditions) - 1)) {
                foreach ($conditions as $subCondition) {
                    if (!$this->allConditionsAreMatched($subCondition, $context)) {
                        return false;
                    }
                }
                return true;
            }
            // If it's a single condition (associative array)
            if (isset($conditions['attribute'])) {
                try {
                    return Conditions::conditionIsMatched($conditions, $context, $getRegex);
                } catch (\Exception $e) {
                    $this->logger->warning($e->getMessage(), [
                        'exception' => $e,
                        'condition' => $conditions,
                        'context' => $context,
                    ]);
                    return false;
                }
            }
        }
        return false;
    }

    public function segmentIsMatched(array $segment, array $context): bool
    {
        return $this->allConditionsAreMatched($segment['conditions'], $context);
    }

    public function allSegmentsAreMatched($groupSegments, array $context): bool
    {
        if ($groupSegments === '*') {
            return true;
        }

        if (is_string($groupSegments)) {
            $segment = $this->getSegment($groupSegments);
            return $segment ? $this->segmentIsMatched($segment, $context) : false;
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
