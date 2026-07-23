<?php

namespace Featurevisor\Internal;

final class Diagnostics
{
    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $evaluation
     */
    public static function reportEvaluation(
        array $options,
        array $evaluation,
        string $message,
        string $level = 'debug',
        ?string $code = null
    ): void {
        $reportDiagnostic = $options['reportDiagnostic'] ?? null;
        if (!is_callable($reportDiagnostic)) {
            return;
        }

        $diagnostic = [
            'level' => $level,
            'code' => $code ?? ($evaluation['reason'] ?? 'evaluation_error'),
            'message' => $message,
            'details' => [
                'featureKey' => $evaluation['featureKey'] ?? null,
                'variableKey' => $evaluation['variableKey'] ?? null,
                'reason' => $evaluation['reason'] ?? null,
                'evaluation' => $evaluation,
            ],
        ];

        if (array_key_exists('error', $evaluation)) {
            $diagnostic['originalError'] = $evaluation['error'];
        }

        $reportDiagnostic($diagnostic);
    }
}
