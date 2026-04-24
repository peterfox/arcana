<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tool;

use PeterFox\Arcana\Contract\SkillLibraryInterface;

/**
 * Invokable tool that loads the full content of a named skill.
 *
 * Compatible with Instructor PHP tool calling and any framework that
 * accepts PHP callables as tools. Returns the full skill Markdown body
 * (optionally with bundled resources appended) as a plain string.
 *
 * @example With Instructor PHP
 *   $tool = new LoadSkillTool($library);
 *   // Pass $tool as a callable to Instructor
 * @example Direct invocation
 *   $body = ($tool)('web-search');
 *   $body = ($tool)('web-search', includeResources: false);
 */
final class LoadSkillTool
{
    public function __construct(
        private readonly SkillLibraryInterface $library,
    ) {}

    /**
     * Load the full content of a skill by its exact name.
     *
     * @param string $name Exact skill name (as returned by list_skills).
     * @param bool $includeResources Whether to append bundled resource files.
     *
     * @return string Full Markdown content of the skill.
     */
    public function __invoke(string $name, bool $includeResources = true): string
    {
        $skill = $this->library->loadSkill($name);

        return $skill->fullContent($includeResources);
    }
}
