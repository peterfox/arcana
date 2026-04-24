<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tool;

use PeterFox\Arcana\Contract\SkillLibraryInterface;

/**
 * Unified tool façade exposing list_skills and load_skill as a single
 * object with:
 *
 *   - OpenAI / Anthropic compatible JSON function definitions ({@see self::definitions()})
 *   - A dispatch method for routing tool call results ({@see self::dispatch()})
 *   - Direct method access ({@see self::listSkills()}, {@see self::loadSkill()})
 *
 * This class is the recommended integration point for frameworks that work
 * with raw function definition arrays (e.g. direct OpenAI PHP SDK, custom
 * LLM clients). For Laravel + Prism PHP integration, use the dedicated
 * {@see \PeterFox\Arcana\Laravel\ListSkillsTool} and
 * {@see \PeterFox\Arcana\Laravel\LoadSkillTool} instead.
 *
 * @example OpenAI PHP SDK
 *   $tool = Arcana::tool($library);
 *
 *   $response = $openai->chat()->create([
 *       'model' => 'gpt-4o',
 *       'messages' => [['role' => 'user', 'content' => $prompt]],
 *       'tools' => $tool->definitions(),
 *   ]);
 *
 *   // In your tool-call handler loop:
 *   $result = $tool->dispatch($call->function->name, json_decode($call->function->arguments, true));
 */
final class SkillsTool
{
    private readonly ListSkillsTool $listSkillsTool;

    private readonly LoadSkillTool $loadSkillTool;

    public function __construct(SkillLibraryInterface $library)
    {
        $this->listSkillsTool = new ListSkillsTool($library);
        $this->loadSkillTool = new LoadSkillTool($library);
    }

    // -------------------------------------------------------------------------
    // Direct API
    // -------------------------------------------------------------------------

    /**
     * Return a JSON-encoded list of available skills.
     *
     * @param  string|null  $filter  Optional case-insensitive search term.
     */
    public function listSkills(?string $filter = null): string
    {
        return ($this->listSkillsTool)($filter);
    }

    /**
     * Return the full Markdown content of a named skill.
     *
     * @param  string  $name             Exact skill name.
     * @param  bool    $includeResources Whether to append bundled resources.
     */
    public function loadSkill(string $name, bool $includeResources = true): string
    {
        return ($this->loadSkillTool)($name, $includeResources);
    }

    // -------------------------------------------------------------------------
    // Tool dispatch
    // -------------------------------------------------------------------------

    /**
     * Route an LLM tool call to the correct handler.
     *
     * @param  string               $function   The function name (list_skills | load_skill).
     * @param  array<string, mixed> $arguments  Decoded tool call arguments.
     * @return string               Tool result as a string (to be sent back to the model).
     *
     * @throws \InvalidArgumentException When an unknown function name is given.
     */
    public function dispatch(string $function, array $arguments): string
    {
        return match ($function) {
            'list_skills' => $this->listSkills(
                filter: isset($arguments['filter']) ? (string) $arguments['filter'] : null,
            ),
            'load_skill' => $this->loadSkill(
                name: isset($arguments['name'])
                    ? (string) $arguments['name']
                    : throw new \InvalidArgumentException("Missing required argument 'name' for load_skill."),
                includeResources: isset($arguments['include_resources'])
                    ? (bool) $arguments['include_resources']
                    : true,
            ),
            default => throw new \InvalidArgumentException(
                "Unknown tool function '{$function}'. Available: list_skills, load_skill."
            ),
        };
    }

    // -------------------------------------------------------------------------
    // Function definitions
    // -------------------------------------------------------------------------

    /**
     * Return OpenAI / Anthropic compatible tool definition arrays.
     *
     * Pass this to any LLM client that accepts function/tool definitions.
     * The format follows the OpenAI Chat Completions tools schema and is
     * also accepted by the Anthropic API.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_skills',
                    'description' => implode(' ', [
                        'List all available agent skills with metadata.',
                        'Returns a JSON array containing skill names, descriptions, tags, and trigger phrases.',
                        'Call this first to discover what capabilities are available.',
                        'Use the filter parameter to narrow results by keyword.',
                    ]),
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'filter' => [
                                'type' => 'string',
                                'description' => 'Optional case-insensitive search term to filter skills by name, description, tags, or triggers.',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'load_skill',
                    'description' => implode(' ', [
                        'Load the complete Markdown content of a specific agent skill by its exact name.',
                        'Returns the full skill instructions and any bundled reference documentation.',
                        'Only call this after using list_skills to identify the correct skill name.',
                    ]),
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'The exact skill name as returned by list_skills (e.g. "web-search").',
                            ],
                            'include_resources' => [
                                'type' => 'boolean',
                                'description' => 'Whether to include bundled resource files in the response. Defaults to true.',
                            ],
                        ],
                        'required' => ['name'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Return Anthropic-style tool definitions (Claude API format).
     *
     * @return array<int, array<string, mixed>>
     */
    public function anthropicDefinitions(): array
    {
        return array_map(
            fn(array $def) => [
                'name' => $def['function']['name'],
                'description' => $def['function']['description'],
                'input_schema' => $def['function']['parameters'],
            ],
            $this->definitions()
        );
    }
}
