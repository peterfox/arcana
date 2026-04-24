<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Skill Directories
    |--------------------------------------------------------------------------
    |
    | A list of directories Arcana will recursively scan for SKILL.md files.
    | Skills are discovered from any subdirectory at any depth.
    |
    | You can register vendor skill packs here alongside your own:
    |
    |   'directories' => [
    |       base_path('skills'),
    |       base_path('vendor/acme/skills/src/skills'),
    |   ],
    |
    */
    'directories' => [
        base_path('skills'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Arcana caches parsed Skill objects using your application's PSR-16 cache.
    | Set 'enabled' to false to disable caching entirely (useful in development).
    |
    | 'store'  - The Laravel cache store to use. Null uses the default store.
    | 'ttl'    - Cache TTL in seconds. Default: 3600 (1 hour).
    | 'prefix' - Key prefix for all Arcana cache entries.
    |
    */
    'cache' => [
        'enabled' => (bool) env('ARCANA_CACHE_ENABLED', true),
        'store' => env('ARCANA_CACHE_STORE'),
        'ttl' => (int) env('ARCANA_CACHE_TTL', 3600),
        'prefix' => (string) env('ARCANA_CACHE_PREFIX', 'arcana.'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Skill Preprocessor
    |--------------------------------------------------------------------------
    |
    | An optional class implementing SkillPreprocessorInterface that transforms
    | skills before they are returned or cached. Set to null to disable.
    |
    | Use cases include variable interpolation, context injection, or
    | environment-specific content modification.
    |
    |   'preprocessor' => App\Skills\MyPreprocessor::class,
    |
    */
    'preprocessor' => null,

];
