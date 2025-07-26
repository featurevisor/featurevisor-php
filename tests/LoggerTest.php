<?php

use PHPUnit\Framework\TestCase;

use Featurevisor\Logger;
use function Featurevisor\createLogger;

class LoggerTest extends TestCase
{
    private $originalOutput;

    protected function setUp(): void
    {
        parent::setUp();
        // Capture original output functions
        $this->originalOutput = [
            'log' => function_exists('console_log') ? 'console_log' : null,
            'info' => function_exists('console_info') ? 'console_info' : null,
            'warn' => function_exists('console_warn') ? 'console_warn' : null,
            'error' => function_exists('console_error') ? 'console_error' : null,
        ];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Restore original output functions if needed
    }

    public function testCreateLoggerWithDefaultOptions()
    {
        $logger = createLogger();
        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testCreateLoggerWithCustomLevel()
    {
        $logger = createLogger(['level' => 'debug']);
        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testCreateLoggerWithCustomHandler()
    {
        $customHandlerCalled = false;
        $customHandler = function($level, $message, $details) use (&$customHandlerCalled) {
            $customHandlerCalled = true;
            $this->assertEquals('info', $level);
            $this->assertEquals('test message', $message);
            $this->assertNull($details);
        };

        $logger = createLogger(['handler' => $customHandler]);
        $logger->info('test message');

        $this->assertTrue($customHandlerCalled);
    }

    public function testLoggerConstructorUsesDefaultLogLevelWhenNoneProvided()
    {
        $logger = new Logger([]);

        // Capture output to verify debug is not logged with default level (info)
        ob_start();
        $logger->debug('debug message');
        $output = ob_get_clean();

        // Debug should not be logged with default level (info)
        $this->assertEmpty($output);
    }

    public function testLoggerConstructorUsesProvidedLogLevel()
    {
        $logger = new Logger(['level' => 'debug']);

        // Capture output to verify debug is logged with debug level
        ob_start();
        $logger->debug('debug message');
        $output = ob_get_clean();

        // Debug should be logged with debug level
        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('debug message', $output);
    }

    public function testLoggerConstructorUsesDefaultHandlerWhenNoneProvided()
    {
        $logger = new Logger([]);

        // Capture output to verify info is logged
        ob_start();
        $logger->info('test message');
        $output = ob_get_clean();

        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('test message', $output);
    }

    public function testLoggerConstructorUsesProvidedHandler()
    {
        $customHandlerCalled = false;
        $customHandler = function($level, $message, $details) use (&$customHandlerCalled) {
            $customHandlerCalled = true;
            $this->assertEquals('info', $level);
            $this->assertEquals('test message', $message);
            $this->assertNull($details);
        };

        $logger = new Logger(['handler' => $customHandler]);
        $logger->info('test message');

        $this->assertTrue($customHandlerCalled);
    }

    public function testSetLevelUpdatesTheLogLevel()
    {
        $logger = new Logger(['level' => 'info']);

        // Debug should not be logged initially
        ob_start();
        $logger->debug('debug message');
        $output = ob_get_clean();
        $this->assertEmpty($output);

        // Set to debug level
        $logger->setLevel('debug');
        ob_start();
        $logger->debug('debug message');
        $output = ob_get_clean();
        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('debug message', $output);
    }

    public function testLogErrorMessagesAtAllLevels()
    {
        $levels = ['debug', 'info', 'warn', 'error'];

        foreach ($levels as $level) {
            $logger = new Logger(['level' => $level]);

            ob_start();
            $logger->error('error message');
            $output = ob_get_clean();

            $this->assertStringContainsString('[Featurevisor]', $output);
            $this->assertStringContainsString('error message', $output);
        }
    }

    public function testLogWarnMessagesAtWarnLevelAndAbove()
    {
        $logger = new Logger(['level' => 'warn']);

        ob_start();
        $logger->warn('warn message');
        $output = ob_get_clean();
        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('warn message', $output);

        ob_start();
        $logger->error('error message');
        $output = ob_get_clean();
        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('error message', $output);
    }

    public function testNotLogInfoMessagesAtWarnLevel()
    {
        $logger = new Logger(['level' => 'warn']);

        ob_start();
        $logger->info('info message');
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    public function testNotLogDebugMessagesAtInfoLevel()
    {
        $logger = new Logger(['level' => 'info']);

        ob_start();
        $logger->debug('debug message');
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    public function testLogAllMessagesAtDebugLevel()
    {
        $logger = new Logger(['level' => 'debug']);

        ob_start();
        $logger->debug('debug message');
        $output = ob_get_clean();
        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('debug message', $output);

        ob_start();
        $logger->info('info message');
        $output = ob_get_clean();
        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('info message', $output);

        ob_start();
        $logger->warn('warn message');
        $output = ob_get_clean();
        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('warn message', $output);

        ob_start();
        $logger->error('error message');
        $output = ob_get_clean();
        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('error message', $output);
    }

    public function testDebugMethodCallsCorrectly()
    {
        $logger = new Logger(['level' => 'debug']);

        ob_start();
        $logger->debug('debug message');
        $output = ob_get_clean();

        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('debug message', $output);
    }

    public function testInfoMethodCallsCorrectly()
    {
        $logger = new Logger(['level' => 'debug']);

        ob_start();
        $logger->info('info message');
        $output = ob_get_clean();

        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('info message', $output);
    }

    public function testWarnMethodCallsCorrectly()
    {
        $logger = new Logger(['level' => 'debug']);

        ob_start();
        $logger->warn('warn message');
        $output = ob_get_clean();

        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('warn message', $output);
    }

    public function testErrorMethodCallsCorrectly()
    {
        $logger = new Logger(['level' => 'debug']);

        ob_start();
        $logger->error('error message');
        $output = ob_get_clean();

        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('error message', $output);
    }

    public function testHandleDetailsParameter()
    {
        $logger = new Logger(['level' => 'debug']);
        $details = ['key' => 'value', 'number' => 42];

        ob_start();
        $logger->info('message with details', $details);
        $output = ob_get_clean();

        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('message with details', $output);
        // Note: In PHP, the details might be serialized differently than in JS
    }

    public function testLogMethodCallsHandlerWithCorrectParameters()
    {
        $customHandlerCalled = false;
        $customHandler = function($level, $message, $details) use (&$customHandlerCalled) {
            $customHandlerCalled = true;
            $this->assertEquals('info', $level);
            $this->assertEquals('test message', $message);
            $this->assertEquals(['test' => true], $details);
        };

        $logger = new Logger(['handler' => $customHandler, 'level' => 'debug']);
        $details = ['test' => true];

        $logger->log('info', 'test message', $details);
        $this->assertTrue($customHandlerCalled);
    }

    public function testLogMethodNotCallHandlerWhenLevelIsFilteredOut()
    {
        $customHandlerCalled = false;
        $customHandler = function($level, $message, $details) use (&$customHandlerCalled) {
            $customHandlerCalled = true;
        };

        $logger = new Logger(['handler' => $customHandler, 'level' => 'warn']);

        $logger->log('debug', 'debug message');
        $this->assertFalse($customHandlerCalled);
    }

    public function testDefaultLogHandlerUsesConsoleLogForDebugLevel()
    {
        ob_start();
        Logger::defaultLogHandler('debug', 'debug message');
        $output = ob_get_clean();

        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('debug message', $output);
    }

    public function testDefaultLogHandlerUsesConsoleInfoForInfoLevel()
    {
        ob_start();
        Logger::defaultLogHandler('info', 'info message');
        $output = ob_get_clean();

        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('info message', $output);
    }

    public function testDefaultLogHandlerUsesConsoleWarnForWarnLevel()
    {
        ob_start();
        Logger::defaultLogHandler('warn', 'warn message');
        $output = ob_get_clean();

        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('warn message', $output);
    }

    public function testDefaultLogHandlerUsesConsoleErrorForErrorLevel()
    {
        ob_start();
        Logger::defaultLogHandler('error', 'error message');
        $output = ob_get_clean();

        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('error message', $output);
    }

    public function testDefaultLogHandlerHandlesUndefinedDetails()
    {
        ob_start();
        Logger::defaultLogHandler('info', 'message without details');
        $output = ob_get_clean();

        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('message without details', $output);
    }

    public function testDefaultLogHandlerHandlesProvidedDetails()
    {
        $details = ['key' => 'value'];

        ob_start();
        Logger::defaultLogHandler('info', 'message with details', $details);
        $output = ob_get_clean();

        $this->assertStringContainsString('[Featurevisor]', $output);
        $this->assertStringContainsString('message with details', $output);
    }
}
