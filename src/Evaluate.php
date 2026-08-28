<?php

namespace Featurevisor;

use Featurevisor\Internal\Diagnostics;

class Evaluate
{
    public static function evaluateWithModules(array $opts): array
    {
        try {
            $modulesManager = $opts['modulesManager'];

            // run before modules
            $options = $modulesManager->runBeforeModules($opts);
            $options = $modulesManager->runBeforeEvaluationModules($options);

            // evaluate
            $evaluation = self::evaluate($options);

            // default: variation
            if (
                array_key_exists('defaultVariationValue', $options) &&
                $evaluation['type'] === 'variation' &&
                !array_key_exists('variationValue', $evaluation) &&
                !array_key_exists('variation', $evaluation)
            ) {
                $evaluation['variationValue'] = $options['defaultVariationValue'];
            }

            // default: variable
            if (
                array_key_exists('defaultVariableValue', $options) &&
                $evaluation['type'] === 'variable' &&
                !array_key_exists('variableValue', $evaluation)
            ) {
                $evaluation['variableValue'] = $options['defaultVariableValue'];
            }

            // run after modules
            $evaluation = $modulesManager->runAfterEvaluationModules($evaluation, $options);
            $evaluation = $modulesManager->runAfterModules($evaluation, $options);

            return $evaluation;
        } catch (\Throwable $e) {
            $type = $opts['type'];
            $featureKey = $opts['featureKey'];
            $variableKey = $opts['variableKey'] ?? null;
            $evaluation = [
                'type' => $type,
                'featureKey' => $featureKey,
                'variableKey' => $variableKey,
                'reason' => Evaluation::ERROR,
                'error' => $e
            ];

            Diagnostics::reportEvaluation($opts, $evaluation, 'Error during evaluation', 'error', 'evaluation_error');

            return $evaluation;
        }
    }

    private static function evaluateRequired(array $options, array $feature): ?array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $requirements = $feature['requiredFeatures'] ?? ($feature['required'] ?? null);
        if ($type === 'flag' && $requirements && !self::requiredFeaturesAreMatched($requirements, $options)) {
                $evaluation = [
                    'type' => $type,
                    'featureKey' => $featureKey,
                    'reason' => Evaluation::REQUIRED,
                    'required' => $feature['required'] ?? null,
                    'requiredFeatures' => $feature['requiredFeatures'] ?? null,
                    'enabled' => false
                ];

                Diagnostics::reportEvaluation($options, $evaluation, 'required features not enabled');

                return $evaluation;
        }

        return null;
    }

    /** @param mixed $requirements */
    public static function requiredFeaturesAreMatched($requirements, array $options): bool
    {
        if ($requirements === null) return true;
        $isList = is_array($requirements) && ($requirements === [] || array_keys($requirements) === range(0, count($requirements) - 1));
        $items = $isList ? $requirements : [$requirements];
        $cleanOptions = $options;
        unset($cleanOptions['type'], $cleanOptions['featureKey'], $cleanOptions['variableKey'], $cleanOptions['defaultVariationValue'], $cleanOptions['defaultVariableValue']);
        foreach ($items as $required) {
            $key = is_string($required) ? $required : ($required['feature'] ?? $required['key']);
            $expectedEnabled = is_string($required) ? true : ($required['enabled'] ?? true);
            $expectedVariation = is_string($required) ? null : ($required['variation'] ?? null);
            $flag = self::evaluateWithModules(array_merge($cleanOptions, ['type' => 'flag', 'featureKey' => $key]));
            if ((($flag['enabled'] ?? false) === true) !== $expectedEnabled) return false;
            if ($expectedVariation !== null) {
                $variation = self::evaluateWithModules(array_merge($cleanOptions, ['type' => 'variation', 'featureKey' => $key]));
                $actual = $variation['variationValue'] ?? ($variation['variation']['value'] ?? null);
                if ($actual !== $expectedVariation) return false;
            }
        }
        return true;
    }

    public static function evaluate(array $options): array
    {
        $type = $options['type'];
        $featureKey = $options['featureKey'];
        $variableKey = $options['variableKey'] ?? null;
        $evaluation = null;

        try {
            // root
            $flag = null;

            if ($type !== 'flag') {
                // needed by variation and variable evaluations
                $flag = self::evaluate(array_merge($options, [
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

            Diagnostics::reportEvaluation($options, $evaluation, 'nothing matched');

            return $evaluation;
        } catch (\Throwable $e) {
            $evaluation = [
                'type' => $type,
                'featureKey' => $featureKey,
                'variableKey' => $variableKey,
                'reason' => Evaluation::ERROR,
                'error' => $e
            ];

            Diagnostics::reportEvaluation($options, $evaluation, 'Error during evaluation', 'error', 'evaluation_error');

            return $evaluation;
        }
    }
}
