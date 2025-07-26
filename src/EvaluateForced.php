<?php

namespace Featurevisor;

class EvaluateForced
{
    public static function evaluate(array $options, array $feature, ?array $variableSchema = null): array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $variableKey = $options['variableKey'] ?? null;
        $context = $options['context'];
        $logger = $options['logger'];
        $datafileReader = $options['datafileReader'];

        $forceResult = $datafileReader->getMatchedForce($feature, $context);
        $force = $forceResult['force'] ?? null;
        $forceIndex = $forceResult['forceIndex'] ?? null;

        $result = [
            'force' => $force,
            'forceIndex' => $forceIndex
        ];

        if ($force) {
            // flag
            if ($type === 'flag' && isset($force['enabled'])) {
                $result['evaluation'] = [
                    'type' => $type,
                    'featureKey' => $featureKey,
                    'reason' => Evaluation::FORCED,
                    'forceIndex' => $forceIndex,
                    'force' => $force,
                    'enabled' => $force['enabled']
                ];

                $logger->debug('forced enabled found', $result['evaluation']);

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

                    $logger->debug('forced variation found', $result['evaluation']);

                    return $result;
                }
            }

            // variable
            if ($variableKey && isset($force['variables'][$variableKey])) {
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

                $logger->debug('forced variable', $result['evaluation']);

                return $result;
            }
        }

        return $result;
    }
}
