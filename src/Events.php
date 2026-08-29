<?php

namespace Featurevisor;

class Events
{
    public static function getParamsForStickyFeaturesSetEvent(array $previous = [], array $current = [], bool $replace = false): array
    {
        return ['features' => array_values(array_unique(array_merge(array_keys($previous), array_keys($current)))), 'replaced' => $replace];
    }

    public static function getParamsForStickyVariablesSetEvent(array $previous = [], array $current = [], bool $replace = false): array
    {
        return ['variables' => array_values(array_unique(array_merge(array_keys($previous), array_keys($current)))), 'replaced' => $replace];
    }

    public static function getParamsForDatafileSetEvent(array $previous, array $current, bool $replace = false): array
    {
        $previousFeatures = array_keys($previous['features'] ?? []);
        $currentFeatures = array_keys($current['features'] ?? []);
        $features = [];
        foreach (array_unique(array_merge($previousFeatures, $currentFeatures)) as $key) {
            if (self::entityChanged($previous['features'][$key] ?? null, $current['features'][$key] ?? null)) $features[] = $key;
        }
        $previousVariables = array_keys($previous['variables'] ?? []);
        $currentVariables = array_keys($current['variables'] ?? []);
        $variables = [];
        foreach (array_unique(array_merge($previousVariables, $currentVariables)) as $key) {
            if (self::entityChanged($previous['variables'][$key] ?? null, $current['variables'][$key] ?? null)) $variables[] = $key;
        }
        $changedSegments = [];
        foreach (array_unique(array_merge(array_keys($previous['segments'] ?? []), array_keys($current['segments'] ?? []))) as $key) {
            if (($previous['segments'][$key] ?? null) !== ($current['segments'][$key] ?? null)) $changedSegments[] = $key;
        }

        $allFeatureKeys = array_unique(array_merge($previousFeatures, $currentFeatures));
        do {
            $before = count($features);
            foreach ($allFeatureKeys as $key) {
                if (in_array($key, $features, true)) continue;
                foreach (array_filter([$previous['features'][$key] ?? null, $current['features'][$key] ?? null]) as $feature) {
                    [$segments, $required] = self::featureDependencies($feature);
                    if (array_intersect($segments, $changedSegments) || array_intersect($required, $features)) {
                        $features[] = $key;
                        break;
                    }
                }
            }
        } while ($before !== count($features));

        foreach (array_unique(array_merge($previousVariables, $currentVariables)) as $key) {
            if (in_array($key, $variables, true)) continue;
            foreach (array_filter([$previous['variables'][$key] ?? null, $current['variables'][$key] ?? null]) as $variable) {
                [$segments, $required] = self::globalVariableDependencies($variable);
                if (array_intersect($segments, $changedSegments) || array_intersect($required, $features)) {
                    $variables[] = $key;
                    break;
                }
            }
        }

        return [
            'revision' => $current['revision'],
            'previousRevision' => $previous['revision'],
            'revisionChanged' => $previous['revision'] !== $current['revision'],
            'features' => array_values(array_unique($features)),
            'variables' => array_values(array_unique($variables)),
            'replaced' => $replace,
        ];
    }

    private static function entityChanged(?array $previous, ?array $current): bool
    {
        return $previous === null || $current === null || empty($previous['hash']) || empty($current['hash']) || $previous['hash'] !== $current['hash'];
    }

    /** @param mixed $values */
    private static function requiredFeatureKeys($values): array
    {
        if ($values === null) return [];
        $isList = is_array($values) && ($values === [] || array_keys($values) === range(0, count($values) - 1));
        $items = $isList ? $values : [$values];
        return array_values(array_filter(array_map(fn($item) => is_string($item) ? $item : ($item['feature'] ?? ($item['key'] ?? null)), $items)));
    }

    /** @param mixed $value */
    private static function segmentKeys($value): array
    {
        if ($value === null || $value === '*') return [];
        if (is_string($value)) {
            if (str_starts_with($value, '{') || str_starts_with($value, '[')) {
                try { return self::segmentKeys(json_decode($value, true, 512, JSON_THROW_ON_ERROR)); } catch (\Throwable $e) { return []; }
            }
            return [$value];
        }
        if (is_array($value)) {
            $result = [];
            foreach (['and', 'or', 'not'] as $operator) if (isset($value[$operator])) $result = array_merge($result, self::segmentKeys($value[$operator]));
            if ($result === []) foreach ($value as $item) $result = array_merge($result, self::segmentKeys($item));
            return array_values(array_unique($result));
        }
        return [];
    }

    private static function overrideDependencies(array $groups): array
    {
        $segments = $required = [];
        foreach ($groups as $overrides) foreach ($overrides as $override) {
            $segments = array_merge($segments, self::segmentKeys($override['segments'] ?? null));
            $required = array_merge($required, self::requiredFeatureKeys($override['requiredFeatures'] ?? null));
        }
        return [array_unique($segments), array_unique($required)];
    }

    private static function featureDependencies(array $feature): array
    {
        $segments = [];
        $requirements = array_key_exists('requiredFeatures', $feature) ? $feature['requiredFeatures'] : ($feature['required'] ?? null);
        $required = self::requiredFeatureKeys($requirements);
        foreach ($feature['traffic'] ?? [] as $traffic) {
            $segments = array_merge($segments, self::segmentKeys($traffic['segments'] ?? null));
            [$nestedSegments, $nestedRequired] = self::overrideDependencies($traffic['variableOverrides'] ?? []);
            $segments = array_merge($segments, $nestedSegments);
            $required = array_merge($required, $nestedRequired);
        }
        foreach ($feature['force'] ?? [] as $force) $segments = array_merge($segments, self::segmentKeys($force['segments'] ?? null));
        foreach ($feature['variations'] ?? [] as $variation) {
            [$nestedSegments, $nestedRequired] = self::overrideDependencies($variation['variableOverrides'] ?? []);
            $segments = array_merge($segments, $nestedSegments);
            $required = array_merge($required, $nestedRequired);
        }
        return [array_unique($segments), array_unique($required)];
    }

    private static function globalVariableDependencies(array $variable): array
    {
        $segments = [];
        $required = self::requiredFeatureKeys($variable['requiredFeatures'] ?? null);
        foreach ($variable['overrides'] ?? [] as $override) {
            $segments = array_merge($segments, self::segmentKeys($override['segments'] ?? null));
            $required = array_merge($required, self::requiredFeatureKeys($override['requiredFeatures'] ?? null));
        }
        return [array_unique($segments), array_unique($required)];
    }
}
