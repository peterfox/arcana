<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Laravel;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use PeterFox\Arcana\Contract\SkillLibraryInterface;
use PeterFox\Arcana\SkillMetadata;

/**
 * Laravel AI–compatible tool that lists available agent skills.
 *
 * Returns a JSON array of skill metadata for all (or filtered) skills.
 * Call this first to discover what capabilities are available before
 * loading one with {@see LoadSkillTool}.
 *
 * @example With Laravel AI
 *   use Laravel\Ai\Agent;
 *   use PeterFox\Arcana\Laravel\ListSkillsTool;
 *   use PeterFox\Arcana\Laravel\LoadSkillTool;
 *
 *   class SkillAgent extends Agent
 *   {
 *       public function tools(): array
 *       {
 *           return [
 *               app(ListSkillsTool::class),
 *               app(LoadSkillTool::class),
 *           ];
 *       }
 *   }
 */
final class ListSkillsTool implements Tool
{
    public function __construct(
        private readonly SkillLibraryInterface $library,
    ) {}

    #[\Override]
    public function description(): string
    {
        return 'List all available agent skills with metadata. '
            . 'Returns a JSON array of skills with their names, descriptions, tags, and trigger phrases. '
            . 'Call this first to discover what capabilities are available before loading one.';
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()
                ->description(
                    'Optional case-insensitive search term to filter skills by name, description, tags, or triggers. '
                    . 'Leave empty to list all skills.',
                ),
        ];
    }

    #[\Override]
    public function handle(Request $request): string
    {
        $filter = $request['filter'] ?? null;
        $skills = $this->library->listSkills(is_string($filter) && $filter !== '' ? $filter : null);

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
