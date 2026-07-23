# Featurevisor PHP SDK <!-- omit in toc -->

This is a port of Featurevisor [Javascript SDK](https://featurevisor.com/docs/sdks/javascript/) v3.x to PHP, providing a way to evaluate feature flags, variations, and variables in your PHP applications.

This SDK is compatible with [Featurevisor](https://featurevisor.com/) v3.0 projects and v2 datafiles.

## Table of contents <!-- omit in toc -->

- [Installation](#installation)
- [Public API](#public-api)
- [Initialization](#initialization)
- [Evaluation types](#evaluation-types)
- [Context](#context)
  - [Setting initial context](#setting-initial-context)
  - [Setting after initialization](#setting-after-initialization)
  - [Replacing existing context](#replacing-existing-context)
  - [Manually passing context](#manually-passing-context)
- [Check if enabled](#check-if-enabled)
- [Getting variation](#getting-variation)
- [Getting variables](#getting-variables)
  - [Type specific methods](#type-specific-methods)
- [Getting all evaluations](#getting-all-evaluations)
- [Sticky](#sticky)
  - [Initialize with sticky](#initialize-with-sticky)
  - [Set sticky afterwards](#set-sticky-afterwards)
- [Setting datafile](#setting-datafile)
  - [Merging by default](#merging-by-default)
  - [Replacing](#replacing)
  - [Loading datafiles on demand](#loading-datafiles-on-demand)
  - [Updating datafile](#updating-datafile)
- [Evaluation details](#evaluation-details)
- [Diagnostics](#diagnostics)
  - [Levels](#levels)
  - [Handler](#handler)
- [Events](#events)
  - [`datafile_set`](#datafile_set)
  - [`context_set`](#context_set)
  - [`sticky_set`](#sticky_set)
  - [`error`](#error)
- [Modules](#modules)
  - [Defining a module](#defining-a-module)
  - [Registering modules](#registering-modules)
- [Child instance](#child-instance)
- [Close](#close)
- [OpenFeature](#openfeature)
  - [Installation](#installation-1)
  - [Provider setup](#provider-setup)
  - [Flag key mapping](#flag-key-mapping)
  - [Context mapping](#context-mapping)
  - [Resolution details](#resolution-details)
  - [Tracking](#tracking)
  - [Using an existing Featurevisor instance](#using-an-existing-featurevisor-instance)
- [CLI usage](#cli-usage)
  - [Test](#test)
  - [Benchmark](#benchmark)
  - [Assess distribution](#assess-distribution)
- [Development of this package](#development-of-this-package)
  - [Setting up](#setting-up)
  - [Running tests](#running-tests)
  - [Releasing](#releasing)
- [License](#license)

<!-- FEATUREVISOR_DOCS_BEGIN -->

## Installation

The Featurevisor PHP SDK requires PHP 8.0 or newer. Install it using [Composer](https://getcomposer.org/):

```
$ composer require featurevisor/featurevisor-php
```

## Public API

The main runtime API is `Featurevisor::createFeaturevisor()`:

```php
use Featurevisor\Featurevisor;

$f = Featurevisor::createFeaturevisor([
  "datafile" => $datafileContent,
]);
```

Most applications only need this factory and the returned `Featurevisor` instance. Public extension and observability APIs include modules, diagnostics, events, and the datafile arrays accepted by the factory.

Treat an instance as request-owned in normal PHP applications. If a long-running parallel runtime shares an instance, serialize calls that mutate or close it. Module, event, and diagnostic callbacks are responsible for synchronizing mutable state that they capture.

## Initialization

The SDK can be initialized by passing [datafile](https://featurevisor.com/docs/building-datafiles/) content directly:

```php
<?php

use Featurevisor\Featurevisor;

$datafileUrl = "https://cdn.yoursite.com/datafile.json";

$datafileContent = file_get_contents($datafileUrl);
$datafileContent = json_decode($datafileContent, true);

$f = Featurevisor::createFeaturevisor([
  "datafile" => $datafileContent
]);
```

## Evaluation types

We can evaluate 3 types of values against a particular [feature](https://featurevisor.com/docs/features/):

- [**Flag**](#check-if-enabled) (`boolean`): whether the feature is enabled or not
- [**Variation**](#getting-variation) (`string`): the variation of the feature (if any)
- [**Variables**](#getting-variables): variable values of the feature (if any)

These evaluations are run against the provided context.

## Context

Contexts are [attribute](https://featurevisor.com/docs/attributes) values that we pass to SDK for evaluating [features](https://featurevisor.com/docs/features) against.

Think of the conditions that you define in your [segments](https://featurevisor.com/docs/segments/), which are used in your feature's [rules](https://featurevisor.com/docs/features/#rules).

They are plain objects:

```php
$context = [
  "userId" => "123",
  "country" => "nl",
  // ...other attributes
];
```

Context can be passed to SDK instance in various different ways, depending on your needs:

### Setting initial context

You can set context at the time of initialization:

```php
use Featurevisor\Featurevisor;

$f = Featurevisor::createFeaturevisor([
  "context" => [
    "deviceId" => "123",
    "country" => "nl",
  ],
]);
```

This is useful for values that don't change too frequently and available at the time of application startup.

### Setting after initialization

You can also set more context after the SDK has been initialized:

```php
$f->setContext([
  "userId" => "123",
  "country" => "nl",
]);
```

This will merge the new context with the existing one (if already set).

### Replacing existing context

If you wish to fully replace the existing context, you can pass `true` in second argument:

```php
$f->setContext(
  [
    "deviceId" => "123",
    "userId" => "234",
    "country" => "nl",
    "browser" => "chrome",
  ],
  true // replace existing context
);
```

### Manually passing context

You can optionally pass additional context manually for each and every evaluation separately, without needing to set it to the SDK instance affecting all evaluations:

```php
$context = [
  "userId" => "123",
  "country" => "nl",
];

$isEnabled = $f->isEnabled('my_feature', $context);
$variation = $f->getVariation('my_feature', $context);
$variableValue = $f->getVariable('my_feature', 'my_variable', $context);
```

When manually passing context, it will merge with existing context set to the SDK instance before evaluating the specific value.

Further details for each evaluation types are described below.

## Check if enabled

Once the SDK is initialized, you can check if a feature is enabled or not:

```php
$featureKey = 'my_feature';

$isEnabled = $f->isEnabled($featureKey);

if ($isEnabled) {
  // do something
}
```

You can also pass additional context per evaluation:

```php
$isEnabled = $f->isEnabled($featureKey, [
  // ...additional context
]);
```

## Getting variation

If your feature has any [variations](https://featurevisor.com/docs/features/#variations) defined, you can evaluate them as follows:

```php
$featureKey = 'my_feature';

$variation = $f->getVariation($featureKey);

if ($variation === "treatment") {
  // do something for treatment variation
} else {
  // handle default/control variation
}
```

Additional context per evaluation can also be passed:

```php
$variation = $f->getVariation($featureKey, [
  // ...additional context
]);
```

## Getting variables

Your features may also include [variables](https://featurevisor.com/docs/features/#variables), which can be evaluated as follows:

```php
$variableKey = 'bgColor';

$bgColorValue = $f->getVariable($featureKey, $variableKey);
```

Additional context per evaluation can also be passed:

```php
$bgColorValue = $f->getVariable($featureKey, $variableKey, [
  // ...additional context
]);
```

### Type specific methods

Next to generic `getVariable()` methods, there are also type specific methods available for convenience:

```php
$f->getVariableBoolean($featureKey, $variableKey, $context = []);
$f->getVariableString($featureKey, $variableKey, $context = []);
$f->getVariableInteger($featureKey, $variableKey, $context = []);
$f->getVariableDouble($featureKey, $variableKey, $context = []);
$f->getVariableArray($featureKey, $variableKey, $context = []);
$f->getVariableObject($featureKey, $variableKey, $context = []);
$f->getVariableJSON($featureKey, $variableKey, $context = []);
```

Type specific methods do not coerce values. `getVariableInteger()` returns `null` for the string `"1"`, and boolean getters return `null` for non-boolean values.

## Getting all evaluations

You can get evaluations of all features available in the SDK instance:

```php
$allEvaluations = $f->getAllEvaluations($context = []);

print_r($allEvaluations);
// [
//   myFeature: [
//     enabled: true,
//     variation: "control",
//     variables: [
//       myVariableKey: "myVariableValue",
//     ],
//   ],
//
//   anotherFeature: [
//     enabled: true,
//     variation: "treatment",
//   ]
// ]
```

This is handy especially when you want to pass all evaluations from a backend application to the frontend.

## Sticky

For the lifecycle of the SDK instance in your application, you can set some features with sticky values, meaning that they will not be evaluated against the fetched [datafile](https://featurevisor.com/docs/building-datafiles/):

Sticky values belong to an SDK or child instance. Evaluation options do not accept sticky overrides; use `spawn($context, ['sticky' => ...])` when a child needs its own sticky state.

### Initialize with sticky

```php
use Featurevisor\Featurevisor;

$f = Featurevisor::createFeaturevisor([
  "sticky" => [
    "myFeatureKey" => [
      "enabled" => true,

      // optional
      "variation" => 'treatment',
      "variables" => [
        "myVariableKey" => 'myVariableValue',
      ],
    ],

    "anotherFeatureKey" => [
      "enabled" => false,
    ],
  ],
]);
```

Once initialized with sticky features, the SDK will look for values there first before evaluating the targeting conditions and going through the bucketing process.

### Set sticky afterwards

You can also set sticky features after the SDK is initialized:

```php
$f->setSticky(
  [
    "myFeatureKey" => [
      "enabled" => true,
      "variation" => 'treatment',
      "variables" => [
        "myVariableKey" => 'myVariableValue',
      ],
    ],
    "anotherFeatureKey" => [
      "enabled" => false,
    ],
  ],

  // replace existing sticky features (false by default)
  true
]);
```

## Setting datafile

You may also initialize the SDK without passing `datafile`, and set it later on:

```php
$f->setDatafile($datafileContent);
```

### Merging by default

By default, `setDatafile($datafileContent)` merges the incoming datafile with the SDK instance's existing datafile:

- incoming `features` and `segments` override matching keys
- existing `features` and `segments` that are missing from the incoming datafile are kept
- `revision`, `schemaVersion`, and `featurevisorVersion` are taken from the incoming datafile

This means you can call `setDatafile` more than once with different datafiles, and the SDK instance accumulates their features and segments together.

### Replacing

To replace the stored datafile completely, pass `true` as the second argument:

```php
$f->setDatafile($datafileContent, true);
```

### Loading datafiles on demand

Because merging is the default, a single SDK instance can start with a small datafile and load more datafiles later as your application needs them, instead of downloading every feature upfront.

This pairs well with [targets](https://featurevisor.com/docs/targets/), where each target produces a smaller datafile for a specific part of your application:

```php
$f = Featurevisor::createFeaturevisor([]);

function loadDatafile($f, string $target): void {
  $url = "https://cdn.yoursite.com/production/featurevisor-$target.json";
  $datafile = json_decode(file_get_contents($url), true);

  // merges into whatever was loaded before
  $f->setDatafile($datafile);
}

loadDatafile($f, 'products');

// later, when the user reaches checkout
loadDatafile($f, 'checkout');
```

### Updating datafile

You can set the datafile as many times as you want in your application, which will result in emitting a [`datafile_set`](#datafile_set) event that you can listen and react to accordingly.

The triggers for setting the datafile again can be:

- periodic updates based on an interval (like every 5 minutes), or
- reacting to:
  - a specific event in your application (like a user action), or
  - an event served via websocket or server-sent events (SSE)

## Diagnostics

By default, Featurevisor reports diagnostics to the console for `info` level and above with a `[Featurevisor]` prefix.

### Levels

Available diagnostic levels are `fatal`, `error`, `warn`, `info`, and `debug`.

Set the level during initialization or update it afterwards:

```php
$f = Featurevisor::createFeaturevisor([
  "logLevel" => "debug",
]);

$f->setLogLevel("info");
```

### Handler

Use `onDiagnostic` to send structured diagnostics to your observability system:

```php
$f = Featurevisor::createFeaturevisor([
  "logLevel" => "info",
  "onDiagnostic" => function (array $diagnostic) {
    // send $diagnostic to your observability system
  },
]);
```

Every diagnostic has `level`, `code`, `message`, and an object-shaped `details` value. Optional `module`, `moduleName`, and `originalError` fields describe provenance. Evaluation metadata belongs in `details`.

Diagnostic handlers are isolated from SDK behavior. An exception in a handler does not stop other handlers or evaluations.


## Events

Featurevisor SDK implements a simple event emitter that allows you to listen to events that happen in the runtime.

You can listen to these events that can occur at various stages in your application:

### `datafile_set`

```php
$unsubscribe = $f->on('datafile_set', function ($event) {
  $revision = $event['revision']; // new revision
  $previousRevision = $event['previousRevision'];
  $revisionChanged = $event['revisionChanged']; // true if revision has changed
  $replaced = $event['replaced']; // true if datafile was replaced instead of merged

  // list of feature keys that have new updates,
  // and you should re-evaluate them
  $features = $event['features'];

  // handle here
});

// stop listening to the event
$unsubscribe();
```

The `features` array will contain keys of features that have either been:

- added, or
- updated, or
- removed

compared to the previous datafile content that existed in the SDK instance.

### `context_set`

```php
$unsubscribe = $f->on('context_set', function ($event) {
  $replaced = $event['replaced']; // true if context was replaced
  $context = $event['context']; // the new context

  echo "Context set";
});
```

### `sticky_set`

```php
$unsubscribe = $f->on('sticky_set', function ($event) {
  $replaced = $event['replaced']; // true if sticky features got replaced
  $features = $event['features']; // list of all affected feature keys

  echo "Sticky features set";
});
```

### `error`

```php
$unsubscribe = $f->on('error', function ($event) {
  echo $event['diagnostic']['message'];
});
```

The `error` event is emitted for diagnostics whose level is `error`.

## Evaluation details

Besides logging with debug level enabled, you can also get more details about how the feature variations and variables are evaluated in the runtime against given context:

```php
// flag
$evaluation = $f->evaluateFlag($featureKey, $context = []);

// variation
$evaluation = $f->evaluateVariation($featureKey, $context = []);

// variable
$evaluation = $f->evaluateVariable($featureKey, $variableKey, $context = []);
```

The returned object will always contain the following properties:

- `featureKey`: the feature key
- `reason`: the reason how the value was evaluated

And optionally these properties depending on whether you are evaluating a feature variation or a variable:

- `bucketValue`: the bucket value between 0 and 100,000
- `ruleKey`: the rule key
- `error`: the error object
- `enabled`: if feature itself is enabled or not
- `variation`: the variation object
- `variationValue`: the variation value
- `variableKey`: the variable key
- `variableValue`: the variable value
- `variableSchema`: the variable schema

## Modules

Modules allow you to intercept the evaluation process, report diagnostics, and customize behavior further as per your needs.

### Defining a module

A module is a simple array with optional lifecycle callbacks. A `name` is optional, but when provided it must be unique:

If `setup` throws, the module is not registered. Featurevisor removes subscriptions created during setup, reports `module_setup_error`, and calls `close` when present.

```php
$myCustomModule = [
  // only required property
  'name' => 'my-custom-module',

  // rest of the properties below are all optional per module

  // setup receives a module API
  'setup' => function ($api) {
    $revision = $api['getRevision']();

    $unsubscribe = $api['onDiagnostic'](function (array $diagnostic) {
      // observe diagnostics reported by other modules or the SDK
    });

    $api['reportDiagnostic']([
      'level' => 'info',
      'code' => 'custom_module_ready',
      'message' => 'Custom module is ready',
    ]);
  },

  // before evaluation
  'before' => function ($options) {
    $type = $options['type']; // `flag` | `variation` | `variable`
    $featureKey = $options['featureKey'];
    $variableKey = $options['variableKey']; // if type is `variable`
    $context = $options['context'];

    // update context before evaluation
    $options['context'] = array_merge($options['context'], [
      'someAdditionalAttribute' => 'value',
    ]);

    return $options;
  },

  // after evaluation
  'after' => function ($evaluation, $options) {
    $reason = $evaluation['reason']; // `error` | `feature_not_found` | `variable_not_found` | ...

    if ($reason === "error") {
      // log error

      return;
    }
  },

  // configure bucket key
  'bucketKey' => function ($options) {
    $featureKey = $options['featureKey'];
    $context = $options['context'];
    $bucketBy = $options['bucketBy'];
    $bucketKey = $options['bucketKey']; // default bucket key

    // return custom bucket key
    return $bucketKey;
  },

  // configure bucket value (between 0 and 100,000)
  'bucketValue' => function ($options) {
    $featureKey = $options['featureKey'];
    $context = $options['context'];
    $bucketKey = $options['bucketKey'];
    $bucketValue = $options['bucketValue']; // default bucket value

    // return custom bucket value
    return $bucketValue;
  },

  // cleanup
  'close' => function () {
    // release module resources
  },
];
```

### Registering modules

You can register modules at the time of SDK initialization:

```php
use Featurevisor\Featurevisor;

$f = Featurevisor::createFeaturevisor([
  'modules' => [
    $myCustomModule
  ],
]);
```

Or after initialization:

```php
$removeModule = $f->addModule($myCustomModule);

// $removeModule()

// or remove later by name
$f->removeModule('my-custom-module');
```

## Child instance

A child snapshots the parent keys that exist when it is spawned. Child values win for those keys. Parent keys introduced later are still inherited. Calling `close()` removes both child-owned listeners and subscriptions delegated to the parent.

When dealing with purely client-side applications, it is understandable that there is only one user involved, like in browser or mobile applications.

But when using Featurevisor SDK in server-side applications, where a single server instance can handle multiple user requests simultaneously, it is important to isolate the context for each request.

That's where child instances come in handy:

```php
$childF = $f->spawn([
  // user or request specific context
  'userId' => '123',
]);
```

Now you can pass the child instance where your individual request is being handled, and you can continue to evaluate features targeting that specific user alone:

```php
$isEnabled = $childF->isEnabled('my_feature');
$variation = $childF->getVariation('my_feature');
$variableValue = $childF->getVariable('my_feature', 'my_variable');
```

Similar to parent SDK, child instances also support several additional methods:

- `setContext`
- `setSticky`
- `evaluateFlag`
- `isEnabled`
- `evaluateVariation`
- `getVariation`
- `evaluateVariable`
- `getVariable`
- `getVariableBoolean`
- `getVariableString`
- `getVariableInteger`
- `getVariableDouble`
- `getVariableArray`
- `getVariableObject`
- `getVariableJSON`
- `getAllEvaluations`
- `on`
- `close`

## Close

Both primary and child instances support a `.close()` method. The primary instance also closes registered modules and removes diagnostic subscriptions.

```php
$f->close();
```

## CLI usage

This package also provides a CLI tool for running your Featurevisor project's test specs and benchmarking against this PHP SDK:

All three commands accept repeatable `--target=<target>` options. `test` builds only the selected Target datafiles and runs untargeted assertions plus assertions for those targets. `benchmark` and `assess-distribution` run independently against every selected Target datafile. Without `--target`, existing project-wide behavior is preserved. Project definitions, test specs, Target discovery, and datafile generation continue to come from the Node.js CLI.

### Test

Learn more about testing [here](https://featurevisor.com/docs/testing/).

```
$ vendor/bin/featurevisor test --projectDirectoryPath="/absolute/path/to/your/featurevisor/project"
```

Additional options that are available:

```
$ vendor/bin/featurevisor test \
    --projectDirectoryPath="/absolute/path/to/your/featurevisor/project" \
    --quiet|verbose \
    --onlyFailures \
    --keyPattern="myFeatureKey" \
    --assertionPattern="#1"
```

If assertions include `target`, the runner builds and selects the corresponding Target datafile automatically via `npx featurevisor build --target=<target> --environment=<env> --json`.

### Benchmark

Learn more about benchmarking [here](https://featurevisor.com/docs/cli/#benchmarking).

```
$ vendor/bin/featurevisor benchmark \
    --projectDirectoryPath="/absolute/path/to/your/featurevisor/project" \
    --environment="production" \
    --feature="myFeatureKey" \
    --context='{"country": "nl"}' \
    --n=1000
```

### Assess distribution

Learn more about assessing distribution [here](https://featurevisor.com/docs/cli/#assess-distribution).

```
$ vendor/bin/featurevisor assess-distribution \
    --projectDirectoryPath="/absolute/path/to/your/featurevisor/project" \
    --environment=production \
    --feature=foo \
    --variation \
    --context='{"country": "nl"}' \
    --populateUuid=userId \
    --populateUuid=deviceId \
    --n=1000
```

## OpenFeature

The provider targets OpenFeature PHP SDK `2.x`. OpenFeature remains optional and is not installed or loaded by the base Featurevisor SDK.

### Installation

```bash
composer require featurevisor/featurevisor-php open-feature/sdk:^2.2
```

### Provider setup

```php
use Featurevisor\OpenFeatureProvider;
use OpenFeature\OpenFeatureAPI;
use OpenFeature\implementation\flags\Attributes;
use OpenFeature\implementation\flags\EvaluationContext;

$provider = new OpenFeatureProvider([
    'datafile' => $datafileContent,
]);

$api = OpenFeatureAPI::getInstance();
$api->setProvider($provider);

$client = $api->getClient();
$enabled = $client->getBooleanValue(
    'checkout',
    false,
    new EvaluationContext('user-123', new Attributes(['country' => 'nl']))
);
```

The current OpenFeature PHP SDK does not expose provider shutdown through its API. Call `$provider->shutdown()` when your application shuts down. This closes a Featurevisor instance created by the provider and releases provider subscriptions.

### Flag key mapping

| OpenFeature key | Featurevisor evaluation |
| --- | --- |
| `checkout` | Boolean flag for `checkout` |
| `checkout:variation` | Variation value for `checkout` |
| `checkout:title` | Variable `title` for `checkout` |

Boolean variables use the boolean resolver. Integer and double variables use their matching numeric resolvers. Arrays, objects, and JSON variables use the object resolver.

The first separator divides the feature key from the selector. Use `keySeparator` and `variationKey` when project keys require a different grammar:

```php
$provider = new OpenFeatureProvider(
    options: ['datafile' => $datafileContent],
    keySeparator: '/',
    variationKey: '$variation'
);
```

This makes `checkout/$variation` the variation key and `checkout/title` a variable key.

### Context mapping

OpenFeature's targeting key maps to `userId` by default. Use `targetingKeyField` to map it to another Featurevisor context field:

```php
$provider = new OpenFeatureProvider(
    options: ['datafile' => $datafileContent],
    targetingKeyField: 'accountId'
);
```

OpenFeature context attributes are copied without mutating the incoming context. Nested arrays are preserved. Dates are normalized to UTC ISO strings with millisecond precision, matching the JavaScript provider.

### Resolution details

The provider maps Featurevisor evaluation results to OpenFeature details:

| Featurevisor result | OpenFeature result |
| --- | --- |
| Required, forced, sticky, or rule match | `TARGETING_MATCH` |
| Traffic allocation | `SPLIT` |
| Disabled variation or variable | `DISABLED` |
| No match or variable default | `DEFAULT` |
| Missing feature, variable, or variations | `ERROR` with `FLAG_NOT_FOUND` |
| Wrong resolver type | `ERROR` with `TYPE_MISMATCH` |
| Invalid datafile | `ERROR` with `PARSE_ERROR` |
| Evaluation failure | `ERROR` with `GENERAL` |

Errors return the default value supplied to OpenFeature. A malformed datafile uses the stable message `Could not parse datafile`. A later successful `setDatafile()` call clears the parse error.

Selected Featurevisor variations are exposed as the OpenFeature variant when available. OpenFeature PHP SDK 2.x does not expose flag metadata in resolution details, so Featurevisor metadata such as revision, rule key, and bucket value cannot currently be returned by this provider.

### Tracking

Tracking is a no-op unless `onTrack` is configured:

```php
$provider = new OpenFeatureProvider(
    options: ['datafile' => $datafileContent],
    onTrack: function ($name, $context, $details) {
        echo $name;
    }
);
```

### Using an existing Featurevisor instance

You can reuse an existing Featurevisor instance:

```php
$featurevisor = Featurevisor::createFeaturevisor(['datafile' => $datafileContent]);
$provider = new OpenFeatureProvider(featurevisor: $featurevisor);
```

The caller owns an instance passed this way. Calling `$provider->shutdown()` does not close it. Call `$featurevisor->close()` when every consumer is finished with it. When the provider creates the instance from options, the provider owns and closes it. If both are supplied, `$featurevisor` takes precedence over `$options`. Shutdown is safe to call more than once.

See the [OpenFeature provider guide](https://featurevisor.com/docs/sdks/openfeature/) for resolution reasons, errors, lifecycle, and providers for other languages.

<!-- FEATUREVISOR_DOCS_END -->

## Development of this package

### Setting up

Clone the repository, and install the dependencies using [Composer](https://getcomposer.org/):

```
$ composer install
```

### Running tests

```
$ composer test
```

Run the complete local release check with:

```
$ make check
```

The OpenFeature and base SDK tests can also be run separately with `make test-openfeature` and `make test-base`.

To run the SDK against Featurevisor example-1 from the local monorepo checkout:

```
$ make test-example-1
```

### Releasing

- Manually create a new release on [GitHub](https://github.com/featurevisor/featurevisor-php/releases)
- Tag it with a prefix of `v`, like `v2.0.0`
- The Packagist workflow notifies [Packagist](https://packagist.org/packages/featurevisor/featurevisor-php) after the tag is pushed

## License

MIT © [Fahad Heylaal](https://fahad19.com)
