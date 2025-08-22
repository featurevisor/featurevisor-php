<?php

namespace Featurevisor;

use Closure;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class HooksManager
{
    private array $hooks = [];
    private LoggerInterface $logger;

    /**
     * @param array{
     *     hooks?: array<array{
     *         name: string,
     *         before: Closure,
     *         after: Closure,
     *         bucketKey: Closure,
     *         bucketValue: Closure
     *     }>,
     *     logger?: LoggerInterface,
     * } $options
     * @return self
     */
    public static function createFromOptions(array $options): self
    {
        return new self(
            $options['hooks'] ?? [],
            $options['logger'] ?? new NullLogger()
        );
    }

    /**
     * @param array<array{
     *     name: string,
     *     before: Closure,
     *     after: Closure,
     *     bucketKey: Closure,
     *     bucketValue: Closure
     * }> $hooks
     */
    public function __construct(array $hooks, LoggerInterface $logger)
    {
        $this->logger = $logger;
        foreach ($hooks as $hook) {
            $this->add($hook);
        }
    }

    /**
     * @param array{
     *      name: string,
     *      before: Closure,
     *      after: Closure,
     *      bucketKey: Closure,
     *      bucketValue: Closure
     *  } $hook
     * @return callable|null
     */
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
