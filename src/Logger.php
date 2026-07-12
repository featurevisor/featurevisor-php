<?php

namespace Featurevisor;

use Closure;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Stringable;

/** @internal SDK infrastructure. Use diagnostics through Featurevisor instead. */
final class Logger implements LoggerInterface
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
    public static function create(array $options = []): self
    {
        return new self(
            $options['level'] ?? self::DEFAULT_LEVEL,
            $options['handler'] ?? null
        );
    }

    public function __construct(string $level = self::DEFAULT_LEVEL, ?Closure $handler = null)
    {
        $this->handler = $handler ?? static fn ($level, $message, array $context) => self::defaultLogHandler($level, $message, $context);
        $this->setLevel($level);
    }

    public function setLevel(string $level): void
    {
        $level = self::normalizeLevel($level);
        if (!in_array($level, self::ALL_LEVELS, true)) {
            throw new InvalidArgumentException('Invalid log level');
        }

        $this->level = $level;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function log($level, $message, array $context = []): void
    {
        $level = self::normalizeLevel((string) $level);

        if (!in_array($level, self::ALL_LEVELS, true)) {
            throw new InvalidArgumentException('Invalid log level');
        }

        $shouldHandle = array_search($this->level, self::ALL_LEVELS, true) >= array_search($level, self::ALL_LEVELS, true);

        if (!$shouldHandle) {
            return;
        }

        ($this->handler)($level, self::MSG_PREFIX.' '.$message, $context);
    }

    private static function normalizeLevel(string $level): string
    {
        if ($level === 'fatal') {
            return LogLevel::EMERGENCY;
        }
        if ($level === 'warn') {
            return LogLevel::WARNING;
        }
        return $level;
    }

    private static function defaultLogHandler($level, $message, ?array $details = null): void
    {
        if (STDOUT == false) {
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
