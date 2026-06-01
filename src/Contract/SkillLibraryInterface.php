<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Contract;

use PeterFox\Arcana\Exception\SkillNotFoundException;
use PeterFox\Arcana\Exception\ValidationException;
use PeterFox\Arcana\Skill;
use PeterFox\Arcana\SkillMetadata;

/**
 * Contract for a skill library that discovers and serves AI agent skills.
 *
 * Implementations must support progressive disclosure:
 *   - listSkills() returns lightweight metadata only
 *   - loadSkill() fetches full content on demand
 */
interface SkillLibraryInterface
{
    /**
     * Maximum byte length permitted for the listSkills() filter string.
     *
     * Filter strings arrive from LLM tool-call arguments and may be
     * arbitrarily long. This cap prevents unbounded str_contains() calls
     * across every skill's metadata fields.
     */
    public const int MAX_FILTER_LENGTH = 256;

    /**
     * List all available skills, optionally filtered by a search term.
     *
     * Returns lightweight metadata only — does not load skill body content.
     * Filtering matches against name, description, tags, and triggers.
     *
     * @param string|null $filter Optional case-insensitive search term. Must be
     *                            at most {@see self::MAX_FILTER_LENGTH} bytes.
     *
     * @throws ValidationException When the filter exceeds MAX_FILTER_LENGTH.
     *
     * @return array<SkillMetadata>
     */
    public function listSkills(?string $filter = null): array;

    /**
     * Load the complete content of a skill by its exact name.
     *
     * Skill body content is loaded on demand (progressive disclosure).
     *
     * @param string $name Exact skill name (lowercase letters, numbers, hyphens).
     *
     * @throws SkillNotFoundException When the skill does not exist.
     * @throws ValidationException When the name contains invalid characters.
     */
    public function loadSkill(string $name): Skill;

    /**
     * Check whether a skill with the given name exists.
     */
    public function hasSkill(string $name): bool;
}
