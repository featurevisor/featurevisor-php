<?php

declare(strict_types=1);

namespace Featurevisor\Datafile;


use Featurevisor\Datafile\Conditions\ConditionInterface;

final class Segment
{
    private bool $archived;
    /**
     * @var Condition|array<Condition>|string
     */
    private $conditions; // Can be Condition, List<Condition>, or String
    private string $description;

    /**
     * @param array<array{
     *      archived: bool,
     *      conditions: array{}|list<array{}>|string,
     *      description: string
     *  }> $segments
     * @return array<Segment>
     */
    public static function createManyFromArray(array $segments): array
    {
        return array_map(
            static fn(array $segment): Segment => self::createFromArray($segment),
            $segments
        );
    }

    /**
     * @param array{
     *     archived: bool,
     *     conditions: array{}|list<array{}>|string,
     *     description: string
     * } $data
     */
    public static function createFromArray(array $data): self
    {
        return new self(
            $data['description'] ?? '',
            Conditions::createFromMixed($data['conditions']),
            $data['archived'] ?? false
        );
    }

    public function __construct(
        string $description,
        ConditionInterface $conditions,
        bool $archived = false
    )
    {
        $this->archived = $archived;
        $this->conditions = $conditions;
        $this->description = $description;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return Condition|array<Condition>|string
     */
    public function getConditions()
    {
        return $this->conditions;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

}
