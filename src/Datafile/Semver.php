<?php

declare(strict_types=1);

namespace Featurevisor\Datafile;


use Exception;
use InvalidArgumentException;

final class Semver
{
    private const VALIDATION_REGEX = '/^[v^~<>=]*?(\d+)(?:\.([x*]|\d+)(?:\.([x*]|\d+)(?:\.([x*]|\d+))?(?:-([\da-z\-]+(?:\.[\da-z\-]+)*))?(?:\+[\da-z\-]+(?:\.[\da-z\-]+)*)?)?)?$/i';

    private string $value;
    private array $segments;

    /**
     * @param mixed $value
     * @throws InvalidArgumentException
     */
    public static function createFromMixed($value): self
    {
        if (is_string($value) === false) {
            throw new InvalidArgumentException('Invalid argument expected string');
        }

        return new self($value);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(string $value)
    {
        if (!preg_match(self::VALIDATION_REGEX, $value, $match)) {
            throw new InvalidArgumentException("Invalid argument not valid semver ('$value' received)");
        }

        array_shift($match);
        $this->value = $value;
        $this->segments = $match;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSegments(): array
    {
        return $this->segments;
    }
}
