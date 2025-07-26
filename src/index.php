<?php

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Emitter.php';
require_once __DIR__ . '/MurmurHash.php';
require_once __DIR__ . '/CompareVersions.php';
require_once __DIR__ . '/Helpers.php';
require_once __DIR__ . '/Bucketer.php';
require_once __DIR__ . '/Conditions.php';
require_once __DIR__ . '/DatafileReader.php';
require_once __DIR__ . '/HooksManager.php';
require_once __DIR__ . '/Evaluation.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/EvaluateDisabled.php';
require_once __DIR__ . '/EvaluateSticky.php';
require_once __DIR__ . '/EvaluateNotFound.php';
require_once __DIR__ . '/EvaluateForced.php';
require_once __DIR__ . '/EvaluateByBucketing.php';
require_once __DIR__ . '/Evaluate.php';
require_once __DIR__ . '/Child.php';
require_once __DIR__ . '/Instance.php';

// Export the main classes and functions
class_alias('Featurevisor\\Instance', 'FeaturevisorInstance');
class_alias('Featurevisor\\Child', 'FeaturevisorChildInstance');
class_alias('Featurevisor\\Logger', 'FeaturevisorLogger');
class_alias('Featurevisor\\Emitter', 'FeaturevisorEmitter');
class_alias('Featurevisor\\DatafileReader', 'FeaturevisorDatafileReader');
class_alias('Featurevisor\\HooksManager', 'FeaturevisorHooksManager');
class_alias('Featurevisor\\Evaluation', 'FeaturevisorEvaluation');
class_alias('Featurevisor\\Bucketer', 'FeaturevisorBucketer');
class_alias('Featurevisor\\Conditions', 'FeaturevisorConditions');
class_alias('Featurevisor\\MurmurHash', 'FeaturevisorMurmurHash');
class_alias('Featurevisor\\CompareVersions', 'FeaturevisorCompareVersions');
class_alias('Featurevisor\\Helpers', 'FeaturevisorHelpers');
class_alias('Featurevisor\\Events', 'FeaturevisorEvents');
class_alias('Featurevisor\\Evaluate', 'FeaturevisorEvaluate');
class_alias('Featurevisor\\EvaluateDisabled', 'FeaturevisorEvaluateDisabled');
class_alias('Featurevisor\\EvaluateSticky', 'FeaturevisorEvaluateSticky');
class_alias('Featurevisor\\EvaluateNotFound', 'FeaturevisorEvaluateNotFound');
class_alias('Featurevisor\\EvaluateForced', 'FeaturevisorEvaluateForced');
class_alias('Featurevisor\\EvaluateByBucketing', 'FeaturevisorEvaluateByBucketing');
