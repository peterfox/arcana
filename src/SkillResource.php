<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

/**
 * An immutable descriptor for a supplementary file resource bundled with
 * a skill (e.g. extended documentation, templates, reference data).
 *
 * Resources live inside the skill's directory and are loaded on demand
 * via {@see Skill::loadResource()}.
 *
 * Declared in SKILL.md frontmatter under `resources:`:
 *
 *   resources:
 *     - name: overview
 *       description: High-level overview of the skill
 *       path: resources/overview.md
 */
final class SkillResource
{
    public function __construct(
        /** Unique name used to retrieve this resource via Skill::loadResource(). */
        public readonly string $name,
        /** Short description of the resource's purpose. */
        public readonly string $description,
        /** Relative path from the skill directory (e.g. resources/overview.md). */
        public readonly string $path,
    ) {}
}
