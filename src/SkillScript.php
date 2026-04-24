<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

/**
 * An immutable descriptor for an executable script bundled with a skill.
 *
 * Scripts can be executed by a {@see Contract\SkillPreprocessorInterface}
 * to inject dynamic context into the skill body at load time.
 *
 * Declared in SKILL.md frontmatter under `scripts:`:
 *
 *   scripts:
 *     - name: fetch-context
 *       description: Fetches current deployment context
 *       path: scripts/fetch-context.php
 *       language: php
 *
 * @see \PeterFox\Arcana\Contract\SkillPreprocessorInterface
 */
final class SkillScript
{
    public function __construct(
        /** Unique name identifying this script. */
        public readonly string $name,
        /** Short description of what the script does. */
        public readonly string $description,
        /** Relative path from the skill directory (e.g. scripts/fetch-context.php). */
        public readonly string $path,
        /** Language identifier (e.g. 'php', 'bash', 'python'). */
        public readonly string $language,
    ) {}
}
