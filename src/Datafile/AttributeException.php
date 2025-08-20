<?php

declare(strict_types=1);

namespace Featurevisor\Datafile;


final class AttributeException extends \LogicException
{
    public static function createForNotFoundAttribute(string $name): self
    {
        return new self("Attribute '$name' not found");
    }

    public static function createForInvalidType(string $attribute, array $allowedTypes, string $givenType): self
    {
        $allowedTypesMsg = implode(', ', $allowedTypes);
        return new self("Attribute '$attribute' expected one of '$allowedTypesMsg'. Given '$givenType'");
    }
}
