<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Exception;

/**
 * Thrown when a security violation is detected, such as path traversal
 * attempts in resource or script paths within a skill.
 */
final class SecurityException extends ArcanaException
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
