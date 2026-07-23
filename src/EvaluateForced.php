<?php

namespace Featurevisor;

use Featurevisor\Internal\Diagnostics;

class EvaluateForced
{
    public static function evaluate(array $options, array $feature, ?array $variableSchema = null): array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $variableKey = $options['variableKey'] ?? null;
        $context = $options['context'];
        $datafile = $options['datafile'];

        $forceResult = $datafile['getMatchedForce']($feature, $context);
        $force = $forceResult['force'] ?? null;
        $forceIndex = $forceResult['forceIndex'] ?? null;

        $result = [
            'force' => $force,
            'forceIndex' => $forceIndex
        ];

        if ($force) {
            // flag
            if ($type === 'flag' && array_key_exists('enabled', $force)) {
                $result['evaluation'] = [
                    'type' => $type,
                    'featureKey' => $featureKey,
                    'reason' => Evaluation::FORCED,
                    'forceIndex' => $forceIndex,
                    'force' => $force,
                    'enabled' => $force['enabled']
                ];

                Diagnostics::reportEvaluation($options, $result['evaluation'], 'forced enabled found');

                return $result;
            }

            // variation
            if ($type === 'variation' && isset($force['variation']) && isset($feature['variations'])) {
                $variation = null;
                foreach ($feature['variations'] as $v) {
                    if ($v['value'] === $force['variation']) {
                        $variation = $v;
                        break;
                    }
                }

                if ($variation) {
                    $result['evaluation'] = [
                        'type' => $type,
                        'featureKey' => $featureKey,
                        'reason' => Evaluation::FORCED,
                        'forceIndex' => $forceIndex,
                        'force' => $force,
                        'variation' => $variation
                    ];

                    Diagnostics::reportEvaluation($options, $result['evaluation'], 'forced variation found');

                    return $result;
                }
            }

            // variable
            if ($variableKey && isset($force['variables']) && array_key_exists($variableKey, $force['variables'])) {
                $result['evaluation'] = [
                    'type' => $type,
                    'featureKey' => $featureKey,
                    'reason' => Evaluation::FORCED,
                    'forceIndex' => $forceIndex,
                    'force' => $force,
                    'variableKey' => $variableKey,
                    'variableSchema' => $variableSchema,
                    'variableValue' => $force['variables'][$variableKey]
                ];

                Diagnostics::reportEvaluation($options, $result['evaluation'], 'forced variable');

                return $result;
            }
        }

        return $result;
    }
}
