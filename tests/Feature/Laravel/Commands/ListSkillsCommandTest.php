<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tests\Feature\Laravel\Commands;

use Illuminate\Support\Facades\Artisan;
use PeterFox\Arcana\Laravel\Commands\ListSkillsCommand;
use PeterFox\Arcana\Tests\Feature\Laravel\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ListSkillsCommand::class)]
final class ListSkillsCommandTest extends TestCase
{
    #[Test]
    public function it_lists_skills_in_a_table(): void
    {
        $this->artisan('arcana:list')
            ->expectsOutputToContain('example-skill')
            ->assertSuccessful();
    }

    #[Test]
    public function it_filters_skills_by_term(): void
    {
        $this->artisan('arcana:list', ['--filter' => 'example'])
            ->expectsOutputToContain('example-skill')
            ->assertSuccessful();
    }

    #[Test]
    public function it_outputs_json_when_flag_is_given(): void
    {
        $exitCode = Artisan::call('arcana:list', ['--json' => true]);
        self::assertSame(0, $exitCode);

        $decoded = json_decode(Artisan::output(), true);

        self::assertIsArray($decoded);
        self::assertNotEmpty($decoded);

        $names = array_column($decoded, 'name');
        self::assertContains('example-skill', $names);
    }

    #[Test]
    public function it_warns_when_no_skills_match_filter(): void
    {
        $this->artisan('arcana:list', ['--filter' => 'this-skill-does-not-exist-xyz'])
            ->expectsOutputToContain('No skills matched')
            ->assertSuccessful();
    }

    #[Test]
    public function json_output_includes_expected_fields(): void
    {
        Artisan::call('arcana:list', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        self::assertNotEmpty($decoded);
        $skill = $decoded[0];

        foreach (['name', 'description', 'version', 'tags', 'triggers'] as $field) {
            self::assertArrayHasKey($field, $skill, "Missing field: {$field}");
        }
    }
}
