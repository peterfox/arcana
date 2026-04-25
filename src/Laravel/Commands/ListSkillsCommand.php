<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Laravel\Commands;

use Illuminate\Console\Command;
use PeterFox\Arcana\Contract\SkillLibraryInterface;
use PeterFox\Arcana\SkillMetadata;

/**
 * Artisan command: arcana:list
 *
 * Lists all available skills in a formatted table.
 *
 *   php artisan arcana:list
 *   php artisan arcana:list --filter=search
 *   php artisan arcana:list --json
 */
final class ListSkillsCommand extends Command
{
    public function __construct()
    {
        $this->signature = 'arcana:list
                            {--filter= : Filter skills by name, description, tags, or triggers}
                            {--json    : Output raw JSON instead of a table}';

        $this->description = 'List all available Arcana agent skills';

        parent::__construct();
    }

    public function handle(SkillLibraryInterface $library): int
    {
        $filterOption = $this->option('filter');
        $filter = is_string($filterOption) && $filterOption !== '' ? $filterOption : null;
        $skills = $library->listSkills($filter);

        if ($skills === []) {
            $this->warn(
                $filter
                ? "No skills matched the filter: \"{$filter}\""
                : 'No skills found. Check your arcana.directories config.',
            );

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode(
                array_map(fn(SkillMetadata $m) => [
                    'name' => $m->name,
                    'description' => $m->description,
                    'version' => $m->version,
                    'author' => $m->author,
                    'tags' => $m->tags,
                    'triggers' => $m->triggers,
                ], $skills),
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            ));

            return self::SUCCESS;
        }

        $this->table(
            headers: ['Name', 'Description', 'Version', 'Tags'],
            rows: array_map(fn(SkillMetadata $m) => [
                "<info>{$m->name}</info>",
                $this->truncate($m->description, 60),
                $m->version,
                implode(', ', array_slice($m->tags, 0, 4)) . (count($m->tags) > 4 ? '…' : ''),
            ], $skills),
        );

        $count = count($skills);
        $noun = $count === 1 ? 'skill' : 'skills';
        $this->line('');
        $this->line("  <comment>Total: {$count} {$noun}</comment>" . ($filter !== null ? " matching \"{$filter}\"" : ''));

        return self::SUCCESS;
    }

    private function truncate(string $text, int $maxLength): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 1) . '…';
    }
}
