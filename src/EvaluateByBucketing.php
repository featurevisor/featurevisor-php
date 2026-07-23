<?php

namespace Featurevisor;

use Featurevisor\Internal\Diagnostics;

class EvaluateByBucketing
{
    public static function evaluate(array $options, array $feature, ?array $variableSchema = null, ?array $force = null): array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $context = $options['context'];
        $variableKey = $options['variableKey'] ?? null;
        $datafile = $options['datafile'];
        $modulesManager = $options['modulesManager'];

        // bucketKey
        $bucketKey = Bucketer::getBucketKey([
            'featureKey' => $featureKey,
            'bucketBy' => $feature['bucketBy'],
            'context' => $context,
            'reportDiagnostic' => $options['reportDiagnostic'] ?? null
        ]);

        $bucketKey = $modulesManager->runBucketKeyModules([
            'type' => $type,
            'featureKey' => $featureKey,
            'variableKey' => $variableKey,
            'context' => $context,
            'bucketBy' => $feature['bucketBy'],
            'bucketKey' => $bucketKey,
            'feature' => $feature,
        ]);

        // bucketValue
        $bucketValue = Bucketer::getBucketedNumber($bucketKey);

        $bucketValue = $modulesManager->runBucketValueModules([
            'type' => $type,
            'featureKey' => $featureKey,
            'variableKey' => $variableKey,
            'bucketKey' => $bucketKey,
            'context' => $context,
            'bucketValue' => $bucketValue,
            'feature' => $feature,
        ]);

        $matchedTraffic = null;
        $matchedAllocation = null;

        if ($type !== 'flag') {
            $matchedTraffic = $datafile['getMatchedTraffic']($feature['traffic'], $context);

            if ($matchedTraffic) {
                $matchedAllocation = $datafile['getMatchedAllocation']($matchedTraffic, $bucketValue);
            }
        } else {
            $matchedTraffic = $datafile['getMatchedTraffic']($feature['traffic'], $context);
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

                Diagnostics::reportEvaluation($options, $result['evaluation'], 'matched rule with 0 percentage');

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

                        Diagnostics::reportEvaluation($options, $result['evaluation'], 'matched');

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

                    Diagnostics::reportEvaluation($options, $result['evaluation'], 'not matched');

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

                    Diagnostics::reportEvaluation($options, $result['evaluation'], 'override from rule');

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

                    Diagnostics::reportEvaluation($options, $result['evaluation'], 'matched traffic');

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

                        Diagnostics::reportEvaluation($options, $result['evaluation'], 'override from rule');

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

                        Diagnostics::reportEvaluation($options, $result['evaluation'], 'allocated variation');

                        return $result;
                    }
                }
            }
        }

        // variable
        if ($type === 'variable' && $variableKey) {
            // override from rule
            if ($matchedTraffic) {
                // "variableOverrides"
                if (isset($matchedTraffic['variableOverrides'][$variableKey])) {
                    $overrides = $matchedTraffic['variableOverrides'][$variableKey];
                    $override = null;
                    $overrideIndex = -1;

                    foreach ($overrides as $index => $o) {
                        if (isset($o['conditions'])) {
                            $conditions = Conditions::parseConditionsIfStringified(
                                $o['conditions'],
                                $options['reportDiagnostic'] ?? null
                            );

                            if ($datafile['allConditionsAreMatched']($conditions, $context)) {
                                $override = $o;
                                $overrideIndex = $index;
                                break;
                            }
                        }

                        if (isset($o['segments'])) {
                            $segments = Conditions::parseSegmentsIfStringified($o['segments']);
                            if ($datafile['allSegmentsAreMatched']($segments, $context)) {
                                $override = $o;
                                $overrideIndex = $index;
                                break;
                            }
                        }
                    }

                    if ($override) {
                        $result['evaluation'] = [
                            'type' => $type,
                            'featureKey' => $featureKey,
                            'reason' => Evaluation::VARIABLE_OVERRIDE_RULE,
                            'bucketKey' => $bucketKey,
                            'bucketValue' => $bucketValue,
                            'ruleKey' => $matchedTraffic['key'] ?? null,
                            'traffic' => $matchedTraffic,
                            'variableKey' => $variableKey,
                            'variableSchema' => $variableSchema,
                            'variableValue' => $override['value'],
                            'variableOverrideIndex' => $overrideIndex,
                        ];

                        Diagnostics::reportEvaluation($options, $result['evaluation'], 'variable override from rule');

                        return $result;
                    }
                }

                // from "variables"
                if (isset($matchedTraffic['variables']) && array_key_exists($variableKey, $matchedTraffic['variables'])) {
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

                    Diagnostics::reportEvaluation($options, $result['evaluation'], 'override from rule');

                    return $result;
                }
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
                    $overrideIndex = -1;
                    foreach ($overrides as $index => $o) {
                        if (isset($o['conditions'])) {
                            $conditions = Conditions::parseConditionsIfStringified(
                                $o['conditions'],
                                $options['reportDiagnostic'] ?? null
                            );

                            if ($datafile['allConditionsAreMatched']($conditions, $context)) {
                                $override = $o;
                                $overrideIndex = $index;
                                break;
                            }
                        }

                        if (isset($o['segments'])) {
                            $segments = Conditions::parseSegmentsIfStringified($o['segments']);
                            if ($datafile['allSegmentsAreMatched']($segments, $context)) {
                                $override = $o;
                                $overrideIndex = $index;
                                break;
                            }
                        }
                    }

                    if ($override) {
                        $result['evaluation'] = [
                            'type' => $type,
                            'featureKey' => $featureKey,
                            'reason' => Evaluation::VARIABLE_OVERRIDE_VARIATION,
                            'bucketKey' => $bucketKey,
                            'bucketValue' => $bucketValue,
                            'ruleKey' => $matchedTraffic['key'] ?? null,
                            'traffic' => $matchedTraffic,
                            'variableKey' => $variableKey,
                            'variableSchema' => $variableSchema,
                            'variableValue' => $override['value'],
                            'variableOverrideIndex' => $overrideIndex,
                        ];

                        Diagnostics::reportEvaluation($options, $result['evaluation'], 'variable override from variation');

                        return $result;
                    }
                }

                if ($variation && isset($variation['variables']) && array_key_exists($variableKey, $variation['variables'])) {
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

                    Diagnostics::reportEvaluation($options, $result['evaluation'], 'allocated variable');

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

            Diagnostics::reportEvaluation($options, $result['evaluation'], 'no matched variation');

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

                Diagnostics::reportEvaluation($options, $result['evaluation'], 'using default value');

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

            Diagnostics::reportEvaluation($options, $result['evaluation'], 'variable not found');

            return $result;
        }

        return $result;
    }
}
