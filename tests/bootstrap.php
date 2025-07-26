<?php

/**
 * Bootstrap file for PHPUnit tests
 *
 * This file includes all the necessary source files for the Featurevisor PHP SDK tests
 */

// Include all source files
require_once __DIR__ . '/../src/index.php';
require_once __DIR__ . '/../src/Instance.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/Bucketer.php';
require_once __DIR__ . '/../src/Child.php';
require_once __DIR__ . '/../src/Conditions.php';
require_once __DIR__ . '/../src/CompareVersions.php';
require_once __DIR__ . '/../src/DatafileReader.php';
require_once __DIR__ . '/../src/Emitter.php';
require_once __DIR__ . '/../src/Events.php';
require_once __DIR__ . '/../src/Evaluation.php';
require_once __DIR__ . '/../src/Evaluate.php';
require_once __DIR__ . '/../src/EvaluateByBucketing.php';
require_once __DIR__ . '/../src/EvaluateDisabled.php';
require_once __DIR__ . '/../src/EvaluateForced.php';
require_once __DIR__ . '/../src/EvaluateNotFound.php';
require_once __DIR__ . '/../src/EvaluateSticky.php';
require_once __DIR__ . '/../src/Helpers.php';
require_once __DIR__ . '/../src/HooksManager.php';
require_once __DIR__ . '/../src/MurmurHash.php';

// Export functions to global namespace for tests
if (!function_exists('createInstance')) {
    function createInstance(array $options = []): \Featurevisor\Instance
    {
        return \Featurevisor\createInstance($options);
    }
}

if (!function_exists('createLogger')) {
    function createLogger(array $options = []): \Featurevisor\Logger
    {
        return \Featurevisor\createLogger($options);
    }
}

if (!function_exists('defaultLogHandler')) {
    function defaultLogHandler(string $level, string $message, array $details = []): void
    {
        $logger = new \Featurevisor\Logger();
        $logger->defaultLogHandler($level, $message, $details);
    }
}

if (!function_exists('getParamsForStickySetEvent')) {
    function getParamsForStickySetEvent(array $previousStickyFeatures, array $newStickyFeatures, bool $replace = false): array
    {
        return \Featurevisor\Events::getParamsForStickySetEvent($previousStickyFeatures, $newStickyFeatures, $replace);
    }
}

if (!function_exists('getParamsForDatafileSetEvent')) {
    function getParamsForDatafileSetEvent($previousDatafileReader, $newDatafileReader): array
    {
        return \Featurevisor\Events::getParamsForDatafileSetEvent($previousDatafileReader, $newDatafileReader);
    }
}

// Export classes to global namespace for tests
if (!class_exists('Logger')) {
    class_alias('Featurevisor\\Logger', 'Logger');
}

if (!class_exists('DatafileReader')) {
    class_alias('Featurevisor\\DatafileReader', 'DatafileReader');
}

// Set up any test-specific configurations here if needed
