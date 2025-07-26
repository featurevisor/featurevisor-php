<?php

namespace Featurevisor;

class EvaluateByBucketing
{
    public static function evaluate(array $options, array $feature, ?array $variableSchema = null, ?array $force = null): array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $context = $options['context'];
        $variableKey = $options['variableKey'] ?? null;
        $logger = $options['logger'];
        $datafileReader = $options['datafileReader'];
        $hooksManager = $options['hooksManager'];

        $hooks = $hooksManager->getAll();

        // bucketKey
        $bucketKey = Bucketer::getBucketKey([
            'featureKey' => $featureKey,
            'bucketBy' => $feature['bucketBy'],
            'context' => $context,
            'logger' => $logger
        ]);

        foreach ($hooks as $hook) {
            if (isset($hook['bucketKey'])) {
                $bucketKey = $hook['bucketKey']([
                    'featureKey' => $featureKey,
                    'context' => $context,
                    'bucketBy' => $feature['bucketBy'],
                    'bucketKey' => $bucketKey
                ]);
            }
        }

        // bucketValue
        $bucketValue = Bucketer::getBucketedNumber($bucketKey);

        foreach ($hooks as $hook) {
            if (isset($hook['bucketValue'])) {
                $bucketValue = $hook['bucketValue']([
                    'featureKey' => $featureKey,
                    'bucketKey' => $bucketKey,
                    'context' => $context,
                    'bucketValue' => $bucketValue
                ]);
            }
        }

        $matchedTraffic = null;
        $matchedAllocation = null;

        if ($type !== 'flag') {
            $matchedTraffic = $datafileReader->getMatchedTraffic($feature['traffic'], $context);

            if ($matchedTraffic) {
                $matchedAllocation = $datafileReader->getMatchedAllocation($matchedTraffic, $bucketValue);
            }
        } else {
            $matchedTraffic = $datafileReader->getMatchedTraffic($feature['traffic'], $context);
        }

        $result = [
            'bucketKey' => $bucketKey,
            'bucketValue' => $bucketValue,
            'matchedTraffic' => $matchedTraffic,
            'matchedAllocation' => $matchedAllocation
        ];

