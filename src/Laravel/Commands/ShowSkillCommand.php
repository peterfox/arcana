<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Laravel\Commands;

use Illuminate\Console\Command;
use PeterFox\Arcana\Contract\SkillLibraryInterface;
use PeterFox\Arcana\Exception\SkillNotFoundException;
use PeterFox\Arcana\Exception\ValidationException;
use PeterFox\Arcana\Skill;

/**
 * Artisan command: arcana:show
 *
 * Displays the full metadata and body content of a named skill.
 *
 *   php artisan arcana:show web-search
 *   php artisan arcana:show web-search --body
 *   php artisan arcana:show web-search --json
 */
final class ShowSkillCommand extends Command
{
    protected $signature = 'arcana:show
                            {name             : The exact skill name to display}
                            {--body           : Output the raw Markdown body only}
                            {--no-resources   : Exclude bundled resources from --body output}
                            {--json           : Output raw JSON}';

    protected $description = 'Show the full content of a named Arcana skill';

    public function handle(SkillLibraryInterface $library): int
    {
        $name = (string) $this->argument('name');

        try {
            $skill = $library->loadSkill($name);
        } catch (SkillNotFoundException $e) {
            $this->error("Skill '{$name}' not found.");
            $this->line('');
            $this->line('Run <comment>php artisan arcana:list</comment> to see available skills.');

            return self::FAILURE;
        } catch (ValidationException $e) {
            $this->error("Invalid skill name: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($this->option('json')) {
            return $this->outputJson($skill);
        }

        if ($this->option('body')) {
            $includeResources = !$this->option('no-resources');
            $this->line($skill->fullContent($includeResources));

            return self::SUCCESS;
        }

        return $this->outputFormatted($skill);
    }

    private function outputFormatted(Skill $skill): int
    {
        $m = $skill->metadata;

        $this->line('');
        $this->line("  <info>Skill: {$m->name}</info>  <comment>v{$m->version}</comment>");
        $this->line('');
        $this->line("  {$m->description}");

        if ($m->author !== null) {
            $this->line('');
            $this->line("  <comment>Author:</comment> {$m->author}");
        }

        if ($m->tags !== []) {
            $this->line("  <comment>Tags:</comment>     " . implode(', ', $m->tags));
        }

        if ($m->triggers !== []) {
            $this->line("  <comment>Triggers:</comment>");
            foreach ($m->triggers as $trigger) {
                $this->line("    - {$trigger}");
            }
        }

        if ($m->resources !== []) {
            $this->line("  <comment>Resources:</comment>");
            foreach ($m->resources as $resource) {
                $desc = $resource->description !== '' ? " — {$resource->description}" : '';
                $this->line("    - {$resource->name}{$desc}");
            }
        }

        if ($m->references !== []) {
            $this->line("  <comment>References:</comment>");
            foreach ($m->references as $ref) {
                $this->line("    - {$ref->title}: {$ref->url}");
            }
        }

        $this->line('');
        $this->line('  <comment>─── Body ───────────────────────────────────────────────────</comment>');
        $this->line('');

        foreach (explode("\n", $skill->body) as $line) {
            $this->line("  {$line}");
        }

        $this->line('');

        return self::SUCCESS;
    }

    private function outputJson(Skill $skill): int
    {
        $m = $skill->metadata;

        $this->line(json_encode([
            'name' => $m->name,
            'description' => $m->description,
            'version' => $m->version,
            'author' => $m->author,
            'tags' => $m->tags,
            'triggers' => $m->triggers,
            'resources' => array_map(fn($r) => [
                'name' => $r->name,
                'description' => $r->description,
                'path' => $r->path,
            ], $m->resources),
            'scripts' => array_map(fn($s) => [
                'name' => $s->name,
                'description' => $s->description,
                'path' => $s->path,
                'language' => $s->language,
            ], $m->scripts),
            'references' => array_map(fn($ref) => [
                'title' => $ref->title,
                'url' => $ref->url,
            ], $m->references),
            'body' => $skill->body,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
