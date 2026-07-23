<?php

namespace Featurevisor;

use Featurevisor\Internal\Diagnostics;

class EvaluateDisabled
{
    public static function evaluate(array $options, array $flag): ?array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $datafile = $options['datafile'];
        $variableKey = $options['variableKey'] ?? null;
        if ($type !== 'flag') {
            $evaluation = null;

            if (($flag['enabled'] ?? true) === false) {
                $evaluation = [
                    'type' => $type,
                    'featureKey' => $featureKey,
                    'reason' => Evaluation::DISABLED
                ];

                $feature = $datafile['getFeature']($featureKey);

                // serve variable default value if feature is disabled (if explicitly specified)
                if ($type === 'variable') {
                    if ($feature && $variableKey && isset($feature['variablesSchema'][$variableKey])) {
                        $variableSchema = $feature['variablesSchema'][$variableKey];

                        if (array_key_exists('disabledValue', $variableSchema)) {
                            // disabledValue: <value>
                            $evaluation = [
                                'type' => $type,
                                'featureKey' => $featureKey,
                                'reason' => Evaluation::VARIABLE_DISABLED,
                                'variableKey' => $variableKey,
                                'variableValue' => $variableSchema['disabledValue'],
                                'variableSchema' => $variableSchema,
                                'enabled' => false
                            ];
                        } elseif ($variableSchema['useDefaultWhenDisabled'] ?? false) {
                            // useDefaultWhenDisabled: true
                            $evaluation = [
                                'type' => $type,
                                'featureKey' => $featureKey,
                                'reason' => Evaluation::VARIABLE_DEFAULT,
                                'variableKey' => $variableKey,
                                'variableValue' => $variableSchema['defaultValue'],
                                'variableSchema' => $variableSchema,
                                'enabled' => false
                            ];
                        }
                    }
                }

                // serve disabled variation value if feature is disabled (if explicitly specified)
                if ($type === 'variation' && $feature && array_key_exists('disabledVariationValue', $feature)) {
                    $evaluation = [
                        'type' => $type,
                        'featureKey' => $featureKey,
                        'reason' => Evaluation::VARIATION_DISABLED,
                        'variationValue' => $feature['disabledVariationValue'],
                        'enabled' => false
                    ];
                }

                Diagnostics::reportEvaluation($options, $evaluation, 'feature is disabled');

                return $evaluation;
            }
        }

        return null;
    }
}