        if ($matchedTraffic) {
            // percentage: 0
            if ($matchedTraffic['percentage'] === 0) {
                $result['evaluation'] = [
                    'type' => $type,
                    'featureKey' => $featureKey,
                    'reason' => Evaluation::RULE,
                    'bucketKey' => $bucketKey,
                    'bucketValue' => $bucketValue,
                    'ruleKey' => $matchedTraffic['key'],
                    'traffic' => $matchedTraffic,
                    'enabled' => false
                ];

                $logger->debug('matched rule with 0 percentage', $result['evaluation']);

                return $result;
            }

            // flag
            if ($type === 'flag') {
                // flag: check if mutually exclusive
                if (isset($feature['ranges']) && count($feature['ranges']) > 0) {
                    $matchedRange = null;
                    foreach ($feature['ranges'] as $range) {
                        if ($bucketValue >= $range[0] && $bucketValue < $range[1]) {
                            $matchedRange = $range;
                            break;
                        }
                    }

                    // matched
                    if ($matchedRange) {
                        $result['evaluation'] = [
                            'type' => $type,
                            'featureKey' => $featureKey,
                            'reason' => Evaluation::ALLOCATED,
                            'bucketKey' => $bucketKey,
                            'bucketValue' => $bucketValue,
                            'ruleKey' => $matchedTraffic['key'],
                            'traffic' => $matchedTraffic,
                            'enabled' => isset($matchedTraffic['enabled']) ? $matchedTraffic['enabled'] : true
                        ];

                        $logger->debug('matched', $result['evaluation']);

                        return $result;
                    }

                    // no match
                    $result['evaluation'] = [
                        'type' => $type,
                        'featureKey' => $featureKey,
                        'reason' => Evaluation::OUT_OF_RANGE,
                        'bucketKey' => $bucketKey,
                        'bucketValue' => $bucketValue,
                        'enabled' => false
                    ];

                    $logger->debug('not matched', $result['evaluation']);

                    return $result;
                }

                // flag: override from rule
                if (isset($matchedTraffic['enabled'])) {
                    $result['evaluation'] = [
                        'type' => $type,
                        'featureKey' => $featureKey,
                        'reason' => Evaluation::RULE,
                        'bucketKey' => $bucketKey,
                        'bucketValue' => $bucketValue,
                        'ruleKey' => $matchedTraffic['key'],
                        'traffic' => $matchedTraffic,
                        'enabled' => $matchedTraffic['enabled']
                    ];

                    $logger->debug('override from rule', $result['evaluation']);

                    return $result;
                }

                // treated as enabled because of matched traffic
                if ($bucketValue <= $matchedTraffic['percentage']) {
                    $result['evaluation'] = [
                        'type' => $type,
                        'featureKey' => $featureKey,
                        'reason' => Evaluation::RULE,
                        'bucketKey' => $bucketKey,
                        'bucketValue' => $bucketValue,
                        'ruleKey' => $matchedTraffic['key'],
                        'traffic' => $matchedTraffic,
                        'enabled' => true
                    ];

                    $logger->debug('matched traffic', $result['evaluation']);

                    return $result;
                }
            }

            // variation
            if ($type === 'variation' && isset($feature['variations'])) {
                // override from rule
                if (isset($matchedTraffic['variation'])) {
                    $variation = null;
                    foreach ($feature['variations'] as $v) {
                        if ($v['value'] === $matchedTraffic['variation']) {
                            $variation = $v;
                            break;
                        }
                    }

                    if ($variation) {
                        $result['evaluation'] = [
                            'type' => $type,
                            'featureKey' => $featureKey,
                            'reason' => Evaluation::RULE,
                            'bucketKey' => $bucketKey,
                            'bucketValue' => $bucketValue,
                            'ruleKey' => $matchedTraffic['key'],
                            'traffic' => $matchedTraffic,
                            'variation' => $variation
                        ];

                        $logger->debug('override from rule', $result['evaluation']);

                        return $result;
                    }
                }

                // regular allocation
                if ($matchedAllocation && isset($matchedAllocation['variation'])) {
                    $variation = null;
                    foreach ($feature['variations'] as $v) {
                        if ($v['value'] === $matchedAllocation['variation']) {
                            $variation = $v;
                            break;
                        }
                    }

                    if ($variation) {
                        $result['evaluation'] = [
                            'type' => $type,
                            'featureKey' => $featureKey,
                            'reason' => Evaluation::ALLOCATED,
                            'bucketKey' => $bucketKey,
                            'bucketValue' => $bucketValue,
                            'ruleKey' => $matchedTraffic['key'],
                            'traffic' => $matchedTraffic,
                            'variation' => $variation
                        ];

                        $logger->debug('allocated variation', $result['evaluation']);

                        return $result;
                    }
                }
            }
        }

