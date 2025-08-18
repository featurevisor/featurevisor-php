<?php

namespace Featurevisor;

use Closure;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Stringable;

class Logger implements LoggerInterface
{
    use LoggerTrait;
    private const MSG_PREFIX = '[Featurevisor]';

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
        $this->handler = $options['handler'] ?? static fn ($level, $message, array $context) => self::defaultLogHandler($level, $message, $context);
    }

    public function setLevel(string $level): void
    {
        if (!in_array($level, self::ALL_LEVELS, true)) {
            throw new InvalidArgumentException('Invalid log level');
        }

        $this->level = $level;
    }

    public function log($level, $message, array $context = []): void
    {
        $shouldHandle = array_search($this->level, self::ALL_LEVELS) >= array_search($level, self::ALL_LEVELS);

        if (!$shouldHandle) {
            return;
        }

        ($this->handler)($level, self::MSG_PREFIX.' '.$message, $context);
    }

    public static function defaultLogHandler($level, $message, ?array $details = null): void
    {
        if (STDOUT === false) {
            return;
        }

        fwrite(
            STDOUT,
            sprintf(
                '%s %s',
                $message,
                $details !== null ? json_encode($details, JSON_THROW_ON_ERROR) : null
            ) . PHP_EOL
        );
    }
}
