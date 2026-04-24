<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Laravel;

use PeterFox\Arcana\Contract\SkillLibraryInterface;
use PeterFox\Arcana\SkillMetadata;

/**
 * Prism PHP–compatible tool that lists available agent skills.
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
 * @see \PeterFox\Arcana\Laravel\LoadSkillTool
 */
final class ListSkillsTool
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

        // We use a string class reference to avoid a hard dependency on
        // prism-php/prism at the framework level. The package must be
        // installed by the application to use this class.
        $toolClass = 'Prism\\Prism\\Tool';

        if (!class_exists($toolClass)) {
            throw new \LogicException(
                'prism-php/prism is required to use Arcana\'s Laravel tools. '
                . 'Run: composer require prism-php/prism',
            );
        }

        return $toolClass::as('list_skills')
            ->for(
                'List all available agent skills with metadata. '
                . 'Returns a JSON array of skills with their names, descriptions, tags, and trigger phrases. '
                . 'Call this first to discover what capabilities are available before loading one.',
            )
            ->withStringParameter(
                name: 'filter',
                description: 'Optional case-insensitive search term to filter skills by name, description, tags, or triggers. Leave empty to list all skills.',
            )
            ->using(function (?string $filter = null) use ($library): string {
                $skills = $library->listSkills($filter ?: null);

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
            });
    }
}
