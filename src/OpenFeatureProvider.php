<?php

declare(strict_types=1);

namespace Featurevisor;

use DateTimeInterface;
use OpenFeature\implementation\provider\AbstractProvider;
use OpenFeature\implementation\provider\ResolutionDetails;
use OpenFeature\implementation\provider\ResolutionError;
use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\interfaces\provider\ErrorCode;
use OpenFeature\interfaces\provider\Reason;
use OpenFeature\interfaces\provider\ResolutionDetails as ResolutionDetailsInterface;

/** OpenFeature provider backed by the Featurevisor v3 SDK. Requires PHP 8 and open-feature/sdk. */
final class OpenFeatureProvider extends AbstractProvider
{
    protected static string $NAME = 'Featurevisor';

    private Featurevisor $featurevisor;
    private string $targetingKeyField;
    private string $keySeparator;
    private string $variationKey;
    /** @var callable|null */
    private $onTrack;
    /** @var callable */
    private $datafileUnsubscribe;
    private ?string $datafileError = null;
    private bool $ownsFeaturevisor;

    /**
     * @param array<string, mixed> $options Featurevisor options
     * @param callable|null $onTrack function(string, ?EvaluationContext, ?array): void
     */
    public function __construct(
        array $options = [],
        ?Featurevisor $featurevisor = null,
        string $targetingKeyField = 'userId',
        string $keySeparator = ':',
        string $variationKey = 'variation',
        ?callable $onTrack = null
    ) {
        $this->targetingKeyField = $targetingKeyField !== '' ? $targetingKeyField : 'userId';
        $this->keySeparator = $keySeparator !== '' ? $keySeparator : ':';
        $this->variationKey = $variationKey !== '' ? $variationKey : 'variation';
        $this->onTrack = $onTrack;
        $this->ownsFeaturevisor = $featurevisor === null;
        if ($featurevisor !== null) {
            $this->featurevisor = $featurevisor;
        } else {
            if (isset($options['datafile']) && is_string($options['datafile'])) {
                try {
                    json_decode($options['datafile'], true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $error) {
                    $this->datafileError = 'Could not parse datafile';
                }
            }
            $originalHandler = $options['onDiagnostic'] ?? null;
            $options['onDiagnostic'] = function (array $diagnostic) use ($originalHandler): void {
                if (($diagnostic['code'] ?? null) === 'invalid_datafile') {
                    $this->datafileError = (string) $diagnostic['message'];
                }
                if (($diagnostic['code'] ?? null) === 'datafile_set') {
                    $this->datafileError = null;
                }
                if ($originalHandler !== null) {
                    $originalHandler($diagnostic);
                }
            };
            $this->featurevisor = Featurevisor::createFeaturevisor($options);
        }
        $this->datafileUnsubscribe = $this->featurevisor->on('datafile_set', function (): void {
            $this->datafileError = null;
        });
    }

    public function getFeaturevisor(): Featurevisor
    {
        return $this->featurevisor;
    }

    public function shutdown(): void
    {
        ($this->datafileUnsubscribe)();
        if ($this->ownsFeaturevisor) {
            $this->featurevisor->close();
        }
    }

    /** @param array<string, mixed>|null $details */
    public function track(string $name, ?EvaluationContext $context = null, ?array $details = null): void
    {
        if ($this->onTrack !== null) {
            ($this->onTrack)($name, $context, $details);
        }
    }

    public function resolveBooleanValue(string $flagKey, bool $defaultValue, ?EvaluationContext $context = null): ResolutionDetailsInterface
    {
        return $this->resolve($flagKey, $defaultValue, $context, 'boolean');
    }

    public function resolveStringValue(string $flagKey, string $defaultValue, ?EvaluationContext $context = null): ResolutionDetailsInterface
    {
        return $this->resolve($flagKey, $defaultValue, $context, 'string');
    }

    public function resolveIntegerValue(string $flagKey, int $defaultValue, ?EvaluationContext $context = null): ResolutionDetailsInterface
    {
        return $this->resolve($flagKey, $defaultValue, $context, 'integer');
    }

    public function resolveFloatValue(string $flagKey, float $defaultValue, ?EvaluationContext $context = null): ResolutionDetailsInterface
    {
        return $this->resolve($flagKey, $defaultValue, $context, 'number');
    }

    /** @param mixed[] $defaultValue */
    public function resolveObjectValue(string $flagKey, array $defaultValue, ?EvaluationContext $context = null): ResolutionDetailsInterface
    {
        return $this->resolve($flagKey, $defaultValue, $context, 'object');
    }

    /** @param bool|string|int|float|mixed[] $defaultValue */
    private function resolve(string $flagKey, $defaultValue, ?EvaluationContext $evaluationContext, string $expectedType): ResolutionDetailsInterface
    {
        if ($this->datafileError !== null) {
            return $this->error($defaultValue, ErrorCode::PARSE_ERROR(), $this->datafileError);
        }
        $position = strpos($flagKey, $this->keySeparator);
        $featureKey = $position === false ? $flagKey : substr($flagKey, 0, $position);
        $selector = $position === false ? null : substr($flagKey, $position + strlen($this->keySeparator));
        $context = $this->context($evaluationContext);

        if ($selector === null || $selector === '') {
            if ($expectedType !== 'boolean') {
                return $this->typeMismatch($flagKey, $defaultValue, $expectedType);
            }
            $evaluation = $this->featurevisor->evaluateFlag($featureKey, $context);
            $value = $evaluation['enabled'] ?? null;
        } elseif ($selector === $this->variationKey) {
            $evaluation = $this->featurevisor->evaluateVariation($featureKey, $context);
            $value = $evaluation['variationValue'] ?? ($evaluation['variation']['value'] ?? null);
        } else {
            $evaluation = $this->featurevisor->evaluateVariable($featureKey, $selector, $context);
            $value = $evaluation['variableValue'] ?? null;
            if (($evaluation['variableSchema']['type'] ?? null) === 'json' && is_string($value)) {
                $parsed = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $parsed;
                }
            }
        }

        $errorCode = $this->errorCode($evaluation['reason']);
        if ($errorCode !== null) {
            return $this->error($defaultValue, $errorCode, $this->errorMessage($evaluation));
        }
        if ($value === null) {
            $value = $defaultValue;
        } elseif (!$this->matches($value, $expectedType)) {
            return $this->typeMismatch($flagKey, $defaultValue, $expectedType);
        }

        $details = new ResolutionDetails();
        $details->setValue($value);
        $details->setReason($this->reason($evaluation['reason']));
        $variant = $evaluation['variationValue'] ?? ($evaluation['variation']['value'] ?? null);
        if ($variant !== null) {
            $details->setVariant((string) $variant);
        }
        return $details;
    }

