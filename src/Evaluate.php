<?php

namespace Featurevisor;

class Evaluate
{
    public static function evaluateWithHooks(array $opts): array
    {
        try {
            $hooksManager = $opts['hooksManager'];
            $hooks = $hooksManager->getAll();

            // run before hooks
            $options = $opts;
            foreach ($hooksManager->getAll() as $hook) {
                if (isset($hook['before'])) {
                    $options = $hook['before']($options);
                }
            }

            // evaluate
            $evaluation = self::evaluate($options);

            // default: variation
            if (
                isset($options['defaultVariationValue']) &&
                $evaluation['type'] === 'variation' &&
                !isset($evaluation['variationValue'])
            ) {
                $evaluation['variationValue'] = $options['defaultVariationValue'];
            }

            // default: variable
            if (
                isset($options['defaultVariableValue']) &&
                $evaluation['type'] === 'variable' &&
                !isset($evaluation['variableValue'])
            ) {
                $evaluation['variableValue'] = $options['defaultVariableValue'];
            }

            // run after hooks
            foreach ($hooks as $hook) {
                if (isset($hook['after'])) {
                    $evaluation = $hook['after']($evaluation, $options);
                }
            }

            return $evaluation;
        } catch (\Exception $e) {
            $type = $opts['type'];
            $featureKey = $opts['featureKey'];
            $variableKey = $opts['variableKey'] ?? null;
            $logger = $opts['logger'];

            $evaluation = [
                'type' => $type,
                'featureKey' => $featureKey,
                'variableKey' => $variableKey,
                'reason' => Evaluation::ERROR,
                'error' => $e->getMessage()
            ];

            $logger->error('error during evaluation', $evaluation);

            return $evaluation;
        }
    }

    private static function evaluateRequired(array $options, array $feature): ?array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $logger = $options['logger'];

        if ($type === 'flag' && isset($feature['required']) && count($feature['required']) > 0) {
            $requiredFeaturesAreEnabled = true;

            foreach ($feature['required'] as $required) {
                $requiredKey = null;
                $requiredVariation = null;

                if (is_string($required)) {
                    $requiredKey = $required;
                } else {
                    $requiredKey = $required['key'];
                    $requiredVariation = $required['variation'] ?? null;
                }

                $requiredEvaluation = self::evaluate(array_merge($options, [
                    'type' => 'flag',
                    'featureKey' => $requiredKey
                ]));
                $requiredIsEnabled = $requiredEvaluation['enabled'] ?? false;

                if (!$requiredIsEnabled) {
                    $requiredFeaturesAreEnabled = false;
                    break;
                }

                if ($requiredVariation !== null) {
                    $requiredVariationEvaluation = self::evaluate(array_merge($options, [
                        'type' => 'variation',
                        'featureKey' => $requiredKey
                    ]));

                    $requiredVariationValue = null;

                    if (isset($requiredVariationEvaluation['variationValue'])) {
                        $requiredVariationValue = $requiredVariationEvaluation['variationValue'];
                    } elseif (isset($requiredVariationEvaluation['variation']['value'])) {
                        $requiredVariationValue = $requiredVariationEvaluation['variation']['value'];
                    }

                    if ($requiredVariationValue !== $requiredVariation) {
                        $requiredFeaturesAreEnabled = false;
                        break;
                    }
                }
            }

            if (!$requiredFeaturesAreEnabled) {
                $evaluation = [
                    'type' => $type,
                    'featureKey' => $featureKey,
                    'reason' => Evaluation::REQUIRED,
                    'required' => $feature['required'],
                    'enabled' => $requiredFeaturesAreEnabled
                ];

                $logger->debug('required features not enabled', $evaluation);

                return $evaluation;
            }
        }

        return null;
    }

    public static function evaluate(array $options): array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $variableKey = $options['variableKey'] ?? null;
        $logger = $options['logger'];

        $evaluation = null;

        try {
            // root
            $flag = null;

            if ($type !== 'flag') {
                // needed by variation and variable evaluations
                $flag = $options['flagEvaluation'] ?? self::evaluate(array_merge($options, [
                    'type' => 'flag'
                ]));

                $disabledEvaluation = EvaluateDisabled::evaluate($options, $flag);
                if ($disabledEvaluation) {
                    return $disabledEvaluation;
                }
            }

            // sticky
            $stickyEvaluation = EvaluateSticky::evaluate($options);
            if ($stickyEvaluation) {
                return $stickyEvaluation;
            }

            // not found
            $notFoundResult = EvaluateNotFound::evaluate($options);

            if (isset($notFoundResult['evaluation'])) {
                return $notFoundResult['evaluation'];
            }

            $feature = $notFoundResult['feature'];
            $variableSchema = $notFoundResult['variableSchema'] ?? null;

            // forced
            $forcedResult = EvaluateForced::evaluate($options, $feature, $variableSchema);
            $force = $forcedResult['force'] ?? null;

            if (isset($forcedResult['evaluation'])) {
                return $forcedResult['evaluation'];
            }

            // required
            $requiredEvaluation = self::evaluateRequired($options, $feature);
            if ($requiredEvaluation) {
                return $requiredEvaluation;
            }

            // bucket
            $bucketingResult = EvaluateByBucketing::evaluate($options, $feature, $variableSchema, $force);
            $bucketKey = $bucketingResult['bucketKey'] ?? null;
            $bucketValue = $bucketingResult['bucketValue'] ?? null;

            if (isset($bucketingResult['evaluation'])) {
                return $bucketingResult['evaluation'];
            }

            // nothing matched
            $evaluation = [
                'type' => $type,
                'featureKey' => $featureKey,
                'reason' => Evaluation::NO_MATCH,
                'bucketKey' => $bucketKey,
                'bucketValue' => $bucketValue,
                'enabled' => false
            ];

            $logger->debug('nothing matched', $evaluation);

            return $evaluation;
        } catch (\Exception $e) {
            $evaluation = [
                'type' => $type,
                'featureKey' => $featureKey,
                'variableKey' => $variableKey,
                'reason' => Evaluation::ERROR,
                'error' => $e->getMessage()
            ];

            $logger->error('error', $evaluation);

            return $evaluation;
        }
    }
}
