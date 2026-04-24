<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Exception;

/**
 * Thrown when a requested skill cannot be found in any registered directory.
 */
final class SkillNotFoundException extends ArcanaException
{
    public function __construct(
        public readonly string $skillName,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: "Skill '{$skillName}' was not found in any registered directory.",
            previous: $previous,
        );
    }
}
