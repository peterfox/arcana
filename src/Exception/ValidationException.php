<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Exception;

/**
 * Thrown when configuration or input fails validation, such as providing
 * a non-existent directory, an invalid skill name, or malformed metadata.
 */
final class ValidationException extends ArcanaException
{
    public function __construct(
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            previous: $previous,
        );
    }
}