    /** @return array<string, mixed> */
    private function context(?EvaluationContext $context): array
    {
        $result = $context !== null ? $this->normalize($context->getAttributes()->toArray()) : [];
        if ($context !== null && $context->getTargetingKey() !== null && $context->getTargetingKey() !== '') {
            $result[$this->targetingKeyField] = $context->getTargetingKey();
        }
        return $result;
    }

    private function reason(string $reason): string
    {
        if (in_array($reason, ['feature_not_found', 'variable_not_found', 'no_variations', 'error'], true)) return Reason::ERROR;
        if (in_array($reason, ['required', 'forced', 'sticky', 'rule', 'variable_override_variation', 'variable_override_rule'], true)) return Reason::TARGETING_MATCH;
        if ($reason === 'allocated') return Reason::SPLIT;
        if (in_array($reason, ['disabled', 'variation_disabled', 'variable_disabled'], true)) return Reason::DISABLED;
        return Reason::DEFAULT;
    }

    private function errorCode(string $reason): ?ErrorCode
    {
        if (in_array($reason, ['feature_not_found', 'variable_not_found', 'no_variations'], true)) return ErrorCode::FLAG_NOT_FOUND();
        if ($reason === 'error') return ErrorCode::GENERAL();
        return null;
    }

    /** @param array<string, mixed> $evaluation */
    private function errorMessage(array $evaluation): string
    {
        if (($evaluation['error'] ?? null) instanceof \Throwable) return $evaluation['error']->getMessage();
        if ($evaluation['reason'] === 'feature_not_found') return sprintf('Feature "%s" was not found', $evaluation['featureKey']);
        if ($evaluation['reason'] === 'variable_not_found') return sprintf('Variable "%s" was not found for feature "%s"', $evaluation['variableKey'] ?? '', $evaluation['featureKey']);
        if ($evaluation['reason'] === 'no_variations') return sprintf('Feature "%s" has no variations', $evaluation['featureKey']);
        return 'Featurevisor evaluation failed';
    }

    /** @param mixed $value */
    private function matches($value, string $expectedType): bool
    {
        if ($expectedType === 'boolean') return is_bool($value);
        if ($expectedType === 'string') return is_string($value);
        if ($expectedType === 'integer') return is_int($value) || (is_float($value) && floor($value) === $value);
        if ($expectedType === 'number') return (is_int($value) || is_float($value)) && is_finite((float) $value);
        return is_array($value);
    }

    /** @param mixed $value @return mixed */
    private function normalize($value)
    {
        if ($value instanceof DateTimeInterface) return $value->format(DATE_ATOM);
        if (is_array($value)) return array_map(fn($item) => $this->normalize($item), $value);
        return $value;
    }

    /** @param bool|string|int|float|mixed[] $value */
    private function error($value, ErrorCode $code, string $message): ResolutionDetailsInterface
    {
        $details = new ResolutionDetails();
        $details->setValue($value);
        $details->setReason(Reason::ERROR);
        $details->setError(new ResolutionError($code, $message));
        return $details;
    }

    /** @param bool|string|int|float|mixed[] $value */
    private function typeMismatch(string $key, $value, string $expected): ResolutionDetailsInterface
    {
        return $this->error($value, ErrorCode::TYPE_MISMATCH(), sprintf('Flag "%s" did not resolve to a %s value', $key, $expected));
    }
}
