# Arcana

**Dynamic AI Agent Skill Loader — Extend agent knowledge on demand with progressive disclosure.**

[![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Packagist](https://img.shields.io/packagist/v/peterfox/arcana)](https://packagist.org/packages/peterfox/arcana)

Arcana is a lightweight, production-ready PHP library for discovering and serving AI agent skills from the filesystem. It implements the open **Agent Skills specification** (SKILL.md) and follows a progressive disclosure pattern: agents first see lightweight metadata, then load full skill content only when needed.

---

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [The SKILL.md Format](#the-skillmd-format)
- [Core API](#core-api)
- [Tool Calling](#tool-calling)
- [Laravel Integration](#laravel-integration)
- [Laravel AI Integration Guide](#laravel-ai-integration-guide)
- [Security](#security)
- [Caching](#caching)
- [Skill Preprocessors](#skill-preprocessors)
- [Testing](#testing)
- [Exception Reference](#exception-reference)
- [Next Steps / Roadmap](#next-steps--roadmap)

---

## Features

- **Progressive disclosure** — `listSkills()` returns metadata only; `loadSkill()` fetches bodies on demand
- **Multi-directory** — merge skills from multiple directories (your own + vendor packages)
- **OpenAI / Anthropic / Instructor compatible** — built-in function definitions for tool calling
- **Laravel bridge** — service provider, facade, `laravel/ai` tools, Artisan commands, auto-discovery
- **PSR-16 caching** — plug in any cache adapter; ships with a zero-overhead NullCache
- **Preprocessor pipeline** — inject dynamic context into skills before serving them
- **Path traversal protected** — all resource and script paths are strictly validated
- **PHP 8.4+ strict types** — immutable value objects, `readonly`, `final`, PHPStan-ready

---

## Installation

```bash
composer require peterfox/arcana
```

**Requirements:**
- PHP 8.4+
- `symfony/yaml` ^7.0 (auto-installed)
- `psr/simple-cache` ^3.0 (auto-installed)

For Laravel integration with `laravel/ai`:

```bash
composer require peterfox/arcana laravel/ai
```

---

## Quick Start

### 1. Create a skill directory

```
skills/
  web-search/
    SKILL.md
  code-review/
    SKILL.md
    resources/
      style-guide.md
```

### 2. Write a SKILL.md file

```markdown
---
name: web-search
description: Search the web for current information.
version: 1.0.0
author: Your Name
tags:
  - search
  - web
triggers:
  - search the web
  - find online
---

# Web Search Skill

Use this skill when you need up-to-date information from the internet.

## Instructions

1. Formulate a precise search query
2. Execute the search and evaluate sources
3. Synthesise results and cite sources
```

### 3. Use it in PHP

```php
use PeterFox\Arcana\Arcana;

$library = Arcana::create('/path/to/skills');

// Progressive disclosure — metadata only
$skills = $library->listSkills();
foreach ($skills as $metadata) {
    echo "{$metadata->name}: {$metadata->description}\n";
}

// Load full content on demand
$skill = $library->loadSkill('web-search');
echo $skill->body;

// Filter by keyword
$searchSkills = $library->listSkills('search');
```

---

## The SKILL.md Format

Every skill is a directory containing a `SKILL.md` file with a YAML frontmatter block followed by Markdown body content.

```markdown
---
name: skill-name               # required: lowercase letters, digits, hyphens
description: Short summary     # required: used for discovery
version: 1.0.0                 # optional: semver, default "1.0.0"
author: Your Name              # optional

tags:                          # optional: used for filtering
  - tag1
  - tag2

triggers:                      # optional: natural-language phrases for discovery
  - when the user asks about X
  - phrase that indicates this skill

resources:                     # optional: supplementary files
  - name: overview
    description: Background docs
    path: resources/overview.md

scripts:                       # optional: dynamic context scripts
  - name: fetch-context
    description: Fetches runtime context
    path: scripts/fetch-context.php
    language: php

references:                    # optional: external links
  - title: Official Docs
    url: https://example.com/docs
---

# Skill Title

Full Markdown instructions here. This is what the agent reads.
```

### Naming Rules

Skill names must:
- Start with a lowercase letter (`a–z`)
- Contain only lowercase letters, digits (`0–9`), and hyphens (`-`)
- Be 1–64 characters

Valid: `web-search`, `code-review`, `my-skill-v2`
Invalid: `WebSearch`, `my skill`, `my/skill`

---

## Core API

### `SkillLibrary`

```php
use PeterFox\Arcana\Arcana;

// Simple factory
$library = Arcana::create('/path/to/skills');

// Full options
$library = Arcana::create(
    directories: ['/skills', '/vendor/my-pack/skills'],
    cache: $myPsr16Cache,
    preprocessor: $myPreprocessor,
    cacheTtl: 7200,
    cachePrefix: 'myapp.',
);
```

#### `listSkills(?string $filter = null): array<SkillMetadata>`

Returns lightweight metadata for all (or filtered) skills. Does **not** load body content.

```php
$all      = $library->listSkills();           // all skills
$filtered = $library->listSkills('search');   // name / description / tags / triggers match
```

#### `loadSkill(string $name): Skill`

Loads the full skill including body and resources.

```php
$skill = $library->loadSkill('web-search');

echo $skill->body;                     // raw Markdown body
echo $skill->fullContent();            // body + appended resources
echo $skill->fullContent(false);       // body only, no resources
echo $skill->loadResource('overview'); // single resource file content
```

#### `hasSkill(string $name): bool`

Check existence without throwing.

```php
if ($library->hasSkill('web-search')) { ... }
```

---

## Tool Calling

`SkillsTool` provides `list_skills` and `load_skill` in formats compatible with OpenAI, Anthropic, and Instructor PHP.

```php
use PeterFox\Arcana\Arcana;

$tool = Arcana::tool($library);

// ─── OpenAI / compatible SDKs ─────────────────────────────────────────────
$response = $openai->chat()->create([
    'model'    => 'gpt-4o',
    'messages' => [['role' => 'user', 'content' => $userMessage]],
    'tools'    => $tool->definitions(),           // OpenAI format
]);

// Handle tool calls in the response loop:
foreach ($response->choices[0]->message->toolCalls as $call) {
    $result = $tool->dispatch(
        $call->function->name,
        json_decode($call->function->arguments, true)
    );
    // Add $result back to conversation as a tool message
}

// ─── Anthropic (Claude) ────────────────────────────────────────────────────
$tools = $tool->anthropicDefinitions();   // Anthropic `tools` array format

// ─── Direct calls ──────────────────────────────────────────────────────────
$json = $tool->listSkills('search');
$body = $tool->loadSkill('web-search');
```

### Instructor PHP

Use the individual invokable tools for Instructor's callable-based tool registration:

```php
use PeterFox\Arcana\Tool\ListSkillsTool;
use PeterFox\Arcana\Tool\LoadSkillTool;

$listTool = new ListSkillsTool($library);
$loadTool = new LoadSkillTool($library);

// Use as callables with Instructor PHP
$instructor->withTool('list_skills', $listTool, 'List available skills');
$instructor->withTool('load_skill', $loadTool, 'Load a skill by name');
```

---

## Laravel Integration

### Service Provider

Auto-registered via Laravel package discovery. Nothing to do.

Publish the config file:

```bash
php artisan vendor:publish --tag=arcana-config
```

This creates `config/arcana.php`:

```php
return [
    'directories' => [
        base_path('skills'),
    ],
    'cache' => [
        'enabled' => env('ARCANA_CACHE_ENABLED', true),
        'store'   => env('ARCANA_CACHE_STORE'),         // null = default store
        'ttl'     => env('ARCANA_CACHE_TTL', 3600),
        'prefix'  => env('ARCANA_CACHE_PREFIX', 'arcana.'),
    ],
    'preprocessor' => null,   // class FQN or null
];
```

### Facade

```php
use PeterFox\Arcana\Laravel\Facades\Arcana;

$skills = Arcana::listSkills('search');
$skill  = Arcana::loadSkill('web-search');
```

### Dependency Injection

```php
use PeterFox\Arcana\Contract\SkillLibraryInterface;

class MyController
{
    public function __construct(
        private readonly SkillLibraryInterface $skills,
    ) {}
}
```

### Artisan Commands

```bash
# List all skills
php artisan arcana:list

# Filter by keyword
php artisan arcana:list --filter=search

# JSON output (for scripting)
php artisan arcana:list --json

# Show a specific skill
php artisan arcana:show web-search

# Show only the Markdown body
php artisan arcana:show web-search --body

# Show as JSON
php artisan arcana:show web-search --json
```

---

## Laravel AI Integration Guide

Arcana ships with first-class support for **[Laravel AI](https://laravel.com/docs/ai)** (`laravel/ai`), Laravel's official AI SDK.

### Installation

```bash
composer require peterfox/arcana laravel/ai
```

### How It Works

`ListSkillsTool` and `LoadSkillTool` implement `Laravel\Ai\Contracts\Tool`. Laravel AI automatically invokes them when a model decides to call them. The tools handle the skill library interaction; your agent class just declares them.

### Basic Agent

```php
<?php

declare(strict_types=1);

namespace App\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use PeterFox\Arcana\Laravel\ListSkillsTool;
use PeterFox\Arcana\Laravel\LoadSkillTool;

#[Provider('anthropic')]
#[Model('claude-opus-4-6')]
final class SkillAwareAgent implements Agent, HasTools
{
    use Promptable;

    public function __construct(
        private readonly ListSkillsTool $listSkills,
        private readonly LoadSkillTool $loadSkill,
    ) {}

    public function instructions(): string
    {
        return <<<'PROMPT'
            You are a helpful, knowledgeable AI assistant.

            You have access to a library of specialised skills that extend your capabilities.
            Before responding to a request, always:

            1. Call list_skills to discover available capabilities
            2. If a relevant skill exists, call load_skill to get its full instructions
            3. Follow the skill's instructions when formulating your response

            Be concise, accurate, and transparent about which skill you are using.
            PROMPT;
    }

    public function tools(): iterable
    {
        return [$this->listSkills, $this->loadSkill];
    }
}
```

### Calling the Agent

```php
// Resolve from the container (auto-wires tool dependencies)
$agent = SkillAwareAgent::make();

$response = $agent->prompt('Help me search the web for the latest PHP news');

echo $response->text;
```

### Full Controller Example

```php
<?php

namespace App\Http\Controllers;

use App\Agents\SkillAwareAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AgentController extends Controller
{
    public function __invoke(SkillAwareAgent $agent, Request $request): JsonResponse
    {
        $message = $request->validate(['message' => 'required|string|max:2000'])['message'];

        $response = $agent->prompt($message);

        return response()->json(['reply' => $response->text]);
    }
}
```

```php
// routes/api.php
Route::post('/agent', AgentController::class);
```

### Streaming Responses

```php
$stream = $agent->stream($userMessage);

return response()->stream(function () use ($stream) {
    foreach ($stream as $event) {
        if ($event instanceof \Laravel\Ai\Streaming\Events\TextDelta) {
            echo $event->text;
            ob_flush();
            flush();
        }
    }
});
```

### Multi-Provider Support

Switch provider and model at call time without changing your agent class:

```php
use Laravel\Ai\Enums\Lab;

// Use the default provider from config/ai.php
$agent->prompt($message);

// Override at call time
$agent->prompt($message, provider: 'openai', model: 'gpt-4o');
$agent->prompt($message, provider: 'gemini', model: 'gemini-2.0-flash');

// Use a Lab preset
$agent->prompt($message, provider: Lab::Smartest);
$agent->prompt($message, provider: Lab::Cheapest);
```

### Queued Agent Calls

For long-running or background processing:

```php
$agent->queue($message)->then(function ($response) {
    // handle response
});
```

### Structuring Your Skills Directory

We recommend the following layout for a Laravel application:

```
skills/
  web-search/
    SKILL.md
  code-review/
    SKILL.md
    resources/
      style-guide.md
      examples.md
  data-analysis/
    SKILL.md
    scripts/
      fetch-schema.php    # for future SkillPreprocessor use
```

Set the path in `config/arcana.php`:

```php
'directories' => [
    base_path('skills'),
    // Add vendor skill packs:
    // base_path('vendor/acme/skills/src'),
],
```

### Caching in Production

Enable Redis caching to avoid re-parsing skill files on every request:

```dotenv
ARCANA_CACHE_ENABLED=true
ARCANA_CACHE_STORE=redis
ARCANA_CACHE_TTL=3600
```

Skills are cached individually. When you update a skill file, clear the cache:

```bash
php artisan cache:forget arcana.skill.web-search
# or clear all Arcana entries
php artisan cache:clear
```

---

## Security

### Path Traversal Protection

All resource and script paths declared in a skill's frontmatter are validated against the skill's own directory using `realpath()`. Any path that resolves outside the skill directory throws a `SecurityException`:

```php
// Safe — resources/overview.md is inside the skill directory
$skill->loadResource('overview');

// If a SKILL.md declared path: ../../etc/passwd, Arcana would throw:
// PeterFox\Arcana\Exception\SecurityException
```

### Skill Name Validation

Skill names are strictly validated before any filesystem access:
- Must match `/^[a-z][a-z0-9\-]*$/`
- Maximum 64 characters
- No directory separators, dots, or special characters

This prevents any `loadSkill('../etc/passwd')` style attacks.

### Script Execution

The `SkillScript` metadata is stored but **no scripts are executed** by default. Script execution is opt-in through a custom `SkillPreprocessorInterface` implementation, which you write and control entirely.

---

## Caching

Arcana uses PSR-16 (`psr/simple-cache`). Inject any compatible cache adapter:

```php
// Symfony Cache
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

$cache = new Psr16Cache(new RedisAdapter($redis));
$library = Arcana::create('/skills', cache: $cache);

// Laravel (outside service provider)
$cache = app(\Psr\SimpleCache\CacheInterface::class);
$library = Arcana::create('/skills', cache: $cache);
```

Cached items:
| Key pattern | Content | TTL |
|---|---|---|
| `arcana.skill.{name}` | Full `Skill` object | configurable |

The in-memory metadata index is **not** stored in PSR-16; it lives for the duration of the request/process. Filesystem scanning happens at most once per `SkillLibrary` instance.

---

## Skill Preprocessors

Preprocessors transform skill content at load time, before caching. Implement `SkillPreprocessorInterface`:

```php
use PeterFox\Arcana\Contract\SkillPreprocessorInterface;
use PeterFox\Arcana\Skill;

final class VariablePreprocessor implements SkillPreprocessorInterface
{
    /** @param array<string, string> $vars */
    public function __construct(
        private readonly array $vars = [],
    ) {}

    public function process(Skill $skill): Skill
    {
        $body = strtr($skill->body, array_combine(
            array_map(fn($k) => '{{'.$k.'}}', array_keys($this->vars)),
            $this->vars,
        ));

        return new Skill(
            metadata: $skill->metadata,
            body: $body,
        );
    }
}

// Usage
$library = Arcana::create(
    directories: '/skills',
    preprocessor: new VariablePreprocessor(['env' => 'production']),
);
```

In Laravel, register via config:

```php
// config/arcana.php
'preprocessor' => App\Skills\VariablePreprocessor::class,
```

---

## Testing

```bash
# Run the full test suite (Unit + Feature)
composer test

# Or directly
./vendor/bin/phpunit

# With coverage
./vendor/bin/phpunit --coverage-text

# Unit tests only
./vendor/bin/phpunit --testsuite Unit

# Laravel feature tests only
./vendor/bin/phpunit --testsuite Feature
```

### In Your Application

Use the `NullCache` and a temp directory for isolated tests:

```php
use PeterFox\Arcana\Arcana;

$library = Arcana::create($this->skillsFixturePath);

$skills = $library->listSkills();
self::assertCount(2, $skills);
```

### Testing Laravel Agents with `laravel/ai` Fakes

```php
use App\Agents\SkillAwareAgent;
use Laravel\Ai\Gateway\FakeTextGateway;

// Fake all agent responses
SkillAwareAgent::fake(['Hello from the fake agent.']);

$agent = SkillAwareAgent::make();
$response = $agent->prompt('Test message');

self::assertSame('Hello from the fake agent.', $response->text);

SkillAwareAgent::assertPrompted('Test message');
```

---

## Exception Reference

| Exception | When thrown |
|---|---|
| `ArcanaException` | Base class — catch this for any Arcana error |
| `SkillNotFoundException` | `loadSkill()` called with an unknown name |
| `SkillParseException` | Malformed YAML frontmatter or missing required fields |
| `SecurityException` | Path traversal detected in resource/script paths |
| `ValidationException` | Invalid skill name or non-existent directory |

---

## Next Steps / Roadmap

### v0.2 — Dynamic Context & Discovery

- `ScriptPreprocessor` — sandboxed PHP script execution for dynamic skill context injection
- PSR-16 index caching — persist the file index across requests for sub-millisecond listing
- Skill watchers — filesystem watcher to auto-invalidate cache on SKILL.md changes
- `artisan arcana:validate` — lint all skills for missing fields and formatting issues

---

## Contributing

Contributions are welcome! Please open an issue first to discuss significant changes.

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/my-feature`
3. Run tests: `composer check`
4. Submit a PR against `main`

---

## License

MIT — see [LICENSE](LICENSE) for details.

---

*Arcana — unlock your agents' full potential, one skill at a time.*
