<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tool;

use PeterFox\Arcana\Contract\SkillLibraryInterface;
use PeterFox\Arcana\SkillMetadata;

/**
 * Invokable tool that lists available skills with lightweight metadata.
 *
 * Compatible with Instructor PHP tool calling and any framework that
 * accepts PHP callables as tools. The return type is a JSON string so it
 * can be sent directly as a tool result in any LLM conversation.
 *
 * @example With Instructor PHP
 *   $tool = new ListSkillsTool($library);
 *   // Pass $tool as a callable to Instructor
 * @example Direct invocation
 *   $json = ($tool)('search');  // filtered
 *   $json = ($tool)();          // all skills
 */
final class ListSkillsTool
{
    public function __construct(
        private readonly SkillLibraryInterface $library,
    ) {}

    /**
     * List available skills, optionally filtered by a search term.
     *
     * @param string|null $filter Case-insensitive search term.
     *
     * @return string JSON-encoded skill list.
     */
    public function __invoke(?string $filter = null): string
    {
        $skills = $this->library->listSkills($filter);

        return json_encode([
            'count' => count($skills),
            'skills' => array_map(
                fn(SkillMetadata $m) => [
                    'name' => $m->name,
                    'description' => $m->description,
                    'version' => $m->version,
                    'tags' => $m->tags,
                    'triggers' => $m->triggers,
                ],
                $skills,
            ),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
