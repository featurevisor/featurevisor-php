<?php

namespace Featurevisor;

class Logger
{
    public const ALL_LEVELS = ['fatal', 'error', 'warn', 'info', 'debug'];
    public const DEFAULT_LEVEL = 'info';

    private string $level;
    private $handler;

    public function __construct(array $options = [])
    {
        $this->level = $options['level'] ?? self::DEFAULT_LEVEL;
        $this->handler = $options['handler'] ?? [self::class, 'defaultLogHandler'];
    }

    public function setLevel(string $level): void
    {
        $this->level = $level;
    }

    public function log(string $level, string $message, array $details = []): void
    {
        $shouldHandle = array_search($this->level, self::ALL_LEVELS) >= array_search($level, self::ALL_LEVELS);

        if (!$shouldHandle) {
            return;
        }

        // Pass null for details if not provided, to match TypeScript
        $detailsToPass = empty($details) ? null : $details;
        call_user_func($this->handler, $level, $message, $detailsToPass);
    }

    public function debug(string $message, array $details = []): void
    {
        $this->log('debug', $message, $details);
    }

    public function info(string $message, array $details = []): void
    {
        $this->log('info', $message, $details);
    }

    public function warn(string $message, array $details = []): void
    {
        $this->log('warn', $message, $details);
    }

    public function error(string $message, array $details = []): void
    {
        $this->log('error', $message, $details);
    }

    public static function defaultLogHandler(string $level, string $message, $details = null): void
    {
        $method = 'log';

        if ($level === 'info') {
            $method = 'info';
        } elseif ($level === 'warn') {
            $method = 'warn';
        } elseif ($level === 'error') {
            $method = 'error';
        }

        $prefix = '[Featurevisor]';
        echo "$prefix $message";
        if (!is_null($details)) {
            echo ' ' . json_encode($details);
        }
        echo PHP_EOL;
    }
}