        // variable
        if ($type === 'variable' && $variableKey) {
            // override from rule
            if ($matchedTraffic && isset($matchedTraffic['variables'][$variableKey])) {
                $result['evaluation'] = [
                    'type' => $type,
                    'featureKey' => $featureKey,
                    'reason' => Evaluation::RULE,
                    'bucketKey' => $bucketKey,
                    'bucketValue' => $bucketValue,
                    'ruleKey' => $matchedTraffic['key'],
                    'traffic' => $matchedTraffic,
                    'variableKey' => $variableKey,
                    'variableSchema' => $variableSchema,
                    'variableValue' => $matchedTraffic['variables'][$variableKey]
                ];

                $logger->debug('override from rule', $result['evaluation']);

                return $result;
            }

            // check variations
            $variationValue = null;

            if ($force && isset($force['variation'])) {
                $variationValue = $force['variation'];
            } elseif ($matchedTraffic && isset($matchedTraffic['variation'])) {
                $variationValue = $matchedTraffic['variation'];
            } elseif ($matchedAllocation && isset($matchedAllocation['variation'])) {
                $variationValue = $matchedAllocation['variation'];
            }

            if ($variationValue && is_array($feature['variations'])) {
                $variation = null;
                foreach ($feature['variations'] as $v) {
                    if ($v['value'] === $variationValue) {
                        $variation = $v;
                        break;
                    }
                }

                if ($variation && isset($variation['variableOverrides'][$variableKey])) {
                    $overrides = $variation['variableOverrides'][$variableKey];

                    $override = null;
                    foreach ($overrides as $o) {
                        if (isset($o['conditions'])) {
                            $conditions = is_string($o['conditions']) && $o['conditions'] !== '*'
                                ? json_decode($o['conditions'], true)
                                : $o['conditions'];

                            if ($datafileReader->allConditionsAreMatched($conditions, $context)) {
                                $override = $o;
                                break;
                            }
                        }

                        if (isset($o['segments'])) {
                            $segments = $datafileReader->parseSegmentsIfStringified($o['segments']);
                            if ($datafileReader->allSegmentsAreMatched($segments, $context)) {
                                $override = $o;
                                break;
                            }
                        }
                    }

                    if ($override) {
                        $result['evaluation'] = [
                            'type' => $type,
                            'featureKey' => $featureKey,
                            'reason' => Evaluation::VARIABLE_OVERRIDE,
                            'bucketKey' => $bucketKey,
                            'bucketValue' => $bucketValue,
                            'ruleKey' => $matchedTraffic['key'] ?? null,
                            'traffic' => $matchedTraffic,
                            'variableKey' => $variableKey,
                            'variableSchema' => $variableSchema,
                            'variableValue' => $override['value']
                        ];

                        $logger->debug('variable override', $result['evaluation']);

                        return $result;
                    }
                }

                if ($variation && isset($variation['variables'][$variableKey])) {
                    $result['evaluation'] = [
                        'type' => $type,
                        'featureKey' => $featureKey,
                        'reason' => Evaluation::ALLOCATED,
                        'bucketKey' => $bucketKey,
                        'bucketValue' => $bucketValue,
                        'ruleKey' => $matchedTraffic['key'] ?? null,
                        'traffic' => $matchedTraffic,
                        'variableKey' => $variableKey,
                        'variableSchema' => $variableSchema,
                        'variableValue' => $variation['variables'][$variableKey]
                    ];

                    $logger->debug('allocated variable', $result['evaluation']);

                    return $result;
                }
            }
        }

        // Nothing matched
        if ($type === 'variation') {
            $result['evaluation'] = [
                'type' => $type,
                'featureKey' => $featureKey,
                'reason' => Evaluation::NO_MATCH,
                'bucketKey' => $bucketKey,
                'bucketValue' => $bucketValue
            ];

            $logger->debug('no matched variation', $result['evaluation']);

            return $result;
        }

        if ($type === 'variable') {
            if ($variableSchema) {
                $result['evaluation'] = [
                    'type' => $type,
                    'featureKey' => $featureKey,
                    'reason' => Evaluation::VARIABLE_DEFAULT,
                    'bucketKey' => $bucketKey,
                    'bucketValue' => $bucketValue,
                    'variableKey' => $variableKey,
                    'variableSchema' => $variableSchema,
                    'variableValue' => $variableSchema['defaultValue']
                ];

                $logger->debug('using default value', $result['evaluation']);

                return $result;
            }

            $result['evaluation'] = [
                'type' => $type,
                'featureKey' => $featureKey,
                'reason' => Evaluation::VARIABLE_NOT_FOUND,
                'variableKey' => $variableKey,
                'bucketKey' => $bucketKey,
                'bucketValue' => $bucketValue
            ];

            $logger->debug('variable not found', $result['evaluation']);

            return $result;
        }

        return $result;
    }
}
