<?php

namespace Featurevisor;

class Instance
{
    private array $context = [];
    private Logger $logger;
    private ?array $sticky = null;
    private DatafileReader $datafileReader;
    private HooksManager $hooksManager;
    private Emitter $emitter;

    public function __construct(array $options = [])
    {
        // from options
        $this->context = $options['context'] ?? [];
        $this->logger = $options['logger'] ?? createLogger([
            'level' => $options['logLevel'] ?? Logger::DEFAULT_LEVEL
        ]);
        $this->hooksManager = new HooksManager([
            'hooks' => $options['hooks'] ?? [],
            'logger' => $this->logger
        ]);
        $this->emitter = new Emitter();
        $this->sticky = $options['sticky'] ?? null;

        // datafile
        $emptyDatafile = [
            'schemaVersion' => '2',
            'revision' => 'unknown',
            'segments' => [],
            'features' => []
        ];

        $this->datafileReader = new DatafileReader([
            'datafile' => $emptyDatafile,
            'logger' => $this->logger
        ]);

        if (isset($options['datafile'])) {
            $datafile = is_string($options['datafile']) ? json_decode($options['datafile'], true) : $options['datafile'];
            $this->datafileReader = new DatafileReader([
                'datafile' => $datafile,
                'logger' => $this->logger
            ]);
        }

        $this->logger->info('Featurevisor SDK initialized');
    }

    public function setLogLevel(string $level): void
    {
        $this->logger->setLevel($level);
    }

    public function setDatafile($datafile): void
    {
        try {
            $newDatafileReader = new DatafileReader([
                'datafile' => is_string($datafile) ? json_decode($datafile, true) : $datafile,
                'logger' => $this->logger
            ]);

            $details = Events::getParamsForDatafileSetEvent($this->datafileReader, $newDatafileReader);

            $this->datafileReader = $newDatafileReader;

            $this->logger->info('datafile set', $details);
            $this->emitter->trigger('datafile_set', $details);
        } catch (\Exception $e) {
            $this->logger->error('could not parse datafile', ['error' => $e->getMessage()]);
        }
    }

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

    public function getContext(array $context = []): array
    {
        return !empty($context) ? array_merge($this->context, $context) : $this->context;
    }

    public function spawn(array $context = [], array $options = []): Child
    {
        return new Child([
            'parent' => $this,
            'context' => $this->getContext($context),
            'sticky' => $options['sticky'] ?? null
        ]);
    }

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

    public function evaluateFlag(string $featureKey, array $context = [], array $options = []): array
    {
        $deps = $this->getEvaluationDependencies($context, $options);

        return Evaluate::evaluateWithHooks(array_merge($deps, [
            'type' => 'flag',
            'featureKey' => $featureKey
        ]));
    }

    public function isEnabled(string $featureKey, array $context = [], array $options = []): bool
    {
        $evaluation = $this->evaluateFlag($featureKey, $context, $options);

        return $evaluation['enabled'] ?? false;
    }

    public function evaluateVariation(string $featureKey, array $context = [], array $options = []): array
    {
        $deps = $this->getEvaluationDependencies($context, $options);

        return Evaluate::evaluateWithHooks(array_merge($deps, [
            'type' => 'variation',
            'featureKey' => $featureKey
        ]));
    }

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
            $this->logger->error('getVariation', [
                'featureKey' => $featureKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function evaluateVariable(string $featureKey, string $variableKey, array $context = [], array $options = []): array
    {
        $deps = $this->getEvaluationDependencies($context, $options);

        return Evaluate::evaluateWithHooks(array_merge($deps, [
            'type' => 'variable',
            'featureKey' => $featureKey,
            'variableKey' => $variableKey
        ]));
    }

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
            $this->logger->error('getVariable', [
                'featureKey' => $featureKey,
                'variableKey' => $variableKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function getVariableBoolean(string $featureKey, string $variableKey, array $context = [], array $options = []): ?bool
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }

    public function getVariableString(string $featureKey, string $variableKey, array $context = [], array $options = []): ?string
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    public function getVariableInteger(string $featureKey, string $variableKey, array $context = [], array $options = []): ?int
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    public function getVariableDouble(string $featureKey, string $variableKey, array $context = [], array $options = []): ?float
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        if ($value === null) {
            return null;
        }

        return (float) $value;
    }

    public function getVariableArray(string $featureKey, string $variableKey, array $context = [], array $options = []): ?array
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        if ($value === null) {
            return null;
        }

        return is_array($value) ? $value : [$value];
    }

    public function getVariableObject(string $featureKey, string $variableKey, array $context = [], array $options = [])
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        if ($value === null) {
            return null;
        }

        return is_array($value) ? $value : null;
    }

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
            if (method_exists($this->datafileReader, 'hasVariations') && $this->datafileReader->hasVariations($featureKey)) {
                $variation = $this->getVariation($featureKey, $context, $opts);
                if ($variation !== null) {
                    $evaluatedFeature['variation'] = $variation;
                }
            }
            // variables
            if (method_exists($this->datafileReader, 'getVariableKeys')) {
                $variableKeys = $this->datafileReader->getVariableKeys($featureKey);
                if (!empty($variableKeys)) {
                    $evaluatedFeature['variables'] = [];
                    foreach ($variableKeys as $variableKey) {
                        $evaluatedFeature['variables'][$variableKey] = $this->getVariable($featureKey, $variableKey, $context, $opts);
                    }
                }
            }
            $evaluations[$featureKey] = $evaluatedFeature;
        }
        return $evaluations;
    }
}

function createInstance(array $options = []): Instance
{
    return new Instance($options);
}
