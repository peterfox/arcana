<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Laravel;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use PeterFox\Arcana\Contract\SkillLibraryInterface;

/**
 * Laravel AI–compatible tool that loads the full content of a named skill.
 *
 * Returns the complete Markdown body and any bundled resource files for the
 * requested skill. Always call {@see ListSkillsTool} first to get the exact
 * skill name.
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
final class LoadSkillTool implements Tool
{
    public function __construct(
        private readonly SkillLibraryInterface $library,
    ) {}

    public function description(): string
    {
        return 'Load the complete Markdown content of a specific agent skill by its exact name. '
            . 'Returns the full skill instructions and any bundled reference documentation. '
            . 'Only call this after using list_skills to identify the correct skill name.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The exact skill name as returned by list_skills (e.g. "web-search").')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $skill = $this->library->loadSkill($request['name']);

        return $skill->fullContent(includeResources: true);
    }
}
