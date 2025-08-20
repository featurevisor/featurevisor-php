<?php

namespace Featurevisor\Tests;

use DateTime;
use Featurevisor\Datafile\Conditions;
use PHPUnit\Framework\TestCase;

use Featurevisor\DatafileReader;
use function Featurevisor\createLogger;

class ConditionsTest extends TestCase {
    private $datafileReader;

    protected function setUp(): void {
        $logger = createLogger();
        $this->datafileReader = new DatafileReader([
            'datafile' => [
                'schemaVersion' => '2.0',
                'revision' => '1',
                'segments' => [],
                'features' => [],
            ],
            'logger' => $logger,
        ]);
    }

    public function testMatchAllViaStar() {
        self::assertTrue($this->datafileReader->allConditionsAreMatched('*', ['browser_type' => 'chrome']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched('blah', ['browser_type' => 'chrome']));
    }

    public function testOperatorEquals() {
        $conditions = [[ 'attribute' => 'browser_type', 'operator' => 'equals', 'value' => 'chrome' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'firefox']));
    }

    public function testOperatorEqualsDotSeparated() {
        $conditions = [[ 'attribute' => 'browser.type', 'operator' => 'equals', 'value' => 'chrome' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser' => ['type' => 'chrome']]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser' => ['type' => 'firefox']]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser' => ['blah' => 'firefox']]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser' => 'firefox']));
    }

    public function testOperatorNotEquals() {
        $conditions = [[ 'attribute' => 'browser_type', 'operator' => 'notEquals', 'value' => 'chrome' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'firefox']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome']));
    }

    public function testOperatorExists() {
        $conditions = [[ 'attribute' => 'browser_type', 'operator' => 'exists' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'firefox']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['not_browser_type' => 'chrome']));
    }

    public function testOperatorExistsDotSeparated() {
        $conditions = [[ 'attribute' => 'browser.name', 'operator' => 'exists' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser' => ['name' => 'chrome']]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser' => 'chrome']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser' => ['version' => '1.2.3']]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '1.2.3']));
    }

    public function testOperatorNotExists() {
        $conditions = [[ 'attribute' => 'name', 'operator' => 'notExists' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['not_name' => 'Hello World']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['not_name' => 'Hello Universe']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hi World']));
    }

    public function testOperatorNotExistsDotSeparated() {
        $conditions = [[ 'attribute' => 'browser.name', 'operator' => 'notExists' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser' => ['not_name' => 'Hello World']]));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['not_name' => 'Hello Universe']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser' => ['name' => 'Chrome']]));
    }

    public function testOperatorEndsWith() {
        $conditions = [[ 'attribute' => 'name', 'operator' => 'endsWith', 'value' => 'World' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hello World']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hi World']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hi Universe']));
    }

    public function testOperatorIncludes() {
        $conditions = [[ 'attribute' => 'permissions', 'operator' => 'includes', 'value' => 'write' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['permissions' => ['read', 'write']]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['permissions' => ['read']]));
    }

    public function testOperatorNotIncludes() {
        $conditions = [[ 'attribute' => 'permissions', 'operator' => 'notIncludes', 'value' => 'write' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['permissions' => ['read', 'admin']]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['permissions' => ['read', 'write', 'admin']]));
    }

    public function testOperatorContains() {
        $conditions = [[ 'attribute' => 'name', 'operator' => 'contains', 'value' => 'Hello' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hello World']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Yo! Hello!']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hi World']));
    }

    public function testOperatorNotContains() {
        $conditions = [[ 'attribute' => 'name', 'operator' => 'notContains', 'value' => 'Hello' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hi World']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hello World']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Yo! Hello!']));
    }

    public function testOperatorMatches() {
        $conditions = [[ 'attribute' => 'name', 'operator' => 'matches', 'value' => '^[a-zA-Z]{2,}$' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hello']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Helloooooo']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hello World']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hell123']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => '123']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 123]));
    }

    public function testOperatorMatchesWithRegexFlags() {
        $conditions = [[ 'attribute' => 'name', 'operator' => 'matches', 'value' => '^[a-zA-Z]{2,}$', 'regexFlags' => 'i' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hello']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Helloooooo']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hello World']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hell123']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => '123']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 123]));
    }

    public function testOperatorNotMatches() {
        $conditions = [[ 'attribute' => 'name', 'operator' => 'notMatches', 'value' => '^[a-zA-Z]{2,}$' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hi World']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['name' => '123']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hello']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hellooooooo']));
    }

    public function testOperatorNotMatchesWithRegexFlags() {
        $conditions = [[ 'attribute' => 'name', 'operator' => 'notMatches', 'value' => '^[a-zA-Z]{2,}$', 'regexFlags' => 'i' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hi World']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['name' => '123']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hello']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['name' => 'Hellooooooo']));
    }

    public function testOperatorIn() {
        $conditions = [[ 'attribute' => 'browser_type', 'operator' => 'in', 'value' => ['chrome', 'firefox'] ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'firefox']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'edge']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'safari']));
    }

    public function testOperatorNotIn() {
        $conditions = [[ 'attribute' => 'browser_type', 'operator' => 'notIn', 'value' => ['chrome', 'firefox'] ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'edge']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'safari']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'firefox']));
    }

    public function testOperatorGreaterThan() {
        $conditions = [[ 'attribute' => 'age', 'operator' => 'greaterThan', 'value' => 18 ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['age' => 19]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['age' => 17]));
    }

    public function testOperatorGreaterThanOrEquals() {
        $conditions = [[ 'attribute' => 'age', 'operator' => 'greaterThanOrEquals', 'value' => 18 ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['age' => 18]));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['age' => 19]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['age' => 17]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['age' => 16]));
    }

    public function testOperatorLessThan() {
        $conditions = [[ 'attribute' => 'age', 'operator' => 'lessThan', 'value' => 18 ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['age' => 17]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['age' => 19]));
    }

    public function testOperatorLessThanOrEquals() {
        $conditions = [[ 'attribute' => 'age', 'operator' => 'lessThanOrEquals', 'value' => 18 ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['age' => 17]));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['age' => 18]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['age' => 19]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['age' => 20]));
    }

    public function testOperatorSemverEquals() {
        $conditions = [[ 'attribute' => 'version', 'operator' => 'semverEquals', 'value' => '1.0.0' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '1.0.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '2.0.0']));
    }

    public function testOperatorSemverNotEquals() {
        $conditions = [[ 'attribute' => 'version', 'operator' => 'semverNotEquals', 'value' => '1.0.0' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '2.0.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '1.0.0']));
    }

    public function testOperatorSemverGreaterThan() {
        $conditions = [[ 'attribute' => 'version', 'operator' => 'semverGreaterThan', 'value' => '1.0.0' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '2.0.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '0.9.0']));
    }

    public function testOperatorSemverGreaterThanOrEquals() {
        $conditions = [[ 'attribute' => 'version', 'operator' => 'semverGreaterThanOrEquals', 'value' => '1.0.0' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '1.0.0']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '2.0.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '0.9.0']));
    }

    public function testOperatorSemverLessThan() {
        $conditions = [[ 'attribute' => 'version', 'operator' => 'semverLessThan', 'value' => '1.0.0' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '0.9.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '1.1.0']));
    }

    public function testOperatorSemverLessThanOrEquals() {
        $conditions = [[ 'attribute' => 'version', 'operator' => 'semverLessThanOrEquals', 'value' => '1.0.0' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '1.0.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['version' => '1.1.0']));
    }

    public function testOperatorBefore() {
        $conditions = [[ 'attribute' => 'date', 'operator' => 'before', 'value' => '2023-05-13T16:23:59Z' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['date' => '2023-05-12T00:00:00Z']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['date' => new DateTime('2023-05-12T00:00:00Z')]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['date' => '2023-05-14T00:00:00Z']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['date' => new DateTime('2023-05-14T00:00:00Z')]));
    }

    public function testOperatorAfter() {
        $conditions = [[ 'attribute' => 'date', 'operator' => 'after', 'value' => '2023-05-13T16:23:59Z' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['date' => '2023-05-14T00:00:00Z']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['date' => new DateTime('2023-05-14T00:00:00Z')]));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['date' => '2023-05-12T00:00:00Z']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['date' => new DateTime('2023-05-12T00:00:00Z')]));
    }

    public function testSimpleConditionVariants() {
        $conditions = [[ 'attribute' => 'browser_type', 'operator' => 'equals', 'value' => 'chrome' ]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions[0], ['browser_type' => 'chrome']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched([], ['browser_type' => 'chrome']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched([], ['browser_type' => 'firefox']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome', 'browser_version' => '1.0']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched([
            ['attribute' => 'browser_type', 'operator' => 'equals', 'value' => 'chrome'],
            ['attribute' => 'browser_version', 'operator' => 'equals', 'value' => '1.0'],
        ], ['browser_type' => 'chrome', 'browser_version' => '1.0', 'foo' => 'bar']));
    }

    public function testAndCondition() {
        $conditions = [[ 'and' => [
            [ 'attribute' => 'browser_type', 'operator' => 'equals', 'value' => 'chrome' ],
        ]]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'firefox']));

        $conditions = [[ 'and' => [
            [ 'attribute' => 'browser_type', 'operator' => 'equals', 'value' => 'chrome' ],
            [ 'attribute' => 'browser_version', 'operator' => 'equals', 'value' => '1.0' ],
        ]]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome', 'browser_version' => '1.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome']));
    }

    public function testOrCondition() {
        $conditions = [[ 'or' => [
            [ 'attribute' => 'browser_type', 'operator' => 'equals', 'value' => 'chrome' ],
        ]]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome']));

        $conditions = [[ 'or' => [
            [ 'attribute' => 'browser_type', 'operator' => 'equals', 'value' => 'chrome' ],
            [ 'attribute' => 'browser_version', 'operator' => 'equals', 'value' => '1.0' ],
        ]]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_version' => '1.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'firefox']));
    }

    public function testNotCondition() {
        $conditions = [[ 'not' => [
            [ 'attribute' => 'browser_type', 'operator' => 'equals', 'value' => 'chrome' ],
            [ 'attribute' => 'browser_version', 'operator' => 'equals', 'value' => '1.0' ],
        ]]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'firefox', 'browser_version' => '2.0']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome', 'browser_version' => '2.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome', 'browser_version' => '1.0']));
    }

    public function testNestedConditions() {
        // OR inside AND
        $conditions = [[ 'and' => [
            [ 'attribute' => 'browser_type', 'operator' => 'equals', 'value' => 'chrome' ],
            [ 'or' => [
                [ 'attribute' => 'browser_version', 'operator' => 'equals', 'value' => '1.0' ],
                [ 'attribute' => 'browser_version', 'operator' => 'equals', 'value' => '2.0' ],
            ]],
        ]]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome', 'browser_version' => '1.0']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome', 'browser_version' => '2.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome', 'browser_version' => '3.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_version' => '2.0']));

        // plain, then OR inside AND
        $conditions = [
            [ 'attribute' => 'country', 'operator' => 'equals', 'value' => 'nl' ],
            [ 'and' => [
                [ 'attribute' => 'browser_type', 'operator' => 'equals', 'value' => 'chrome' ],
                [ 'or' => [
                    [ 'attribute' => 'browser_version', 'operator' => 'equals', 'value' => '1.0' ],
                    [ 'attribute' => 'browser_version', 'operator' => 'equals', 'value' => '2.0' ],
                ]],
            ]],
        ];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['country' => 'nl', 'browser_type' => 'chrome', 'browser_version' => '1.0']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['country' => 'nl', 'browser_type' => 'chrome', 'browser_version' => '2.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome', 'browser_version' => '3.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['country' => 'us', 'browser_version' => '2.0']));

        // AND inside OR
        $conditions = [[ 'or' => [
            [ 'attribute' => 'browser_type', 'operator' => 'equals', 'value' => 'chrome' ],
            [ 'and' => [
                [ 'attribute' => 'device_type', 'operator' => 'equals', 'value' => 'mobile' ],
                [ 'attribute' => 'orientation', 'operator' => 'equals', 'value' => 'portrait' ],
            ]],
        ]]];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'chrome', 'browser_version' => '2.0']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'firefox', 'device_type' => 'mobile', 'orientation' => 'portrait']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'firefox', 'browser_version' => '2.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'firefox', 'device_type' => 'desktop']));

        // plain, then AND inside OR
        $conditions = [
            [ 'attribute' => 'country', 'operator' => 'equals', 'value' => 'nl' ],
            [ 'or' => [
                [ 'attribute' => 'browser_type', 'operator' => 'equals', 'value' => 'chrome' ],
                [ 'and' => [
                    [ 'attribute' => 'device_type', 'operator' => 'equals', 'value' => 'mobile' ],
                    [ 'attribute' => 'orientation', 'operator' => 'equals', 'value' => 'portrait' ],
                ]],
            ]],
        ];
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['country' => 'nl', 'browser_type' => 'chrome', 'browser_version' => '2.0']));
        self::assertTrue($this->datafileReader->allConditionsAreMatched($conditions, ['country' => 'nl', 'browser_type' => 'firefox', 'device_type' => 'mobile', 'orientation' => 'portrait']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['browser_type' => 'firefox', 'browser_version' => '2.0']));
        self::assertFalse($this->datafileReader->allConditionsAreMatched($conditions, ['country' => 'de', 'browser_type' => 'firefox', 'device_type' => 'desktop']));
    }

    public function testEuropeConditions()
    {
        $conditions = Conditions::createFromMixed([
            [
                'attribute' => 'continent',
                'operator' => 'equals',
                'value' => 'europe',
            ],
            [
                'attribute' => 'country',
                'operator' => 'notIn',
                'value' => ['gb'],
            ]
        ]);

        self::assertFalse($conditions->isSatisfiedBy(['country' => ['foo' => 'bar']]));
    }
}
