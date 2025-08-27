<?php

namespace Featurevisor;

use Closure;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Featurevisor
{
    private array $context;
    private LoggerInterface $logger;
    private ?array $sticky;
    private DatafileReader $datafileReader;
    private HooksManager $hooksManager;
    private Emitter $emitter;

    /**
     * @param array{
     *     datafile?: string|array<string, mixed>,
     *     logger?: LoggerInterface,
     *     context?: array<string, mixed>,
     *     sticky?: array<string, mixed>,
     *     hooks?: array<array{
     *         name: string,
     *         before?: Closure,
     *         after?: Closure,
     *         bucketKey?: Closure,
     *         bucketValue?: Closure
     *    }>
     * } $options
     * @return self
     */
    public static function createInstance(array $options): self
    {
        $logger = $options['logger'] ?? new NullLogger();

        return new self(
            isset($options['datafile'])
                ? DatafileReader::createFromMixed($options['datafile'], $logger)
                : DatafileReader::createEmpty($logger),
            $logger,
            HooksManager::createFromOptions($options),
            new Emitter(),
            $options['context'] ?? [],
            $options['sticky'] ?? null
        );
    }

    public function __construct(
        DatafileReader $datafile,
        LoggerInterface $logger,
        HooksManager $hooksManager,
        Emitter $emitter,
        array $context = [],
        ?array $sticky = null
    )
    {
        $this->datafileReader = $datafile;
        $this->logger = $logger;
        $this->hooksManager = $hooksManager;
        $this->sticky = $sticky;
        $this->emitter = $emitter;
        $this->context = $context;

        $this->logger->info('Featurevisor SDK initialized');
    }

    /**
     * @param string|array<string, mixed> $datafile
     */
    public function setDatafile($datafile): void
    {
        try {
            $newDatafileReader = DatafileReader::createFromMixed($datafile, $this->logger);

            $details = Events::getParamsForDatafileSetEvent($this->datafileReader, $newDatafileReader);

            $this->datafileReader = $newDatafileReader;

            $this->logger->info('datafile set', $details);
            $this->emitter->trigger('datafile_set', $details);
        } catch (\Exception $e) {
            $this->logger->error('could not parse datafile', ['error' => $e->getMessage(), 'exception' => $e]);
        }
    }

    /**
     * @param array<string, mixed> $sticky
     */
    public function setSticky(array $sticky, bool $replace = false): void
    {
        $previousStickyFeatures = $this->sticky ?? [];

        if ($replace) {
            $this->sticky = $sticky;
        } else {
            $this->sticky = array_merge($this->sticky ?? [], $sticky);
        }

        $params = Events::getParamsForStickySetEvent($previousStickyFeatures, $this->sticky, $replace);

        $this->logger->info('sticky features set', $params);
        $this->emitter->trigger('sticky_set', $params);
    }

    public function getRevision(): string
    {
        return $this->datafileReader->getRevision();
    }

    public function getFeature(string $featureKey): ?array
    {
        return $this->datafileReader->getFeature($featureKey);
    }

    public function addHook(array $hook): ?callable
    {
        return $this->hooksManager->add($hook);
    }

    public function on(string $eventName, callable $callback): callable
    {
        return $this->emitter->on($eventName, $callback);
    }

    public function close(): void
    {
        $this->emitter->clearAll();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setContext(array $context, bool $replace = false): void
    {
        if ($replace) {
            $this->context = $context;
        } else {
            $this->context = array_merge($this->context, $context);
        }

        $this->emitter->trigger('context_set', [
            'context' => $this->context,
            'replaced' => $replace
        ]);

        $this->logger->debug($replace ? 'context replaced' : 'context updated', [
            'context' => $this->context,
            'replaced' => $replace
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function getContext(array $context = []): array
    {
        return !empty($context) ? array_merge($this->context, $context) : $this->context;
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     sticky?: array<string, mixed>
     * } $options
     * @return Child
     */
    public function spawn(array $context = [], array $options = []): Child
    {
        return new Child([
            'parent' => $this,
            'context' => $this->getContext($context),
            'sticky' => $options['sticky'] ?? null
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     * @return array
     */
    private function getEvaluationDependencies(array $context, array $options = []): array
    {
        $sticky = $this->sticky;
        if (isset($options['sticky'])) {
            if ($this->sticky && is_array($this->sticky) && is_array($options['sticky'])) {
                $sticky = array_merge($this->sticky, $options['sticky']);
            } else {
                $sticky = $options['sticky'];
            }
        }
        return array_merge($options, [
            'context' => $this->getContext($context),
            'logger' => $this->logger,
            'hooksManager' => $this->hooksManager,
            'datafileReader' => $this->datafileReader,
            'sticky' => $sticky,
            'defaultVariationValue' => $options['defaultVariationValue'] ?? null,
            'defaultVariableValue' => $options['defaultVariableValue'] ?? null,
            'flagEvaluation' => $options['flagEvaluation'] ?? null
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     * @return array{
     *     type: string,
     *     featureKey: string,
     *     reason: string,
     *     bucketKey: string,
     *     bucketValue: string,
     *     enabled: bool,
     *     error?: string,
     * }
     */
    public function evaluateFlag(string $featureKey, array $context = [], array $options = []): array
    {
        $deps = $this->getEvaluationDependencies($context, $options);

        return Evaluate::evaluateWithHooks(array_merge($deps, [
            'type' => 'flag',
            'featureKey' => $featureKey
        ]));
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     */
    public function isEnabled(string $featureKey, array $context = [], array $options = []): bool
    {
        $evaluation = $this->evaluateFlag($featureKey, $context, $options);

        return $evaluation['enabled'] ?? false;
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     * @return array{
     *     type: string,
     *     featureKey: string,
     *     reason: string,
     *     bucketKey: string,
     *     bucketValue: string,
     *     variation: array<string, mixed>,
     *     enabled: bool,
     *     error?: string,
     * }
     */
    public function evaluateVariation(string $featureKey, array $context = [], array $options = []): array
    {
        $deps = $this->getEvaluationDependencies($context, $options);

        return Evaluate::evaluateWithHooks(array_merge($deps, [
            'type' => 'variation',
            'featureKey' => $featureKey
        ]));
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     * @return mixed|null
     */
    public function getVariation(string $featureKey, array $context = [], array $options = [])
    {
        try {
            $evaluation = $this->evaluateVariation($featureKey, $context, $options);

            if (isset($evaluation['variationValue'])) {
                return $evaluation['variationValue'];
            }

            if (isset($evaluation['variation']['value'])) {
                return $evaluation['variation']['value'];
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
                'action' => 'getVariation',
                'featureKey' => $featureKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     * @return array{
     *     type: string,
     *     featureKey: string,
     *     reason: string,
     *     bucketKey: string,
     *     bucketValue: string,
     *     enabled: bool,
     *     error?: string,
     * }
     */
    public function evaluateVariable(string $featureKey, string $variableKey, array $context = [], array $options = []): array
    {
        $deps = $this->getEvaluationDependencies($context, $options);

        return Evaluate::evaluateWithHooks(array_merge($deps, [
            'type' => 'variable',
            'featureKey' => $featureKey,
            'variableKey' => $variableKey
        ]));
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     * @return mixed|null
     */
    public function getVariable(string $featureKey, string $variableKey, array $context = [], array $options = [])
    {
        try {
            $evaluation = $this->evaluateVariable($featureKey, $variableKey, $context, $options);

            if (array_key_exists('variableValue', $evaluation)) {
                if (
                    isset($evaluation['variableSchema']) &&
                    isset($evaluation['variableSchema']['type']) &&
                    $evaluation['variableSchema']['type'] === 'json' &&
                    is_string($evaluation['variableValue'])
                ) {
                    $decoded = json_decode($evaluation['variableValue'], true);
                    if ($decoded !== null) {
                        return $decoded;
                    }
                }
                return $evaluation['variableValue'];
            }
            return null;
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
                'action' => 'getVariable',
                'featureKey' => $featureKey,
                'variableKey' => $variableKey,
            ]);
            return null;
        }
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     */
    public function getVariableBoolean(string $featureKey, string $variableKey, array $context = [], array $options = []): ?bool
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     */
    public function getVariableString(string $featureKey, string $variableKey, array $context = [], array $options = []): ?string
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     */
    public function getVariableInteger(string $featureKey, string $variableKey, array $context = [], array $options = []): ?int
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     */
    public function getVariableDouble(string $featureKey, string $variableKey, array $context = [], array $options = []): ?float
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     */
    public function getVariableArray(string $featureKey, string $variableKey, array $context = [], array $options = []): ?array
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        if ($value === null) {
            return null;
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     */
    public function getVariableObject(string $featureKey, string $variableKey, array $context = [], array $options = [])
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        if ($value === null) {
            return null;
        }

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     * @return array<mixed>|mixed|null
     */
    public function getVariableJSON(string $featureKey, string $variableKey, array $context = [], array $options = [])
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : $value;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string> $featureKeys
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>,
     *     sticky?: array<string, mixed>
     * } $options
     * @return array<string, mixed>
     */
    public function getAllEvaluations(array $context = [], array $featureKeys = [], array $options = []): array
    {
        $deps = $this->getEvaluationDependencies($context, $options);
        $evaluations = [];
        if (empty($featureKeys)) {
            $featureKeys = $this->datafileReader->getFeatureKeys();
        }
        foreach ($featureKeys as $featureKey) {
            // isEnabled
            $flagEvaluation = $this->evaluateFlag($featureKey, $context, $options);
            $evaluatedFeature = [
                'enabled' => isset($flagEvaluation['enabled']) ? $flagEvaluation['enabled'] === true : false,
            ];
            $opts = array_merge($options, [
                'flagEvaluation' => $flagEvaluation,
            ]);
            // variation
            if ($this->datafileReader->hasVariations($featureKey)) {
                $variation = $this->getVariation($featureKey, $context, $opts);
                if ($variation !== null) {
                    $evaluatedFeature['variation'] = $variation;
                }
            }
            // variables
            $variableKeys = $this->datafileReader->getVariableKeys($featureKey);
            if (!empty($variableKeys)) {
                $evaluatedFeature['variables'] = [];
                foreach ($variableKeys as $variableKey) {
                    $evaluatedFeature['variables'][$variableKey] = $this->getVariable($featureKey, $variableKey, $context, $opts);
                }
            }
            $evaluations[$featureKey] = $evaluatedFeature;
        }
        return $evaluations;
    }
}
