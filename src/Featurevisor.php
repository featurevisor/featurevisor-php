<?php

namespace Featurevisor;

use Closure;
use Featurevisor\Internal\DatafileReader;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Featurevisor
{
    private array $context;
    private LoggerInterface $logger;
    private ?array $sticky;
    private DatafileReader $datafileReader;
    private ModulesManager $modulesManager;
    private Emitter $emitter;
    /** @var callable|null */
    private $onDiagnostic;
    /** @var array<int, array<string, mixed>> */
    private array $moduleDiagnosticSubscriptions;
    private bool $closed;

    /**
     * @param array{
     *     datafile?: string|array<string, mixed>,
     *     logLevel?: LogLevel::*|string,
     *     context?: array<string, mixed>,
     *     sticky?: array<string, mixed>,
     *     modules?: array<array{
     *         name: string,
     *         before?: Closure,
     *         after?: Closure,
     *         bucketKey?: Closure,
     *         bucketValue?: Closure
     *    }>
     * } $options
     * @return self
     */
    public static function createFeaturevisor(array $options = []): self
    {
        return new self($options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function __construct(array $options = [])
    {
        $this->logger = Logger::create([
            'level' => $options['logLevel'] ?? Logger::DEFAULT_LEVEL,
            'handler' => function (string $level, string $message, ?array $details = null): void {
                $details = $details ?? [];
                $message = preg_replace('/^\[Featurevisor\]\s*/', '', $message);
                if ($level === LogLevel::WARNING) {
                    $level = 'warn';
                } elseif (in_array($level, [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL], true)) {
                    $level = 'fatal';
                }
                $code = isset($details['reason']) ? (string) $details['reason'] : $message;
                if ($message === 'feature is deprecated') {
                    $code = 'deprecated_feature';
                } elseif ($message === 'variable is deprecated') {
                    $code = 'deprecated_variable';
                } elseif ($message === 'feature not found') {
                    $code = 'feature_not_found';
                } elseif ($message === 'variable schema not found') {
                    $code = 'variable_not_found';
                } elseif ($message === 'no variations') {
                    $code = 'no_variations';
                } elseif ($message === 'invalid bucketBy') {
                    $code = 'invalid_bucket_by';
                }
                $this->reportDiagnostic([
                    'level' => $level,
                    'code' => $code,
                    'message' => $message,
                    'details' => $details,
                ]);
            },
        ]);
        $this->emitter = new Emitter();
        $this->context = $options['context'] ?? [];
        $this->sticky = $options['sticky'] ?? null;
        $this->onDiagnostic = $options['onDiagnostic'] ?? null;
        $this->moduleDiagnosticSubscriptions = [];
        $this->closed = false;
        $this->datafileReader = DatafileReader::createEmpty($this->logger);
        $this->modulesManager = ModulesManager::createFromOptions([
            'modules' => $options['modules'] ?? [],
            'reportDiagnostic' => [$this, 'reportDiagnostic'],
            'moduleApiFactory' => [$this, 'createModuleApi'],
            'clearModuleDiagnosticSubscriptions' => [$this, 'clearModuleDiagnosticSubscriptions'],
        ]);

        if (isset($options['datafile'])) {
            $this->setDatafile($options['datafile'], true);
        }

        $this->reportDiagnostic([
            'level' => 'info',
            'code' => 'sdk_initialized',
            'message' => 'Featurevisor SDK initialized',
        ]);
    }

    /**
     * @param string|array<string, mixed> $datafile
     */
    public function setDatafile($datafile, bool $replace = false): void
    {
        if ($this->closed) {
            return;
        }

        try {
            $incomingDatafile = is_string($datafile)
                ? json_decode($datafile, true, 512, JSON_THROW_ON_ERROR)
                : $datafile;
            $nextDatafile = $replace
                ? $incomingDatafile
                : $this->mergeDatafiles($this->datafileReader->getDatafile(), $incomingDatafile);
            $newDatafileReader = DatafileReader::createFromMixed($nextDatafile, $this->logger);

            $details = Events::getParamsForDatafileSetEvent($this->datafileReader, $newDatafileReader, $replace);

            $this->datafileReader = $newDatafileReader;

            $this->reportDiagnostic([
                'level' => 'info',
                'code' => 'datafile_set',
                'message' => 'Datafile set',
                'details' => $details,
            ]);
            $this->emitter->trigger('datafile_set', $details);
        } catch (\Exception $e) {
            $this->reportDiagnostic([
                'level' => 'error',
                'code' => 'invalid_datafile',
                'message' => 'Could not parse datafile',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $sticky
     */
    public function setSticky(array $sticky, bool $replace = false): void
    {
        if ($this->closed) {
            return;
        }

        $previousStickyFeatures = $this->sticky ?? [];

        if ($replace) {
            $this->sticky = $sticky;
        } else {
            $this->sticky = array_merge($this->sticky ?? [], $sticky);
        }

        $params = Events::getParamsForStickySetEvent($previousStickyFeatures, $this->sticky, $replace);

        $this->reportDiagnostic([
            'level' => 'info',
            'code' => 'sticky_set',
            'message' => 'Sticky features set',
            'details' => $params,
        ]);
        $this->emitter->trigger('sticky_set', $params);
    }

    public function getRevision(): string
    {
        return $this->datafileReader->getRevision();
    }

    public function getSchemaVersion(): string
    {
        return $this->datafileReader->getSchemaVersion();
    }

    public function getSegment(string $segmentKey): ?array
    {
        return $this->datafileReader->getSegment($segmentKey);
    }

    public function getFeatureKeys(): array
    {
        return $this->datafileReader->getFeatureKeys();
    }

    public function getVariableKeys(string $featureKey): array
    {
        return $this->datafileReader->getVariableKeys($featureKey);
    }

    public function hasVariations(string $featureKey): bool
    {
        return $this->datafileReader->hasVariations($featureKey);
    }

    public function getFeature(string $featureKey): ?array
    {
        return $this->datafileReader->getFeature($featureKey);
    }

    public function setLogLevel(string $level): void
    {
        if (method_exists($this->logger, 'setLevel')) {
            $this->logger->setLevel($level);
        }
    }

    public function addModule(array $module): ?callable
    {
        if ($this->closed) {
            return null;
        }

        return $this->modulesManager->add($module);
    }

    /**
     * @param string|array<string, mixed> $nameOrModule
     */
    public function removeModule($nameOrModule): void
    {
        if ($this->closed) {
            return;
        }

        $this->modulesManager->remove($nameOrModule);
    }

    public function on(string $eventName, callable $callback): callable
    {
        return $this->emitter->on($eventName, $callback);
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->modulesManager->closeAll();
        $this->moduleDiagnosticSubscriptions = [];
        $this->emitter->clearAll();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function setContext(array $context, bool $replace = false): void
    {
        if ($this->closed) {
            return;
        }

        if ($replace) {
            $this->context = $context;
        } else {
            $this->context = array_merge($this->context, $context);
        }

        $this->emitter->trigger('context_set', [
            'context' => $this->context,
            'replaced' => $replace
        ]);

        $this->reportDiagnostic([
            'level' => 'debug',
            'code' => 'context_set',
            'message' => $replace ? 'Context replaced' : 'Context updated',
            'details' => [
                'context' => $this->context,
                'replaced' => $replace,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $module
     * @return array<string, callable>
     */
    public function createModuleApi(array $module): array
    {
        return [
            'getRevision' => function(): string {
                return $this->getRevision();
            },
            'onDiagnostic' => function(callable $handler, array $options = []) use ($module): callable {
                $subscription = [
                    'id' => uniqid('diagnostic_', true),
                    'moduleId' => $module['id'] ?? null,
                    'handler' => $handler,
                    'level' => $options['level'] ?? Logger::DEFAULT_LEVEL,
                ];
                $this->moduleDiagnosticSubscriptions[] = $subscription;

                return function() use ($subscription): void {
                    $this->moduleDiagnosticSubscriptions = array_values(array_filter(
                        $this->moduleDiagnosticSubscriptions,
                        static fn(array $existing): bool => $existing['id'] !== $subscription['id']
                    ));
                };
            },
            'reportDiagnostic' => function(array $diagnostic) use ($module): void {
                $this->reportDiagnostic($diagnostic, $module);
            },
        ];
    }

    /**
     * @param array<string, mixed> $module
     */
    public function clearModuleDiagnosticSubscriptions(array $module): void
    {
        $moduleId = $module['id'] ?? null;
        $this->moduleDiagnosticSubscriptions = array_values(array_filter(
            $this->moduleDiagnosticSubscriptions,
            static fn(array $subscription): bool => $subscription['moduleId'] !== $moduleId
        ));
    }

    /**
     * @param array<string, mixed> $diagnostic
     * @param array<string, mixed>|null $sourceModule
     */
    public function reportDiagnostic(array $diagnostic, ?array $sourceModule = null): void
    {
        $diagnostic['level'] = $diagnostic['level'] ?? 'info';
        if ($sourceModule && isset($sourceModule['name']) && !isset($diagnostic['moduleName'])) {
            $diagnostic['moduleName'] = $sourceModule['name'];
        }
        $details = is_array($diagnostic['details'] ?? null) ? $diagnostic['details'] : [];
        $reservedKeys = ['level', 'code', 'message', 'module', 'moduleName', 'originalError', 'details'];
        foreach ($diagnostic as $key => $value) {
            if (!in_array($key, $reservedKeys, true)) {
                $details[$key] = $value;
                unset($diagnostic[$key]);
            }
        }
        $diagnostic['details'] = $details === [] ? (object) [] : $details;

        foreach ($this->moduleDiagnosticSubscriptions as $subscription) {
            if ($sourceModule && ($subscription['moduleId'] ?? null) === ($sourceModule['id'] ?? null)) {
                continue;
            }

            if (!$this->levelAllows($diagnostic['level'], $subscription['level'])) {
                continue;
            }

            try {
                ($subscription['handler'])($diagnostic);
            } catch (\Throwable $error) {
                error_log('[Featurevisor] Diagnostic handler failed: '.$error->getMessage());
            }
        }

        $instanceLevel = method_exists($this->logger, 'getLevel') ? $this->logger->getLevel() : Logger::DEFAULT_LEVEL;
        if ($this->levelAllows($diagnostic['level'], $instanceLevel)) {
            if ($this->onDiagnostic) {
                try {
                    ($this->onDiagnostic)($diagnostic);
                } catch (\Throwable $error) {
                    error_log('[Featurevisor] Diagnostic handler failed: '.$error->getMessage());
                }
            } else {
                Logger::create(['level' => $this->logger->getLevel()])->log(
                    $this->normalizeLogLevel($diagnostic['level']),
                    $diagnostic['message'] ?? ($diagnostic['code'] ?? 'diagnostic'),
                    $diagnostic
                );
            }
        }

        if (in_array($this->normalizeLogLevel($diagnostic['level']), [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR], true)) {
            $this->emitter->trigger('error', $diagnostic);
        }
    }

    /**
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    private function mergeDatafiles(array $previous, array $incoming): array
    {
        return array_merge($previous, $incoming, [
            'segments' => array_merge($previous['segments'] ?? [], $incoming['segments'] ?? []),
            'features' => array_merge($previous['features'] ?? [], $incoming['features'] ?? []),
        ]);
    }

    private function normalizeLogLevel(string $level): string
    {
        if ($level === 'fatal') {
            return LogLevel::EMERGENCY;
        }

        if ($level === 'warn') {
            return LogLevel::WARNING;
        }

        return $level;
    }

    private function levelAllows(string $diagnosticLevel, string $configuredLevel): bool
    {
        $levels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];

        $diagnosticLevel = $this->normalizeLogLevel($diagnosticLevel);
        $configuredLevel = $this->normalizeLogLevel($configuredLevel);

        if (!in_array($diagnosticLevel, $levels, true) || !in_array($configuredLevel, $levels, true)) {
            return false;
        }

        return array_search($configuredLevel, $levels, true) >= array_search($diagnosticLevel, $levels, true);
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
     *     __featurevisorChildSticky?: array<string, mixed>
     * } $options
     * @return array
     */
    private function getEvaluationDependencies(array $context, array $options = []): array
    {
        $sticky = $options['__featurevisorChildSticky'] ?? $this->sticky;
        return array_merge($options, [
            'context' => $this->getContext($context),
            'logger' => $this->logger,
            'modulesManager' => $this->modulesManager,
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
     *     __featurevisorChildSticky?: array<string, mixed>
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

        return Evaluate::evaluateWithModules(array_merge($deps, [
            'type' => 'flag',
            'featureKey' => $featureKey
        ]));
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>
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
     *     flagEvaluation?: array<string, mixed>
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

        return Evaluate::evaluateWithModules(array_merge($deps, [
            'type' => 'variation',
            'featureKey' => $featureKey
        ]));
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>
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
     *     flagEvaluation?: array<string, mixed>
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

        return Evaluate::evaluateWithModules(array_merge($deps, [
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
     *     flagEvaluation?: array<string, mixed>
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
     *     flagEvaluation?: array<string, mixed>
     * } $options
     */
    public function getVariableBoolean(string $featureKey, string $variableKey, array $context = [], array $options = []): ?bool
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);
        return Helpers::getValueByType($value, 'boolean');
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>
     * } $options
     */
    public function getVariableString(string $featureKey, string $variableKey, array $context = [], array $options = []): ?string
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);
        return Helpers::getValueByType($value, 'string');
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>
     * } $options
     */
    public function getVariableInteger(string $featureKey, string $variableKey, array $context = [], array $options = []): ?int
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);
        return Helpers::getValueByType($value, 'integer');
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>
     * } $options
     */
    public function getVariableDouble(string $featureKey, string $variableKey, array $context = [], array $options = []): ?float
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);
        return Helpers::getValueByType($value, 'double');
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>
     * } $options
     */
    public function getVariableArray(string $featureKey, string $variableKey, array $context = [], array $options = []): ?array
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);
        return Helpers::getValueByType($value, 'array');
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>
     * } $options
     */
    public function getVariableObject(string $featureKey, string $variableKey, array $context = [], array $options = [])
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);
        return Helpers::getValueByType($value, 'object');
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     flagEvaluation?: array<string, mixed>
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
