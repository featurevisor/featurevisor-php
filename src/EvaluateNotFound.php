<?php

namespace Featurevisor;

use Featurevisor\Internal\Diagnostics;

class EvaluateNotFound
{
    public static function evaluate(array $options): array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $variableKey = $options['variableKey'] ?? null;
        $datafile = $options['datafile'];

        $result = [];

        $feature = is_string($featureKey) ? $datafile['getFeature']($featureKey) : $featureKey;

        // feature: not found
        if (!$feature) {
            $result['evaluation'] = [
                'type' => $type,
                'featureKey' => $featureKey,
                'reason' => Evaluation::FEATURE_NOT_FOUND
            ];

            Diagnostics::reportEvaluation($options, $result['evaluation'], 'Feature not found', 'warn', 'feature_not_found');

            return $result;
        }

        $result['feature'] = $feature;

        // feature: deprecated
        if ($type === 'flag' && ($feature['deprecated'] ?? false)) {
            ($options['reportDiagnostic'])([
                'level' => 'warn',
                'code' => 'deprecated_feature',
                'message' => 'Feature is deprecated',
                'details' => ['featureKey' => $featureKey],
            ]);
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

                Diagnostics::reportEvaluation($options, $result['evaluation'], 'Variable schema not found', 'warn', 'variable_not_found');

                return $result;
            }

            $result['variableSchema'] = $variableSchema;
            if ($variableSchema['deprecated'] ?? false) {
                ($options['reportDiagnostic'])([
                    'level' => 'warn',
                    'code' => 'deprecated_variable',
                    'message' => 'Variable is deprecated',
                    'details' => ['featureKey' => $featureKey, 'variableKey' => $variableKey],
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

            Diagnostics::reportEvaluation($options, $result['evaluation'], 'No variations', 'warn', 'no_variations');

            return $result;
        }

        return $result;
    }
}
