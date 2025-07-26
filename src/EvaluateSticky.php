<?php

namespace Featurevisor;

class EvaluateSticky
{
    public static function evaluate(array $options): ?array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $variableKey = $options['variableKey'] ?? null;
        $sticky = $options['sticky'] ?? null;
        $logger = $options['logger'];

        if ($sticky && isset($sticky[$featureKey])) {
            $evaluation = null;

            // flag
            if ($type === 'flag' && isset($sticky[$featureKey]['enabled'])) {
                $evaluation = [
                    'type' => $type,
                    'featureKey' => $featureKey,
                    'reason' => Evaluation::STICKY,
                    'sticky' => $sticky[$featureKey],
                    'enabled' => $sticky[$featureKey]['enabled']
                ];

                $logger->debug('using sticky enabled', $evaluation);

                return $evaluation;
            }

            // variation
            if ($type === 'variation') {
                $variationValue = $sticky[$featureKey]['variation'] ?? null;

                if ($variationValue !== null) {
                    $evaluation = [
                        'type' => $type,
                        'featureKey' => $featureKey,
                        'reason' => Evaluation::STICKY,
                        'variationValue' => $variationValue
                    ];

                    $logger->debug('using sticky variation', $evaluation);

                    return $evaluation;
                }
            }

            // variable
            if ($variableKey) {
                $variables = $sticky[$featureKey]['variables'] ?? null;

                if ($variables && isset($variables[$variableKey])) {
                    $result = $variables[$variableKey];

                    if ($result !== null) {
                        $evaluation = [
                            'type' => $type,
                            'featureKey' => $featureKey,
                            'reason' => Evaluation::STICKY,
                            'variableKey' => $variableKey,
                            'variableValue' => $result
                        ];

                        $logger->debug('using sticky variable', $evaluation);

                        return $evaluation;
                    }
                }
            }
        }

        return null;
    }
}
