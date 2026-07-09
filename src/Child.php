<?php

namespace Featurevisor;

class Child
{
    private $parent;
    private array $context;
    private array $sticky;
    private Emitter $emitter;

    public function __construct(array $options)
    {
        $this->parent = $options['parent'];
        $this->context = $options['context'];
        $this->sticky = $options['sticky'] ?? [];
        $this->emitter = new Emitter();
    }

    public function on(string $eventName, callable $callback): callable
    {
        if ($eventName === 'context_set' || $eventName === 'sticky_set') {
            return $this->emitter->on($eventName, $callback);
        }

        return $this->parent->on($eventName, $callback);
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
    }

    public function getContext(array $context = []): array
    {
        return $this->parent->getContext(array_merge($this->context, $context));
    }

    public function setSticky(array $sticky, bool $replace = false): void
    {
        $previousStickyFeatures = $this->sticky;

        if ($replace) {
            $this->sticky = $sticky;
        } else {
            $this->sticky = array_merge($this->sticky, $sticky);
        }

        $params = Events::getParamsForStickySetEvent($previousStickyFeatures, $this->sticky, $replace);

        $this->emitter->trigger('sticky_set', $params);
    }

    public function isEnabled(string $featureKey, array $context = [], array $options = []): bool
    {
        return $this->parent->isEnabled(
            $featureKey,
            array_merge($this->context, $context),
            array_merge($options, ['__featurevisorChildSticky' => $this->sticky])
        );
    }

    public function getVariation(string $featureKey, array $context = [], array $options = [])
    {
        return $this->parent->getVariation(
            $featureKey,
            array_merge($this->context, $context),
            array_merge($options, ['__featurevisorChildSticky' => $this->sticky])
        );
    }

    public function getVariable(string $featureKey, string $variableKey, array $context = [], array $options = [])
    {
        return $this->parent->getVariable(
            $featureKey,
            $variableKey,
            array_merge($this->context, $context),
            array_merge($options, ['__featurevisorChildSticky' => $this->sticky])
        );
    }

    public function getVariableBoolean(string $featureKey, string $variableKey, array $context = [], array $options = []): ?bool
    {
        return $this->parent->getVariableBoolean(
            $featureKey,
            $variableKey,
            array_merge($this->context, $context),
            array_merge($options, ['__featurevisorChildSticky' => $this->sticky])
        );
    }

    public function getVariableString(string $featureKey, string $variableKey, array $context = [], array $options = []): ?string
    {
        return $this->parent->getVariableString(
            $featureKey,
            $variableKey,
            array_merge($this->context, $context),
            array_merge($options, ['__featurevisorChildSticky' => $this->sticky])
        );
    }

    public function getVariableInteger(string $featureKey, string $variableKey, array $context = [], array $options = []): ?int
    {
        return $this->parent->getVariableInteger(
            $featureKey,
            $variableKey,
            array_merge($this->context, $context),
            array_merge($options, ['__featurevisorChildSticky' => $this->sticky])
        );
    }

    public function getVariableDouble(string $featureKey, string $variableKey, array $context = [], array $options = []): ?float
    {
        return $this->parent->getVariableDouble(
            $featureKey,
            $variableKey,
            array_merge($this->context, $context),
            array_merge($options, ['__featurevisorChildSticky' => $this->sticky])
        );
    }

    public function getVariableArray(string $featureKey, string $variableKey, array $context = [], array $options = []): ?array
    {
        return $this->parent->getVariableArray(
            $featureKey,
            $variableKey,
            array_merge($this->context, $context),
            array_merge($options, ['__featurevisorChildSticky' => $this->sticky])
        );
    }

    public function getVariableObject(string $featureKey, string $variableKey, array $context = [], array $options = [])
    {
        return $this->parent->getVariableObject(
            $featureKey,
            $variableKey,
            array_merge($this->context, $context),
            array_merge($options, ['__featurevisorChildSticky' => $this->sticky])
        );
    }

    public function getVariableJSON(string $featureKey, string $variableKey, array $context = [], array $options = [])
    {
        return $this->parent->getVariableJSON(
            $featureKey,
            $variableKey,
            array_merge($this->context, $context),
            array_merge($options, ['__featurevisorChildSticky' => $this->sticky])
        );
    }

    public function getAllEvaluations(array $context = [], array $featureKeys = [], array $options = []): array
    {
        return $this->parent->getAllEvaluations(
            array_merge($this->context, $context),
            $featureKeys,
            array_merge($options, ['__featurevisorChildSticky' => $this->sticky])
        );
    }
}
