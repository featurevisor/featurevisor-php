<?php

namespace Featurevisor;

class EvaluateNotFound
{
    public static function evaluate(array $options): array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $variableKey = $options['variableKey'] ?? null;
        $logger = $options['logger'];
        $datafileReader = $options['datafileReader'];

        $result = [];

        $feature = is_string($featureKey) ? $datafileReader->getFeature($featureKey) : $featureKey;

        // feature: not found
        if (!$feature) {
            $result['evaluation'] = [
                'type' => $type,
                'featureKey' => $featureKey,
                'reason' => Evaluation::FEATURE_NOT_FOUND
            ];

            $logger->warn('feature not found', $result['evaluation']);

            return $result;
        }

        $result['feature'] = $feature;

        // feature: deprecated
        if ($type === 'flag' && ($feature['deprecated'] ?? false)) {
            $logger->warn('feature is deprecated', ['featureKey' => $featureKey]);
        }

        // variableSchema
        $variableSchema = null;

        if ($variableKey) {
            if (isset($feature['variablesSchema'][$variableKey])) {
                $variableSchema = $feature['variablesSchema'][$variableKey];
            }

            // variable schema not found
            if (!$variableSchema) {
                $result['evaluation'] = [
                    'type' => $type,
                    'featureKey' => $featureKey,
                    'reason' => Evaluation::VARIABLE_NOT_FOUND,
                    'variableKey' => $variableKey
                ];

                $logger->warn('variable schema not found', $result['evaluation']);

                return $result;
            }

            $result['variableSchema'] = $variableSchema;

            if ($variableSchema['deprecated'] ?? false) {
                $logger->warn('variable is deprecated', [
                    'featureKey' => $featureKey,
                    'variableKey' => $variableKey
                ]);
            }
        }

        // variation: no variations
        if ($type === 'variation' && (empty($feature['variations']) || count($feature['variations']) === 0)) {
            $result['evaluation'] = [
                'type' => $type,
                'featureKey' => $featureKey,
                'reason' => Evaluation::NO_VARIATIONS
            ];

            $logger->warn('no variations', $result['evaluation']);

            return $result;
        }

        return $result;
    }
}
