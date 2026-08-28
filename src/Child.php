<?php

namespace Featurevisor;

class Child
{
    private Featurevisor $parent;
    private array $context;
    private array $stickyFeatures;
    private array $stickyVariables;
    private Emitter $emitter;
    /** @var array<int, callable> */
    private array $parentUnsubscribers = [];

    public function __construct(array $options)
    {
        $this->parent = $options['parent'];
        $this->context = $options['context'];
        $this->stickyFeatures = $options['stickyFeatures'] ?? ($options['sticky'] ?? []);
        $this->stickyVariables = $options['stickyVariables'] ?? [];
        $this->emitter = new Emitter();
    }

    public function on(string $eventName, callable $callback): callable
    {
        if (in_array($eventName, ['context_set', 'sticky_set', 'sticky_features_set', 'sticky_variables_set'], true)) {
            return $this->emitter->on($eventName, $callback);
        }
        $parentUnsubscribe = $this->parent->on($eventName, $callback);
        $active = true;
        $unsubscribe = null;
        $unsubscribe = function () use (&$active, &$unsubscribe, $parentUnsubscribe): void {
            if (!$active) return;
            $active = false;
            $parentUnsubscribe();
            foreach ($this->parentUnsubscribers as $index => $candidate) {
                if ($candidate === $unsubscribe) unset($this->parentUnsubscribers[$index]);
            }
        };
        $this->parentUnsubscribers[] = $unsubscribe;
        return $unsubscribe;
    }

    public function close(): void
    {
        foreach (array_values($this->parentUnsubscribers) as $unsubscribe) $unsubscribe();
        $this->parentUnsubscribers = [];
        $this->emitter->clearAll();
    }

    public function setContext(array $context, bool $replace = false): void
    {
        $this->context = $replace ? $context : array_merge($this->context, $context);
        $this->emitter->trigger('context_set', ['context' => $this->context, 'replaced' => $replace]);
    }

    public function getContext(array $context = []): array
    {
        return $this->parent->getContext(array_merge($this->context, $context));
    }

    public function setSticky(array $sticky, bool $replace = false): void { $this->setStickyFeatures($sticky, $replace); }
    public function setStickyFeatures(array $sticky, bool $replace = false): void
    {
        $previous = $this->stickyFeatures;
        $this->stickyFeatures = $replace ? $sticky : array_merge($this->stickyFeatures, $sticky);
        $params = Events::getParamsForStickySetEvent($previous, $this->stickyFeatures, $replace);
        $this->emitter->trigger('sticky_set', $params);
        $this->emitter->trigger('sticky_features_set', $params);
    }
    public function setStickyVariables(array $sticky, bool $replace = false): void
    {
        $previous = $this->stickyVariables;
        $this->stickyVariables = $replace ? $sticky : array_merge($this->stickyVariables, $sticky);
        $this->emitter->trigger('sticky_variables_set', Events::getParamsForStickyVariablesSetEvent($previous, $this->stickyVariables, $replace));
    }

    private function context(array $context): array { return array_merge($this->context, $context); }
    private function options(array $options): array
    {
        return array_merge($options, [
            '__featurevisorChildStickyFeatures' => $this->stickyFeatures,
            '__featurevisorChildStickyVariables' => $this->stickyVariables,
        ]);
    }

    public function isEnabled(string $key, array $context = [], array $options = []): bool { return $this->parent->isEnabled($key, $this->context($context), $this->options($options)); }
    public function evaluateFlag(string $key, array $context = [], array $options = []): array { return $this->parent->evaluateFlag($key, $this->context($context), $this->options($options)); }
    public function getVariation(string $key, array $context = [], array $options = []) { return $this->parent->getVariation($key, $this->context($context), $this->options($options)); }
    public function evaluateVariation(string $key, array $context = [], array $options = []): array { return $this->parent->evaluateVariation($key, $this->context($context), $this->options($options)); }

    private function variableArguments(string $first, string|array|null $second, array $third, array $fourth): array
    {
        if (is_string($second)) return [$first, $second, $this->context($third), $this->options($fourth)];
        return [$first, $this->context($second ?? []), $this->options($third)];
    }
    public function getVariable(string $first, string|array|null $second = null, array $third = [], array $fourth = []) { return $this->parent->getVariable(...$this->variableArguments($first, $second, $third, $fourth)); }
    public function evaluateVariable(string $first, string|array|null $second = null, array $third = [], array $fourth = []): array { return $this->parent->evaluateVariable(...$this->variableArguments($first, $second, $third, $fourth)); }
    public function getVariableBoolean(string $first, string|array|null $second = null, array $third = [], array $fourth = []): ?bool { return $this->parent->getVariableBoolean(...$this->variableArguments($first, $second, $third, $fourth)); }
    public function getVariableString(string $first, string|array|null $second = null, array $third = [], array $fourth = []): ?string { return $this->parent->getVariableString(...$this->variableArguments($first, $second, $third, $fourth)); }
    public function getVariableInteger(string $first, string|array|null $second = null, array $third = [], array $fourth = []): ?int { return $this->parent->getVariableInteger(...$this->variableArguments($first, $second, $third, $fourth)); }
    public function getVariableDouble(string $first, string|array|null $second = null, array $third = [], array $fourth = []): ?float { return $this->parent->getVariableDouble(...$this->variableArguments($first, $second, $third, $fourth)); }
    public function getVariableArray(string $first, string|array|null $second = null, array $third = [], array $fourth = []): ?array { return $this->parent->getVariableArray(...$this->variableArguments($first, $second, $third, $fourth)); }
    public function getVariableObject(string $first, string|array|null $second = null, array $third = [], array $fourth = []) { return $this->parent->getVariableObject(...$this->variableArguments($first, $second, $third, $fourth)); }
    public function getVariableJSON(string $first, string|array|null $second = null, array $third = [], array $fourth = []) { return $this->parent->getVariableJSON(...$this->variableArguments($first, $second, $third, $fourth)); }

    public function getFeatureEvaluations(array $context = [], array $keys = [], array $options = []): array { return $this->parent->getFeatureEvaluations($this->context($context), $keys, $this->options($options)); }
    public function getVariableEvaluations(array $context = [], array $keys = [], array $options = []): array { return $this->parent->getVariableEvaluations($this->context($context), $keys, $this->options($options)); }
    public function getAllEvaluations(array $context = [], array $keys = [], array $options = []): array { return $this->getFeatureEvaluations($context, $keys, $options); }
}
