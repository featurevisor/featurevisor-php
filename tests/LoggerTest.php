<?php

namespace Featurevisor\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use Featurevisor\Logger;
use Psr\Log\LogLevel;

class LoggerTest extends TestCase
{
    private string $logBuffer;

    public static function levelsLoggingTestDataProvider(): iterable
    {
        yield LogLevel::DEBUG => [LogLevel::DEBUG];
        yield LogLevel::INFO => [LogLevel::INFO];
        yield LogLevel::WARNING => [LogLevel::WARNING];
        yield LogLevel::ERROR => [LogLevel::ERROR];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->logBuffer = '';
    }

    public function testCreateLoggerWithDefaultOptions(): void
    {
        $logger = Logger::create();
        self::assertInstanceOf(Logger::class, $logger);
    }

    public function testCreateLoggerWithCustomLevel(): void
    {
        $logger = Logger::create(['level' => 'debug']);
        self::assertInstanceOf(Logger::class, $logger);
    }

    public function testCreateLoggerWithCustomHandler(): void
    {
        $customHandlerCalled = false;
        $customHandler = function($level, $message, $details) use (&$customHandlerCalled) {
            $customHandlerCalled = true;
            self::assertEquals('info', $level);
            self::assertEquals('[Featurevisor] test message', $message);
            self::assertSame([], $details);
        };

        $logger = Logger::create(['handler' => $customHandler]);
        $logger->info('test message');

        self::assertTrue($customHandlerCalled);
    }

    public function testLoggerConstructorUsesDefaultLogLevelWhenNoneProvided(): void
    {
        $logger = Logger::create();

        // Capture output to verify debug is not logged with default level (info)
        $logger->debug('debug message');

        // Debug should not be logged with default level (info)
        self::assertEmpty($this->logBuffer);
    }

    public function testLoggerConstructorUsesProvidedLogLevel(): void
    {
        $logger = $this->getLogger(LogLevel::DEBUG);

        $logger->debug('debug message');

        self::assertEquals('[Featurevisor] debug message' . PHP_EOL, $this->logBuffer);
    }

    public function testLoggerConstructorUsesDefaultHandlerWhenNoneProvided(): void
    {
        $logger = $this->getLogger();

        $logger->info('test message');

        self::assertEquals('[Featurevisor] test message' . PHP_EOL, $this->logBuffer);
    }

    public function testLoggerConstructorUsesProvidedHandler(): void
    {
        $customHandlerCalled = false;
        $customHandler = function($level, $message, $details) use (&$customHandlerCalled) {
            $customHandlerCalled = true;
            self::assertEquals('info', $level);
            self::assertEquals('[Featurevisor] test message', $message);
            self::assertSame([], $details);
        };

        $logger = Logger::create(['handler' => $customHandler]);
        $logger->info('test message');

        self::assertTrue($customHandlerCalled);
    }

    public function testSetLevelUpdatesTheLogLevel(): void
    {
        $logger = $this->getLogger(LogLevel::INFO);

        // Debug should not be logged initially
        $logger->debug('first debug message');

        // Set to debug level
        $logger->setLevel(LogLevel::DEBUG);
        $logger->debug('second debug message');

        self::assertEquals(
            '[Featurevisor] second debug message' . PHP_EOL,
            $this->logBuffer
        );
    }

    /**
     * @dataProvider levelsLoggingTestDataProvider
     */
    public function testLogErrorMessagesAtAllLevels(string $level): void
    {
        $logger = $this->getLogger($level);

        $logger->error('error message');

        self::assertEquals(
            '[Featurevisor] error message' . PHP_EOL,
            $this->logBuffer
        );
    }

    public function testLogWarnMessagesAtWarnLevelAndAbove(): void
    {
        $logger = $this->getLogger(LogLevel::WARNING);

        $logger->warning('warn message');
        $logger->error('error message');

        self::assertEquals(
            '[Featurevisor] warn message' . PHP_EOL .
            '[Featurevisor] error message' . PHP_EOL,
            $this->logBuffer
        );
    }

    public function testNotLogInfoMessagesAtWarnLevel(): void
    {
        $logger = $this->getLogger(LogLevel::WARNING);

        $logger->info('info message');

        self::assertEmpty($this->logBuffer);
    }

    public function testNotLogDebugMessagesAtInfoLevel(): void
    {
        $logger = $this->getLogger(LogLevel::INFO);

        $logger->debug('debug message');

        self::assertEmpty($this->logBuffer);
    }

    public function testLogAllMessagesAtDebugLevel(): void
    {
        $logger = $this->getLogger(LogLevel::DEBUG);

        $logger->debug('debug message');
        $logger->info('info message');
        $logger->warning('warn message');
        $logger->error('error message');

        self::assertEquals(
            '[Featurevisor] debug message' . PHP_EOL .
            '[Featurevisor] info message' . PHP_EOL .
            '[Featurevisor] warn message' . PHP_EOL .
            '[Featurevisor] error message' . PHP_EOL,
            $this->logBuffer
        );
    }

    public function testHandleDetailsParameter(): void
    {
        $logger = $this->getLogger(LogLevel::DEBUG);
        $details = ['key' => 'value', 'number' => 42];

        $logger->info('message with details', $details);

        self::assertEquals(
            '[Featurevisor] message with details {"key":"value","number":42}' . PHP_EOL,
            $this->logBuffer
        );
    }

    public function testLogMethodCallsHandlerWithCorrectParameters(): void
    {
        $customHandlerCalled = false;
        $customHandler = function($level, $message, $details) use (&$customHandlerCalled) {
            $customHandlerCalled = true;
            self::assertEquals('info', $level);
            self::assertEquals('[Featurevisor] test message', $message);
            self::assertEquals(['test' => true], $details);
        };

        $logger = Logger::create(['handler' => $customHandler, 'level' => 'debug']);
        $details = ['test' => true];

        $logger->log('info', 'test message', $details);

        self::assertTrue($customHandlerCalled);
    }

    public function testLogMethodNotCallHandlerWhenLevelIsFilteredOut(): void
    {
        $customHandlerCalled = false;
        $customHandler = function($level, $message, $details) use (&$customHandlerCalled) {
            $customHandlerCalled = true;
        };

        $logger = Logger::create(['handler' => $customHandler, 'level' => LogLevel::WARNING]);

        $logger->log('debug', 'debug message');
        self::assertFalse($customHandlerCalled);
    }

    private function getLogger(string $level = Logger::DEFAULT_LEVEL): Logger
    {
        return Logger::create(['level' => $level, 'handler' => function ($level, $message, array $context) {
            $context = $context !== [] ? ' ' . json_encode($context, JSON_THROW_ON_ERROR) : '';
            $this->logBuffer .= $message . $context . PHP_EOL;
        }]);
    }
}
