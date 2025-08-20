<?php

declare(strict_types=1);

namespace Featurevisor\Tests\Datafile\Fixture;


use Symfony\Component\Yaml\Yaml;

final class SegmentFixture
{
    public static function everyone(): array
    {
        return Yaml::parseFile(__DIR__ . '/Segment/everyone.yaml');
    }

    public static function simpleCondition(): array
    {
        return Yaml::parseFile(__DIR__ . '/Segment/simple_condition.yaml');
    }

    public static function complexExpressions(): array
    {
        return Yaml::parseFile(__DIR__ . '/Segment/complex_expressions.yaml');
    }

    public static function switzerland(): array
    {
        return Yaml::parseFile(__DIR__ . '/Segment/switzerland.yaml');
    }
}
