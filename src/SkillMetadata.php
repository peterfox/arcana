<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

/**
 * Lightweight immutable value object holding a skill's frontmatter metadata.
 *
 * This is returned by {@see Contract\SkillLibraryInterface::listSkills()} for
 * fast, progressive disclosure — the body content is NOT loaded. Use it to
 * let an agent decide which skill to load without paying the I/O cost of
 * reading every skill file.
 */
final class SkillMetadata
{
    /**
     * @param  array<string>          $tags        Categorical tags for discovery.
     * @param  array<string>          $triggers    Natural-language phrases that indicate this skill is relevant.
     * @param  array<SkillResource>   $resources   Supplementary file resources bundled with the skill.
     * @param  array<SkillScript>     $scripts     Executable scripts for dynamic context injection.
     * @param  array<SkillReference>  $references  External reference links.
     * @param  string                 $filePath    Absolute path to the SKILL.md file on disk.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $version,
        public readonly ?string $author,
        public readonly array $tags,
        public readonly array $triggers,
        public readonly array $resources,
        public readonly array $scripts,
        public readonly array $references,
        public readonly string $filePath,
    ) {}

    /**
     * Returns the absolute path to the skill's root directory.
     */
    public function directory(): string
    {
        return dirname($this->filePath);
    }

    /**
     * Returns true if any tag matches the given string (case-insensitive).
     */
    public function hasTag(string $tag): bool
    {
        $tag = strtolower($tag);

        foreach ($this->tags as $t) {
            if (strtolower($t) === $tag) {
                return true;
            }
        }

        return false;
    }
}
