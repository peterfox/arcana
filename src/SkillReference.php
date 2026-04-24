<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

/**
 * An immutable reference link declared in a skill's frontmatter.
 *
 * References provide additional reading material, API docs, or
 * canonical sources of truth for the skill's domain.
 */
final class SkillReference
{
    public function __construct(
        /** Human-readable title for the reference. */
        public readonly string $title,
        /** Fully-qualified URL. */
        public readonly string $url,
    ) {}
}
