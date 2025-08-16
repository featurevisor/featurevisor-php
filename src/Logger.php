<?php

namespace Featurevisor;

use Closure;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

class Logger implements LoggerInterface
{
    use LoggerTrait;

    private const ALL_LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    public const DEFAULT_LEVEL = LogLevel::INFO;

    private string $level;
    private Closure $handler;

    /**
     * @param array{
     *     level?: string,
     *     handler?: Closure,
     * } $options
     */
    public function __construct(array $options = [])
    {
        $this->level = $options['level'] ?? self::DEFAULT_LEVEL;
        $this->handler = $options['handler'] ?? self::defaultLogHandler(...);
    }

    public function setLevel(string $level): void
    {
        $this->level = $level;
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $shouldHandle = array_search($this->level, self::ALL_LEVELS) >= array_search($level, self::ALL_LEVELS);

        if (!$shouldHandle) {
            return;
        }

        ($this->handler)($level, $message, $context);
    }

    public static function defaultLogHandler(string $level, string $message, $details = null): void
    {
        $prefix = '[Featurevisor]';
        echo "$prefix $message";
        if (!is_null($details)) {
            echo ' ' . json_encode($details);
        }
        echo PHP_EOL;
    }
}
