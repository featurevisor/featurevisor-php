<?php

namespace Featurevisor;

class HooksManager
{
    private array $hooks = [];
    private Logger $logger;

    public function __construct(array $options)
    {
        $this->logger = $options['logger'];

        if (isset($options['hooks'])) {
            foreach ($options['hooks'] as $hook) {
                $this->add($hook);
            }
        }
    }

    public function add(array $hook): ?callable
    {
        foreach ($this->hooks as $existingHook) {
            if ($existingHook['name'] === $hook['name']) {
                $this->logger->error("Hook with name \"{$hook['name']}\" already exists.", [
                    'name' => $hook['name'],
                    'hook' => $hook
                ]);
                return null;
            }
        }

        $this->hooks[] = $hook;

        return function() use ($hook) {
            $this->remove($hook['name']);
        };
    }

    public function remove(string $name): void
    {
        $this->hooks = array_filter($this->hooks, function($hook) use ($name) {
            return $hook['name'] !== $name;
        });
    }

    public function getAll(): array
    {
        return $this->hooks;
    }
}
