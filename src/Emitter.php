<?php

namespace Featurevisor;

class Emitter
{
    public array $listeners;

    public function __construct()
    {
        $this->listeners = [];
    }

    public function on(string $eventName, callable $callback): callable
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }

        $listeners = &$this->listeners[$eventName];
        $listeners[] = $callback;

        $isActive = true;

        return function() use (&$listeners, $callback, &$isActive) {
            if (!$isActive) {
                return;
            }

            $isActive = false;

            $index = array_search($callback, $listeners, true);
            if ($index !== false) {
                array_splice($listeners, $index, 1);
            }
        };
    }

    public function trigger(string $eventName, array $details = []): void
    {
        $listeners = $this->listeners[$eventName] ?? null;

        if (!$listeners) {
            return;
        }

        foreach ($listeners as $listener) {
            try {
                $listener($details);
            } catch (\Exception $err) {
                error_log($err->getMessage());
            }
        }
    }

    public function clearAll(): void
    {
        $this->listeners = [];
    }
}
