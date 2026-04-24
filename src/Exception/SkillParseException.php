<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Exception;

/**
 * Thrown when a SKILL.md file cannot be parsed due to malformed YAML
 * frontmatter, missing required fields, or invalid structure.
 */
final class SkillParseException extends ArcanaException
{
    public function __construct(
        string $message,
        public readonly ?string $filePath = null,
        ?\Throwable $previous = null,
    ) {
        $context = $filePath !== null ? " (file: {$filePath})" : '';

        parent::__construct(
            message: $message . $context,
            previous: $previous,
        );
    }
}
