<?php

namespace Featurevisor;

use Closure;

class Featurevisor
{
    private const DEFAULT_LOG_LEVEL = 'info';
    private const LOG_LEVELS = ['fatal', 'error', 'warn', 'info', 'debug'];
    private const EMPTY_DATAFILE = [
        'schemaVersion' => '2',
        'revision' => 'unknown',
        'segments' => [],
        'features' => [],
        'variables' => [],
    ];

    private array $context;
    private string $logLevel;
    private ?array $stickyFeatures;
    private ?array $stickyVariables;
    /** @var array<string, mixed> */
    private array $datafile;
    /** @var array<string, string> */
    private array $regexCache;
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
     *     logLevel?: string,
     *     context?: array<string, mixed>,
     *     sticky?: array<string, mixed>,
     *     modules?: array<array{
     *         name?: string,
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
        $this->logLevel = $this->validateLogLevel($options['logLevel'] ?? self::DEFAULT_LOG_LEVEL);
        $this->emitter = new Emitter();
        $this->context = $options['context'] ?? [];
        $this->stickyFeatures = $options['stickyFeatures'] ?? ($options['sticky'] ?? null);
        $this->stickyVariables = $options['stickyVariables'] ?? null;
        $this->onDiagnostic = $options['onDiagnostic'] ?? null;
        $this->moduleDiagnosticSubscriptions = [];
        $this->closed = false;
        $this->datafile = self::EMPTY_DATAFILE;
        $this->regexCache = [];
        $this->modulesManager = ModulesManager::createFromOptions([
            'modules' => $options['modules'] ?? [],
            'reportDiagnostic' => function(array $diagnostic, ?array $module = null): void {
                $this->reportDiagnostic($diagnostic, $module);
            },
            'moduleApiFactory' => function(array $module): array {
                return $this->createModuleApi($module);
            },
            'clearModuleDiagnosticSubscriptions' => function(array $module): void {
                $this->clearModuleDiagnosticSubscriptions($module);
            },
        ]);

        if (isset($options['datafile'])) {
            $this->setDatafile($options['datafile'], true);
        }

        $this->reportDiagnostic([
            'level' => 'info',
            'code' => 'sdk_initialized',
            'message' => 'SDK initialized',
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
            if (!is_array($incomingDatafile)
                || !is_string($incomingDatafile['schemaVersion'] ?? null)
                || !is_string($incomingDatafile['revision'] ?? null)
                || !is_array($incomingDatafile['segments'] ?? null)
                || !is_array($incomingDatafile['features'] ?? null)
                || (isset($incomingDatafile['variables']) && !is_array($incomingDatafile['variables']))) {
                throw new \InvalidArgumentException('Invalid datafile');
            }
            $storedDatafile = $replace
                ? $incomingDatafile
                : $this->mergeDatafiles($this->datafile, $incomingDatafile);
            $details = Events::getParamsForDatafileSetEvent($this->datafile, $storedDatafile, $replace);

            $this->datafile = $storedDatafile;
            $this->regexCache = [];

            $this->reportDiagnostic([
                'level' => 'info',
                'code' => 'datafile_set',
                'message' => 'Datafile set',
                'details' => $details,
            ]);
            $this->emitter->trigger('datafile_set', $details);
        } catch (\Throwable $e) {
            $this->reportDiagnostic([
                'level' => 'error',
                'code' => 'invalid_datafile',
                'message' => 'Could not parse datafile',
                'originalError' => $e,
                'details' => [],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $sticky
     */
    public function setSticky(array $sticky, bool $replace = false): void
    {
        $this->setStickyFeatures($sticky, $replace);
    }

    public function setStickyFeatures(array $sticky, bool $replace = false): void
    {
        if ($this->closed) {
            return;
        }

        $previousStickyFeatures = $this->stickyFeatures ?? [];

        if ($replace) {
            $this->stickyFeatures = $sticky;
        } else {
            $this->stickyFeatures = array_merge($this->stickyFeatures ?? [], $sticky);
        }

        $params = Events::getParamsForStickySetEvent($previousStickyFeatures, $this->stickyFeatures, $replace);

        $this->reportDiagnostic([
            'level' => 'info',
            'code' => 'sticky_set',
            'message' => 'Sticky features set',
            'details' => $params,
        ]);
        $this->emitter->trigger('sticky_set', $params);
        $this->emitter->trigger('sticky_features_set', $params);
    }

    public function setStickyVariables(array $sticky, bool $replace = false): void
    {
        if ($this->closed) return;
        $previous = $this->stickyVariables ?? [];
        $this->stickyVariables = $replace ? $sticky : array_merge($previous, $sticky);
        $params = Events::getParamsForStickyVariablesSetEvent($previous, $this->stickyVariables, $replace);
        $this->reportDiagnostic(['level' => 'info', 'code' => 'sticky_variables_set', 'message' => 'Sticky variables set', 'details' => $params]);
        $this->emitter->trigger('sticky_variables_set', $params);
    }

    public function getRevision(): string
    {
        return $this->datafile['revision'];
    }

    public function getSchemaVersion(): string
    {
        return $this->datafile['schemaVersion'];
    }

    public function getSegment(string $segmentKey): ?array
    {
        $segment = $this->datafile['segments'][$segmentKey] ?? null;
        if (!is_array($segment)) {
            return null;
        }

        $segment['conditions'] = Conditions::parseConditionsIfStringified(
            $segment['conditions'],
            function (array $diagnostic): void {
                $this->reportDiagnostic($diagnostic);
            }
        );

        return $segment;
    }

    public function getFeatureKeys(): array
    {
        return array_keys($this->datafile['features']);
    }

    public function getVariableKeys(?string $featureKey = null): array
    {
        if ($featureKey === null) return array_keys($this->datafile['variables'] ?? []);
        $feature = $this->getFeature($featureKey);

        return $feature ? array_keys($feature['variablesSchema'] ?? []) : [];
    }

    public function hasVariations(string $featureKey): bool
    {
        $feature = $this->getFeature($featureKey);

        return $feature && is_array($feature['variations'] ?? null) && $feature['variations'] !== [];
    }

    public function getFeature(string $featureKey): ?array
    {
        $feature = $this->datafile['features'][$featureKey] ?? null;

        return is_array($feature) ? $feature : null;
    }

    private function getGlobalVariable(string $variableKey): ?array
    {
        $variable = $this->datafile['variables'][$variableKey] ?? null;
        return is_array($variable) ? $variable : null;
    }

    public function setLogLevel(string $level): void
    {
        $this->logLevel = $this->validateLogLevel($level);
    }

    public function addModule(array $module): ?callable
    {
        if ($this->closed) {
            return null;
        }

        return $this->modulesManager->add($module);
    }

    public function removeModule(string $name): void
    {
        if ($this->closed) {
            return;
        }

        $this->modulesManager->remove($name);
    }

    public function on(string $eventName, callable $callback): callable
    {
        if ($this->closed) {
            return static function (): void {};
        }

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
    private function createModuleApi(array $module): array
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
                    'level' => $options['logLevel'] ?? self::DEFAULT_LOG_LEVEL,
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
    private function clearModuleDiagnosticSubscriptions(array $module): void
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
    private function reportDiagnostic(array $diagnostic, ?array $sourceModule = null): void
    {
        $diagnostic['level'] = $diagnostic['level'] ?? 'info';
        if ($sourceModule && isset($sourceModule['name'])) {
            $diagnostic['module'] = $sourceModule['name'];
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

        if ($this->levelAllows($diagnostic['level'], $this->logLevel)) {
            if ($this->onDiagnostic) {
                try {
                    ($this->onDiagnostic)($diagnostic);
                } catch (\Throwable $error) {
                    error_log('[Featurevisor] Diagnostic handler failed: '.$error->getMessage());
                }
            } else {
                $this->writeDiagnosticToConsole($diagnostic);
            }
        }

        if ($diagnostic['level'] === 'error') {
            $this->emitter->trigger('error', ['diagnostic' => $diagnostic]);
        }
    }

    /**
     * @param array<string, mixed> $previous
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    private function mergeDatafiles(array $previous, array $incoming): array
    {
        $merged = [
            'schemaVersion' => $incoming['schemaVersion'],
            'revision' => $incoming['revision'],
            'segments' => array_merge($previous['segments'] ?? [], $incoming['segments'] ?? []),
            'features' => array_merge($previous['features'] ?? [], $incoming['features'] ?? []),
            'variables' => array_merge($previous['variables'] ?? [], $incoming['variables'] ?? []),
        ];

        if (array_key_exists('featurevisorVersion', $incoming)) {
            $merged['featurevisorVersion'] = $incoming['featurevisorVersion'];
        }

        return $merged;
    }

    private function validateLogLevel(string $level): string
    {
        if (!in_array($level, self::LOG_LEVELS, true)) {
            throw new \InvalidArgumentException('Invalid log level');
        }

        return $level;
    }

    private function levelAllows(string $diagnosticLevel, string $configuredLevel): bool
    {
        if (!in_array($diagnosticLevel, self::LOG_LEVELS, true) || !in_array($configuredLevel, self::LOG_LEVELS, true)) {
            return false;
        }

        return array_search($configuredLevel, self::LOG_LEVELS, true) >= array_search($diagnosticLevel, self::LOG_LEVELS, true);
    }

    /** @param array<string, mixed> $diagnostic */
    private function writeDiagnosticToConsole(array $diagnostic): void
    {
        $message = '[Featurevisor] '.($diagnostic['message'] ?? ($diagnostic['code'] ?? 'diagnostic')).' '.
            json_encode($diagnostic, JSON_UNESCAPED_SLASHES);

        if (defined('STDOUT')) {
            fwrite(STDOUT, $message.PHP_EOL);
        } else {
            error_log($message);
        }
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
            'stickyFeatures' => $options['stickyFeatures'] ?? ($options['sticky'] ?? null),
            'stickyVariables' => $options['stickyVariables'] ?? null,
        ]);
    }

    private function getRegex(string $pattern, string $flags = ''): string
    {
        $cacheKey = $pattern.'-'.$flags;
        if (isset($this->regexCache[$cacheKey])) {
            return $this->regexCache[$cacheKey];
        }

        $pcreFlags = '';
        foreach (str_split($flags) as $flag) {
            if (strpos('imsu', $flag) !== false && strpos($pcreFlags, $flag) === false) {
                $pcreFlags .= $flag;
            } elseif ($flag !== 'g' && $flag !== 'y') {
                throw new \InvalidArgumentException('Invalid regular expression flag: '.$flag);
            }
        }

        $this->regexCache[$cacheKey] = '~'.str_replace('~', '\\~', $pattern).'~'.$pcreFlags;

        return $this->regexCache[$cacheKey];
    }

    /** @param mixed $conditions @param array<string, mixed> $context */
    private function allConditionsAreMatched($conditions, array $context): bool
    {
        return Conditions::allConditionsAreMatched(
            $conditions,
            $context,
            function (string $pattern, string $flags): string {
                return $this->getRegex($pattern, $flags);
            },
            function (array $diagnostic): void {
                $this->reportDiagnostic($diagnostic);
            }
        );
    }

    /** @param mixed $segments @param array<string, mixed> $context */
    private function allSegmentsAreMatched($segments, array $context): bool
    {
        return Conditions::allSegmentsAreMatched(
            $segments,
            $context,
            function (string $segmentKey): ?array {
                return $this->getSegment($segmentKey);
            },
            function (string $pattern, string $flags): string {
                return $this->getRegex($pattern, $flags);
            },
            function (array $diagnostic): void {
                $this->reportDiagnostic($diagnostic);
            }
        );
    }

    /** @param array<int, array<string, mixed>> $traffic @param array<string, mixed> $context */
    private function getMatchedTraffic(array $traffic, array $context): ?array
    {
        foreach ($traffic as $trafficItem) {
            if ($this->allSegmentsAreMatched(Conditions::parseSegmentsIfStringified($trafficItem['segments']), $context)) {
                return $trafficItem;
            }
        }

        return null;
    }

    private function getMatchedAllocation(array $traffic, int $bucketValue): ?array
    {
        foreach ($traffic['allocation'] ?? [] as $allocation) {
            [$start, $end] = $allocation['range'];
            if ($start <= $bucketValue && $end >= $bucketValue) {
                return $allocation;
            }
        }

        return null;
    }

    /** @param string|array<string, mixed> $featureKey @param array<string, mixed> $context */
    private function getMatchedForce($featureKey, array $context): array
    {
        $feature = is_string($featureKey) ? $this->getFeature($featureKey) : $featureKey;
        if (!$feature) {
            return [];
        }

        foreach ($feature['force'] ?? [] as $index => $force) {
            if (array_key_exists('conditions', $force) && $this->allConditionsAreMatched(
                Conditions::parseConditionsIfStringified(
                    $force['conditions'],
                    function (array $diagnostic): void {
                        $this->reportDiagnostic($diagnostic);
                    }
                ),
                $context
            )) {
                return ['force' => $force, 'forceIndex' => $index];
            }

            if (array_key_exists('segments', $force) && $this->allSegmentsAreMatched(
                Conditions::parseSegmentsIfStringified($force['segments']),
                $context
            )) {
                return ['force' => $force, 'forceIndex' => $index];
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     __featurevisorChildSticky?: array<string, mixed>
     * } $options
     * @return array
     */
    private function getEvaluationDependencies(array $context, array $options = []): array
    {
        $sticky = array_key_exists('__featurevisorChildStickyFeatures', $options)
            ? $options['__featurevisorChildStickyFeatures']
            : $this->stickyFeatures;
        $datafile = [
            'getFeature' => function (string $featureKey): ?array {
                return $this->getFeature($featureKey);
            },
            'allConditionsAreMatched' => function ($conditions, array $resolvedContext): bool {
                return $this->allConditionsAreMatched($conditions, $resolvedContext);
            },
            'allSegmentsAreMatched' => function ($segments, array $resolvedContext): bool {
                return $this->allSegmentsAreMatched($segments, $resolvedContext);
            },
            'getMatchedTraffic' => function (array $traffic, array $resolvedContext): ?array {
                return $this->getMatchedTraffic($traffic, $resolvedContext);
            },
            'getMatchedAllocation' => function (array $traffic, int $bucketValue): ?array {
                return $this->getMatchedAllocation($traffic, $bucketValue);
            },
            'getMatchedForce' => function ($featureKey, array $resolvedContext): array {
                return $this->getMatchedForce($featureKey, $resolvedContext);
            },
        ];

        $dependencies = [
            'context' => $this->getContext($context),
            'reportDiagnostic' => function (array $diagnostic): void {
                $this->reportDiagnostic($diagnostic);
            },
            'modulesManager' => $this->modulesManager,
            'datafile' => $datafile,
            'stickyFeatures' => $sticky,
            'sticky' => $sticky,
        ];

        if (array_key_exists('defaultVariationValue', $options)) {
            $dependencies['defaultVariationValue'] = $options['defaultVariationValue'];
        }
        if (array_key_exists('defaultVariableValue', $options)) {
            $dependencies['defaultVariableValue'] = $options['defaultVariableValue'];
        }

        return $dependencies;
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     *     __featurevisorChildSticky?: array<string, mixed>
     * } $options
     * @return array<string, mixed>
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
     * } $options
     */
    public function isEnabled(string $featureKey, array $context = [], array $options = []): bool
    {
        try {
            $evaluation = $this->evaluateFlag($featureKey, $context, $options);

            return ($evaluation['enabled'] ?? false) === true;
        } catch (\Throwable $error) {
            $this->reportDiagnostic([
                'level' => 'error',
                'code' => 'evaluation_error',
                'message' => 'isEnabled failed',
                'originalError' => $error,
                'details' => ['featureKey' => $featureKey],
            ]);

            return false;
        }
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     * } $options
     * @return array<string, mixed>
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
     * } $options
     * @return mixed|null
     */
    public function getVariation(string $featureKey, array $context = [], array $options = [])
    {
        try {
            $evaluation = $this->evaluateVariation($featureKey, $context, $options);

            if (array_key_exists('variationValue', $evaluation)) {
                return $evaluation['variationValue'];
            }

            if (isset($evaluation['variation']) && array_key_exists('value', $evaluation['variation'])) {
                return $evaluation['variation']['value'];
            }

            return null;
        } catch (\Throwable $e) {
            $this->reportDiagnostic([
                'level' => 'error',
                'code' => 'evaluation_error',
                'message' => 'getVariation failed',
                'originalError' => $e,
                'details' => [
                    'action' => 'getVariation',
                    'featureKey' => $featureKey,
                ],
            ]);
            return null;
        }
    }

    /**
     * @param string|array<string, mixed>|null $variableKeyOrContext
     * @param array<string, mixed> $contextOrOptions
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     * } $options
     * @return array<string, mixed>
     */
    public function evaluateVariable(string $featureOrVariableKey, string|array|null $variableKeyOrContext = null, array $contextOrOptions = [], array $options = []): array
    {
        if (!is_string($variableKeyOrContext)) {
            return $this->evaluateGlobalVariable($featureOrVariableKey, $variableKeyOrContext ?? [], $contextOrOptions);
        }
        $deps = $this->getEvaluationDependencies($contextOrOptions, $options);

        return Evaluate::evaluateWithModules(array_merge($deps, [
            'type' => 'variable',
            'featureKey' => $featureOrVariableKey,
            'variableKey' => $variableKeyOrContext
        ]));
    }

    /** @param mixed $requirements */
    private function requiredFeaturesAreMatched($requirements, array $context, array $options): bool
    {
        if ($requirements === null) return true;
        $isList = is_array($requirements) && ($requirements === [] || array_keys($requirements) === range(0, count($requirements) - 1));
        $items = $isList ? $requirements : [$requirements];
        unset($options['defaultVariationValue'], $options['defaultVariableValue']);
        foreach ($items as $required) {
            $key = is_string($required) ? $required : $required['feature'];
            $enabled = is_string($required) ? true : ($required['enabled'] ?? true);
            $variation = is_string($required) ? null : ($required['variation'] ?? null);
            if ($this->isEnabled($key, $context, $options) !== $enabled) return false;
            if ($variation !== null && $this->getVariation($key, $context, $options) !== $variation) return false;
        }
        return true;
    }

    private function evaluateGlobalVariable(string $variableKey, array $context = [], array $options = []): array
    {
        $evaluationOptions = ['type' => 'variable', 'variableKey' => $variableKey, 'context' => $this->getContext($context)];
        if (array_key_exists('defaultVariableValue', $options)) $evaluationOptions['defaultVariableValue'] = $options['defaultVariableValue'];
        try {
            $evaluationOptions = $this->modulesManager->runBeforeEvaluationModules($evaluationOptions);
            $resolvedKey = $evaluationOptions['variableKey'];
            $resolvedContext = $evaluationOptions['context'];
            $variable = $this->getGlobalVariable($resolvedKey);
            $evaluation = ['type' => 'variable', 'variableKey' => $resolvedKey, 'reason' => Evaluation::VARIABLE_NOT_FOUND];
            $sticky = $options['__featurevisorChildStickyVariables'] ?? ($this->stickyVariables ?? []);

            if (array_key_exists($resolvedKey, $sticky)) {
                $evaluation = array_merge($evaluation, ['reason' => Evaluation::STICKY, 'variable' => $variable, 'variableValue' => $sticky[$resolvedKey]]);
            } elseif ($variable) {
                if (!$this->requiredFeaturesAreMatched($variable['requiredFeatures'] ?? null, $resolvedContext, $options)) {
                    $evaluation = array_merge($evaluation, ['reason' => 'required_features_unmet', 'variable' => $variable]);
                    $valueKey = !empty($variable['useDefaultWhenDisabled']) ? 'defaultValue' : 'disabledValue';
                    if (array_key_exists($valueKey, $variable)) $evaluation['variableValue'] = $variable[$valueKey];
                } else {
                    foreach ($variable['overrides'] ?? [] as $index => $override) {
                        if (!$this->requiredFeaturesAreMatched($override['requiredFeatures'] ?? null, $resolvedContext, $options)) continue;
                        $conditionsMatch = !isset($override['conditions']) || $this->allConditionsAreMatched(
                            Conditions::parseConditionsIfStringified($override['conditions'], fn(array $d) => $this->reportDiagnostic($d)),
                            $resolvedContext
                        );
                        $segmentsMatch = !isset($override['segments']) || $this->allSegmentsAreMatched(
                            Conditions::parseSegmentsIfStringified($override['segments']), $resolvedContext
                        );
                        if ($conditionsMatch && $segmentsMatch) {
                            $evaluation = array_merge($evaluation, [
                                'reason' => Evaluation::VARIABLE_OVERRIDE_RULE,
                                'variable' => $variable,
                                'variableValue' => $override['value'] ?? null,
                                'variableOverrideIndex' => $index,
                            ]);
                            if (isset($override['key'])) $evaluation['variableOverrideKey'] = $override['key'];
                            if (isset($override['keyPath'])) $evaluation['variableOverridePath'] = $override['keyPath'];
                            break;
                        }
                    }
                    if ($evaluation['reason'] === Evaluation::VARIABLE_NOT_FOUND) {
                        $evaluation = array_merge($evaluation, ['reason' => Evaluation::VARIABLE_DEFAULT, 'variable' => $variable]);
                        if (array_key_exists('defaultValue', $variable)) $evaluation['variableValue'] = $variable['defaultValue'];
                    }
                }
                if (!empty($variable['deprecated'])) {
                    $this->reportDiagnostic(['level' => 'warn', 'code' => 'variable_deprecated', 'message' => 'Variable "'.$resolvedKey.'" is deprecated', 'details' => ['variableKey' => $resolvedKey, 'evaluation' => $evaluation]]);
                }
            }
            if (!array_key_exists('variableValue', $evaluation) && array_key_exists('defaultVariableValue', $options)) {
                $evaluation['variableValue'] = $options['defaultVariableValue'];
            }
            $evaluation = $this->modulesManager->runAfterEvaluationModules($evaluation, $evaluationOptions);
            $this->reportDiagnostic(['level' => 'debug', 'code' => $evaluation['reason'], 'message' => 'Global variable evaluated', 'details' => $evaluation]);
            return $evaluation;
        } catch (\Throwable $error) {
            $evaluation = ['type' => 'variable', 'variableKey' => $variableKey, 'reason' => Evaluation::ERROR, 'error' => $error];
            $this->reportDiagnostic(['level' => 'error', 'code' => 'evaluation_error', 'message' => 'Global variable evaluation failed', 'originalError' => $error, 'details' => $evaluation]);
            return $evaluation;
        }
    }

    /**
     * @param string|array<string, mixed>|null $variableKeyOrContext
     * @param array<string, mixed> $contextOrOptions
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     * } $options
     * @return mixed|null
     */
    public function getVariable(string $featureOrVariableKey, string|array|null $variableKeyOrContext = null, array $contextOrOptions = [], array $options = [])
    {
        try {
            $evaluation = $this->evaluateVariable($featureOrVariableKey, $variableKeyOrContext, $contextOrOptions, $options);

            if (array_key_exists('variableValue', $evaluation)) {
                if (
                    (($evaluation['variableSchema']['type'] ?? null) === 'json' || ($evaluation['variable']['type'] ?? null) === 'json') &&
                    is_string($evaluation['variableValue'])
                ) {
                    return json_decode($evaluation['variableValue'], true, 512, JSON_THROW_ON_ERROR);
                }
                return $evaluation['variableValue'];
            }
            return null;
        } catch (\Throwable $e) {
            $this->reportDiagnostic([
                'level' => 'error',
                'code' => 'evaluation_error',
                'message' => 'getVariable failed',
                'originalError' => $e,
                'details' => [
                    'action' => 'getVariable',
                    'variableKey' => $featureOrVariableKey,
                ],
            ]);
            return null;
        }
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     * } $options
     */
    public function getVariableBoolean(string $featureKey, string|array|null $variableKey = null, array $context = [], array $options = []): ?bool
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);
        return Helpers::getValueByType($value, 'boolean');
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     * } $options
     */
    public function getVariableString(string $featureKey, string|array|null $variableKey = null, array $context = [], array $options = []): ?string
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);
        return Helpers::getValueByType($value, 'string');
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     * } $options
     */
    public function getVariableInteger(string $featureKey, string|array|null $variableKey = null, array $context = [], array $options = []): ?int
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);
        return Helpers::getValueByType($value, 'integer');
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     * } $options
     */
    public function getVariableDouble(string $featureKey, string|array|null $variableKey = null, array $context = [], array $options = []): ?float
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);
        return Helpers::getValueByType($value, 'double');
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     * } $options
     */
    public function getVariableArray(string $featureKey, string|array|null $variableKey = null, array $context = [], array $options = []): ?array
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);
        return Helpers::getValueByType($value, 'array');
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     * } $options
     */
    public function getVariableObject(string $featureKey, string|array|null $variableKey = null, array $context = [], array $options = [])
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);
        return Helpers::getValueByType($value, 'object');
    }

    /**
     * @param array<string, mixed> $context
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     * } $options
     * @return array<mixed>|mixed|null
     */
    public function getVariableJSON(string $featureKey, string|array|null $variableKey = null, array $context = [], array $options = [])
    {
        $value = $this->getVariable($featureKey, $variableKey, $context, $options);

        return $value;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string> $featureKeys
     * @param array{
     *     defaultVariationValue?: mixed,
     *     defaultVariableValue?: mixed,
     * } $options
     * @return array<string, mixed>
     */
    public function getFeatureEvaluations(array $context = [], array $featureKeys = [], array $options = []): array
    {
        $evaluations = [];
        if (empty($featureKeys)) {
            $featureKeys = $this->getFeatureKeys();
        }
        foreach ($featureKeys as $featureKey) {
            // isEnabled
            $flagEvaluation = $this->evaluateFlag($featureKey, $context, $options);
            $evaluatedFeature = [
                'enabled' => isset($flagEvaluation['enabled']) ? $flagEvaluation['enabled'] === true : false,
            ];
            // variation
            if ($this->hasVariations($featureKey)) {
                $variation = $this->getVariation($featureKey, $context, $options);
                if ($variation !== null) {
                    $evaluatedFeature['variation'] = $variation;
                }
            }
            // variables
            $variableKeys = $this->getVariableKeys($featureKey);
            if (!empty($variableKeys)) {
                $evaluatedFeature['variables'] = [];
                foreach ($variableKeys as $variableKey) {
                    $evaluatedFeature['variables'][$variableKey] = $this->getVariable($featureKey, $variableKey, $context, $options);
                }
            }
            $evaluations[$featureKey] = $evaluatedFeature;
        }
        return $evaluations;
    }

    public function getVariableEvaluations(array $context = [], array $variableKeys = [], array $options = []): array
    {
        if ($variableKeys === []) $variableKeys = $this->getVariableKeys();
        $evaluations = [];
        foreach ($variableKeys as $variableKey) {
            $evaluations[$variableKey] = $this->getVariable($variableKey, $context, $options);
        }
        return $evaluations;
    }

    /** @deprecated Use getFeatureEvaluations(). */
    public function getAllEvaluations(array $context = [], array $featureKeys = [], array $options = []): array
    {
        return $this->getFeatureEvaluations($context, $featureKeys, $options);
    }
}
