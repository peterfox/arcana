<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Laravel;

use PeterFox\Arcana\Contract\SkillLibraryInterface;

/**
 * Prism PHP–compatible tool that loads the full content of a named skill.
 *
 * Returns a Prism Tool instance ready for use in a Prism agent pipeline.
 * Invoke the class to get the configured Tool object:
 *
 * @example With Prism PHP
 *   use Prism\Prism\Prism;
 *
 *   $response = Prism::text()
 *       ->using('anthropic', 'claude-opus-4-6')
 *       ->withSystemPrompt('You are a helpful agent.')
 *       ->withPrompt($userMessage)
 *       ->withTools([
 *           app(ListSkillsTool::class)(),
 *           app(LoadSkillTool::class)(),
 *       ])
 *       ->generate();
 *
 * @see \PeterFox\Arcana\Laravel\ListSkillsTool
 */
final class LoadSkillTool
{
    public function __construct(
        private readonly SkillLibraryInterface $library,
    ) {}

    /**
     * Build and return a Prism Tool instance.
     *
     * @return \Prism\Prism\Tool
     */
    public function __invoke(): mixed
    {
        $library = $this->library;

        $toolClass = 'Prism\\Prism\\Tool';

        if (!class_exists($toolClass)) {
            throw new \LogicException(
                'prism-php/prism is required to use Arcana\'s Laravel tools. ' .
                'Run: composer require prism-php/prism'
            );
        }

        return $toolClass::as('load_skill')
            ->for(
                'Load the complete Markdown content of a specific agent skill by its exact name. ' .
                'Returns the full skill instructions and any bundled reference documentation. ' .
                'Only call this after using list_skills to identify the correct skill name.'
            )
            ->withStringParameter(
                name: 'name',
                description: 'The exact skill name as returned by list_skills (e.g. "web-search").',
            )
            ->using(function (string $name) use ($library): string {
                $skill = $library->loadSkill($name);

                return $skill->fullContent(includeResources: true);
            });
    }
}
