<?php

namespace Featurevisor;

class EvaluateDisabled
{
    public static function evaluate(array $options, array $flag): ?array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $datafileReader = $options['datafileReader'];
        $variableKey = $options['variableKey'] ?? null;
        $logger = $options['logger'];

        if ($type !== 'flag') {
            $evaluation = null;

            if (($flag['enabled'] ?? true) === false) {
                $evaluation = [
                    'type' => $type,
                    'featureKey' => $featureKey,
                    'reason' => Evaluation::DISABLED
                ];

                $feature = $datafileReader->getFeature($featureKey);

                // serve variable default value if feature is disabled (if explicitly specified)
                if ($type === 'variable') {
                    if ($feature && $variableKey && isset($feature['variablesSchema'][$variableKey])) {
                        $variableSchema = $feature['variablesSchema'][$variableKey];

                        if (isset($variableSchema['disabledValue'])) {
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
                if ($type === 'variation' && $feature && isset($feature['disabledVariationValue'])) {
                    $evaluation = [
                        'type' => $type,
                        'featureKey' => $featureKey,
                        'reason' => Evaluation::VARIATION_DISABLED,
                        'variationValue' => $feature['disabledVariationValue'],
                        'enabled' => false
                    ];
                }

                $logger->debug('feature is disabled', $evaluation);

                return $evaluation;
            }
        }

        return null;
    }
}
