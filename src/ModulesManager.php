<?php

namespace Featurevisor;

class ModulesManager
{
    private array $modules = [];
    /** @var callable|null */
    private $reportDiagnostic;
    /** @var callable|null */
    private $moduleApiFactory;
    /** @var callable|null */
    private $clearModuleDiagnosticSubscriptions;

    /**
     * @param array<string, mixed> $options
     * @return self
     */
    public static function createFromOptions(array $options): self
    {
        return new self(
            $options['modules'] ?? [],
            $options['reportDiagnostic'] ?? null,
            $options['moduleApiFactory'] ?? null,
            $options['clearModuleDiagnosticSubscriptions'] ?? null
        );
    }

    /**
     * @param array<int, array<string, mixed>> $modules
     * @param callable|null $reportDiagnostic
     * @param callable|null $moduleApiFactory
     * @param callable|null $clearModuleDiagnosticSubscriptions
     */
    public function __construct(
        array $modules = [],
        ?callable $reportDiagnostic = null,
        ?callable $moduleApiFactory = null,
        ?callable $clearModuleDiagnosticSubscriptions = null
    )
    {
        $this->reportDiagnostic = $reportDiagnostic;
        $this->moduleApiFactory = $moduleApiFactory;
        $this->clearModuleDiagnosticSubscriptions = $clearModuleDiagnosticSubscriptions;

        foreach ($modules as $module) {
            $this->add($module);
        }
    }

    /**
     * @param array<string, mixed> $module
     * @return callable|null
     */
    public function add(array $module): ?callable
    {
        if (!isset($module['id'])) {
            $module['id'] = uniqid('module_', true);
        }

        if (isset($module['name']) && $module['name'] !== '') {
            foreach ($this->modules as $existingModule) {
                if (($existingModule['name'] ?? null) === $module['name']) {
                    $this->report([
                        'level' => 'error',
                        'code' => 'duplicate_module',
                        'message' => 'Duplicate module name',
                        'moduleName' => $module['name'],
                    ], $module);
                    return null;
                }
            }
        }

        if (isset($module['setup']) && is_callable($module['setup']) && $this->moduleApiFactory) {
            try {
                $api = ($this->moduleApiFactory)($module);
                $module['setup']($api);
            } catch (\Throwable $error) {
                if ($this->clearModuleDiagnosticSubscriptions) {
                    ($this->clearModuleDiagnosticSubscriptions)($module);
                }
                $this->report([
                    'level' => 'error',
                    'code' => 'module_setup_error',
                    'message' => 'Module setup failed',
                    'moduleName' => $module['name'] ?? null,
                    'originalError' => $error,
                ], null);
                $this->closeModule($module);
                return null;
            }
        }

        $this->modules[] = $module;

        return function() use ($module) {
            $this->remove($module);
        };
    }

    /**
     * @param string|array<string, mixed> $nameOrModule
     */
    public function remove($nameOrModule): void
    {
        $remainingModules = [];
        $removedModules = [];

        foreach ($this->modules as $module) {
            $matches = is_array($nameOrModule)
                ? (($module['id'] ?? null) === ($nameOrModule['id'] ?? null))
                : (($module['name'] ?? null) === $nameOrModule);

            if ($matches) {
                $removedModules[] = $module;
            } else {
                $remainingModules[] = $module;
            }
        }

        $this->modules = $remainingModules;

        foreach ($removedModules as $module) {
            if ($this->clearModuleDiagnosticSubscriptions) {
                ($this->clearModuleDiagnosticSubscriptions)($module);
            }
            $this->closeModule($module);
        }
    }

    public function getAll(): array
    {
        return $this->modules;
    }

    public function runBeforeModules(array $options): array
    {
        foreach ($this->modules as $module) {
            if (isset($module['before']) && is_callable($module['before'])) {
                $options = $module['before']($options);
            }
        }

        return $options;
    }

    public function runBucketKeyModules(array $options): string
    {
        $bucketKey = $options['bucketKey'];

        foreach ($this->modules as $module) {
            if (isset($module['bucketKey']) && is_callable($module['bucketKey'])) {
                $bucketKey = $module['bucketKey'](array_merge($options, [
                    'bucketKey' => $bucketKey,
                ]));
            }
        }

        return $bucketKey;
    }

    public function runBucketValueModules(array $options): int
    {
        $bucketValue = $options['bucketValue'];

        foreach ($this->modules as $module) {
            if (isset($module['bucketValue']) && is_callable($module['bucketValue'])) {
                $bucketValue = $module['bucketValue'](array_merge($options, [
                    'bucketValue' => $bucketValue,
                ]));
            }
        }

        return $bucketValue;
    }

    public function runAfterModules(array $evaluation, array $options): array
    {
        foreach ($this->modules as $module) {
            if (isset($module['after']) && is_callable($module['after'])) {
                $evaluation = $module['after']($evaluation, $options);
            }
        }

        return $evaluation;
    }

    public function closeAll(): void
    {
        foreach ($this->modules as $module) {
            if ($this->clearModuleDiagnosticSubscriptions) {
                ($this->clearModuleDiagnosticSubscriptions)($module);
            }
            $this->closeModule($module);
        }

        $this->modules = [];
    }

    /**
     * @param array<string, mixed> $module
     */
    private function closeModule(array $module): void
    {
        if (!isset($module['close']) || !is_callable($module['close'])) {
            return;
        }

        try {
            $module['close']();
        } catch (\Throwable $error) {
            $this->report([
                'level' => 'error',
                'code' => 'module_close_error',
                'message' => 'Module close failed',
                'moduleName' => $module['name'] ?? null,
                'originalError' => $error,
            ], null);
        }
    }

    private function report(array $diagnostic, ?array $module = null): void
    {
        if ($this->reportDiagnostic) {
            ($this->reportDiagnostic)($diagnostic, $module);
        }
    }
}
