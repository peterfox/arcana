<?php

declare(strict_types=1);

namespace PeterFox\Arcana;

use PeterFox\Arcana\Contract\SkillLibraryInterface;
use PeterFox\Arcana\Contract\SkillPreprocessorInterface;
use PeterFox\Arcana\Tool\SkillsTool;
use Psr\SimpleCache\CacheInterface;

/**
 * Static entry point for the Arcana skill library.
 *
 * Provides convenient factory methods for bootstrapping a SkillLibrary
 * and its associated tools without requiring a dependency injection
 * container.
 *
 * @example Standalone usage
 *   $library = Arcana::create('/path/to/skills');
 *   $skills  = $library->listSkills();
 *   $skill   = $library->loadSkill('web-search');
 * @example With PSR-16 cache
 *   $library = Arcana::create(
 *       directories: ['/skills', '/vendor/skills'],
 *       cache: new RedisCache($redis),
 *   );
 * @example With tool dispatch
 *   $tool   = Arcana::tool($library);
 *   $result = $tool->dispatch('list_skills', []);
 */
final class Arcana
{
    /** Non-instantiable static factory. */
    private function __construct() {}

    /**
     * Create a new SkillLibrary instance.
     *
     * @param string|array<string> $directories One or more directories containing SKILL.md files.
     * @param CacheInterface|null $cache Optional PSR-16 cache. Defaults to NullCache.
     * @param SkillPreprocessorInterface|null $preprocessor Optional preprocessor pipeline.
     * @param int $cacheTtl Cache TTL in seconds (default: 3600).
     * @param string $cachePrefix Cache key prefix (default: 'arcana.').
     */
    public static function create(
        string|array $directories,
        ?CacheInterface $cache = null,
        ?SkillPreprocessorInterface $preprocessor = null,
        int $cacheTtl = 3600,
        string $cachePrefix = 'arcana.',
    ): SkillLibrary {
        return new SkillLibrary(
            directories: $directories,
            cache: $cache ?? new Cache\NullCache(),
            preprocessor: $preprocessor,
            cacheTtl: $cacheTtl,
            cachePrefix: $cachePrefix,
        );
    }

    /**
     * Create a SkillsTool wrapping the given library.
     *
     * The SkillsTool exposes list_skills and load_skill function definitions
     * compatible with OpenAI, Anthropic, and Instructor PHP tool calling.
     */
    public static function tool(SkillLibraryInterface $library): SkillsTool
    {
        return new SkillsTool($library);
    }
}
