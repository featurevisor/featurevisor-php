<?php

declare(strict_types=1);

namespace Featurevisor\Datafile;


use JsonException;

final class Content
{
    private string $schemaVersion;
    private string $revision;
    /** @var array<string, Segment> */
    private array $segments;

    /**
     * @throws JsonException
     */
    public static function createFromPath(string $path): self
    {
        if (file_exists($path)) {
            $content = file_get_contents($path);
        }

        return self::createFromArray(json_decode($content, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array{
     *  schemaVersion: string,
     *  revision: string,
     *  segments: array<string, array{
     *
     *  }>
     * } $data
     */
    private static function createFromArray(array $data): self
    {
        return new self(
            $data['schemaVersion'],
            $data['revision'],
            Segment::createManyFromArray($data['segments'])
        );
    }

    /**
     * @param array<string, Segment> $segments
     */
    public function __construct(string $schemaVersion, string $revision, array $segments)
    {
        $this->schemaVersion = $schemaVersion;
        $this->revision = $revision;
        $this->segments = $segments;
    }

    public function getSchemaVersion(): string
    {
        return $this->schemaVersion;
    }

    public function getRevision(): string
    {
        return $this->revision;
    }

    /**
     * @return array<string, Segment>
     */
    public function getSegments(): array
    {
        return $this->segments;
    }
}
