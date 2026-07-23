<?php

namespace Featurevisor;

use Featurevisor\Internal\Diagnostics;

class EvaluateSticky
{
    public static function evaluate(array $options): ?array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $variableKey = $options['variableKey'] ?? null;
        $sticky = $options['sticky'] ?? null;
        if ($sticky && array_key_exists($featureKey, $sticky)) {
            $evaluation = null;

            // flag
            if ($type === 'flag' && array_key_exists('enabled', $sticky[$featureKey])) {
                $evaluation = [
                    'type' => $type,
                    'featureKey' => $featureKey,
                    'reason' => Evaluation::STICKY,
                    'sticky' => $sticky[$featureKey],
                    'enabled' => $sticky[$featureKey]['enabled']
                ];

                Diagnostics::reportEvaluation($options, $evaluation, 'using sticky enabled');

                return $evaluation;
            }

            // variation
            if ($type === 'variation') {
                if (array_key_exists('variation', $sticky[$featureKey])) {
                    $variationValue = $sticky[$featureKey]['variation'];
                    $evaluation = [
                        'type' => $type,
                        'featureKey' => $featureKey,
                        'reason' => Evaluation::STICKY,
                        'variationValue' => $variationValue
                    ];

                    Diagnostics::reportEvaluation($options, $evaluation, 'using sticky variation');

                    return $evaluation;
                }
            }

            // variable
            if ($variableKey) {
                $variables = $sticky[$featureKey]['variables'] ?? null;

                if ($variables && array_key_exists($variableKey, $variables)) {
                    $result = $variables[$variableKey];
                    $evaluation = [
                        'type' => $type,
                        'featureKey' => $featureKey,
                        'reason' => Evaluation::STICKY,
                        'variableKey' => $variableKey,
                        'variableValue' => $result
                    ];

                    Diagnostics::reportEvaluation($options, $evaluation, 'using sticky variable');

                    return $evaluation;
                }
            }
        }

        return null;
    }
}
