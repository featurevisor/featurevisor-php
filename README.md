# Featurevisor PHP SDK <!-- omit in toc -->

This is a port of Featurevisor [Javascript SDK](https://featurevisor.com/docs/sdks/javascript/) v2.x to PHP, providing a way to evaluate feature flags, variations, and variables in your PHP applications.

For more information, visit: [https://featurevisor.com](https://featurevisor.com)

- [Installation](#installation)
- [SDK usage](#sdk-usage)
  - [Create an instance](#create-an-instance)
  - [Set context](#set-context)
  - [Evaluate values](#evaluate-values)
    - [Evaluate a flag](#evaluate-a-flag)
    - [Evaluate a variation](#evaluate-a-variation)
    - [Evaluate a variable](#evaluate-a-variable)
    - [Evaluate all values](#evaluate-all-values)
  - [Passing additional context](#passing-additional-context)
  - [Set log level](#set-log-level)
  - [Set datafile](#set-datafile)
  - [Sticky features](#sticky-features)
  - [Child instances](#child-instances)
  - [Hooks](#hooks)
  - [Close](#close)
- [CLI usage](#cli-usage)
  - [Test](#test)
  - [Benchmark](#benchmark)
  - [Assess distribution](#assess-distribution)
- [License](#license)

## Installation

In your PHP application:

```bash
$ composer require featurevisor/featurevisor-php
```

## SDK usage

### Create an instance

```php
<?php

use function Featurevisor\createInstance;

$DATAFILE_CONTENT = "...";

$f = createInstance([
    "datafile" => $DATAFILE_CONTENT,
]);
```

Learn more about Featurevisor datafiles [here](https://featurevisor.com/docs/building-datafiles/).

### Set context

```php
$f->setContext([
    "appVersion" => "1.0.0",
    "userId"     => "123",
    "deviceId"   => "456",
    "country"    => "nl",
]);
```

Context keeps getting accumulated in the SDK instance as you set new ones.

To replace existing context, you can pass the second argument as `true`:

```php
$f->setContext([], true)
```

### Evaluate values

#### Evaluate a flag

```php
$isEnabled = $f->isEnabled("myFeatureKey");
```

#### Evaluate a variation

```php
$variation = $f->getVariation("myFeatureKey");
```

#### Evaluate a variable

```php
$variable = $f->getVariable("myFeatureKey", "variableKey");

// type specific methods
$variable = $f->getVariableBoolean("myFeatureKey", "variableKey");
$variable = $f->getVariableString("myFeatureKey", "variableKey");
$variable = $f->getVariableInteger("myFeatureKey", "variableKey");
$variable = $f->getVariableDouble("myFeatureKey", "variableKey");
$variable = $f->getVariableArray("myFeatureKey", "variableKey");
$variable = $f->getVariableObject("myFeatureKey", "variableKey");
$variable = $f->getVariableJSON("myFeatureKey", "variableKey");
```

#### Evaluate all values

```php
$allEvaluations = $f->getAllEvaluations();

// [
//     "myFeatureKey" => [
//         "enabled" => true,
//         "variation" => "variationA",
//         "variables" => [
//             "variableKey" => "value",
//             // ...
//         ],
//     ],
//
//     "anotherFeatureKey" => [ ... ],
//
//     // ...
// ]
```

### Passing additional context

Each evaluation method accepts an optional argument for passing additional context specific to that evaluation:

```php
$isEnabled = $f->isEnabled("myFeatureKey", [
    "browser" => "chrome",
]);
```

### Set log level

```php
$f->setLogLevel("debug");
```

Accepted values:

-   `debug`
-   `info`
-   `warn`
-   `error`

### Set datafile

You may want to update the datafile at runtime after initializing the SDK instance:

```php
$NEW_DATAFILE_CONTENT = "...";

$f->setDatafile($NEW_DATAFILE_CONTENT);
```

### Sticky features

Setting sticky features allow you to override the configuration as present in the datafile:

```php
$f->setSticky([
    "myFeatureKey" => [
        "enabled" => true,
        "variation" => "variationA",
        "variables" => [
            "variableKey" => "value",
        ],
    ],
]);
```

To clear sticky features, you can use:

```php
$f->setSticky([], true);
```

The second argument `true` indicates to replace the previously set stick features with new empty array.

### Child instances

```php
$childF = $f->spawn($optionalChildContext);

$childF->setContext([
    "userId" => "789",
]);

$isEnabled = $childF->isEnabled("myFeatureKey");
```

Child instances inherit the context and configuration from the primary instance, but you can set a different context for them.

They share similar methods as the primary instance for evaluations:

```php
$childF->isEnabled("myFeatureKey");
$childF->getVariation("myFeatureKey");
$childF->getVariable("myFeatureKey", "variableKey");
$childF->getAllEvaluations();
```

### Hooks

TODO

### Close

To remove any forgotten listeners, both the primary and child instances allow a `close` method:

```php
$f->close();
```

## CLI usage

This package also provides a CLI tool for running your Featurevisor project's test specs and benchmarking against this PHP SDK:

### Test

See: https://featurevisor.com/docs/testing/

```
$ vendor/bin/featurevisor test --projectDirectoryPath="path/to/your/featurevisor/project"
```

Additional options that are available:

```
$ vendor/bin/featurevisor test \
    --projectDirectoryPath="path/to/your/featurevisor/project" \
    --quiet|verbose \
    --onlyFailures \
    --keyPattern="myFeatureKey" \
    --assertionPattern="#1"
```

### Benchmark

TODO

### Assess distribution

TODO

## License

MIT © [Fahad Heylaal](https://fahad19.com)
