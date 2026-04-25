<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tests\Feature\Laravel\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\PendingCommand;
use PeterFox\Arcana\Laravel\Commands\ShowSkillCommand;
use PeterFox\Arcana\Tests\Feature\Laravel\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ShowSkillCommand::class)]
final class ShowSkillCommandTest extends TestCase
{
    #[Test]
    public function it_shows_formatted_skill_metadata(): void
    {
        // Check name and description on separate lines to avoid Mockery's
        // single-expectation-per-doWrite-call limitation.
        $pending = $this->artisan('arcana:show', ['name' => 'example-skill']);

        if (!$pending instanceof PendingCommand) {
            throw new \RuntimeException('Unexpected return type from artisan()');
        }
        $pending->expectsOutputToContain('example-skill')
            ->assertSuccessful();

        // Verify version is present via Artisan::output() (no Mockery interception)
        Artisan::call('arcana:show', ['name' => 'example-skill']);
        self::assertStringContainsString('v1.2.0', Artisan::output());
    }

    #[Test]
    public function it_shows_the_author(): void
    {
        $pending = $this->artisan('arcana:show', ['name' => 'example-skill']);

        if (!$pending instanceof PendingCommand) {
            throw new \RuntimeException('Unexpected return type from artisan()');
        }
        $pending->expectsOutputToContain('Peter Fox')
            ->assertSuccessful();
    }

    #[Test]
    public function it_outputs_the_body_when_flag_is_given(): void
    {
        $pending = $this->artisan('arcana:show', ['name' => 'example-skill', '--body' => true]);

        if (!$pending instanceof PendingCommand) {
            throw new \RuntimeException('Unexpected return type from artisan()');
        }
        $pending->expectsOutputToContain('Example Skill')
            ->assertSuccessful();
    }

    #[Test]
    public function it_outputs_json_when_flag_is_given(): void
    {
        $exitCode = Artisan::call('arcana:show', ['name' => 'example-skill', '--json' => true]);
        self::assertSame(0, $exitCode);

        $decoded = json_decode(Artisan::output(), true);

        self::assertIsArray($decoded);
        self::assertSame('example-skill', $decoded['name']);
        self::assertArrayHasKey('description', $decoded);
        self::assertArrayHasKey('body', $decoded);
        self::assertArrayHasKey('resources', $decoded);
    }

    #[Test]
    public function it_fails_for_an_unknown_skill(): void
    {
        $pending = $this->artisan('arcana:show', ['name' => 'does-not-exist']);

        if (!$pending instanceof PendingCommand) {
            throw new \RuntimeException('Unexpected return type from artisan()');
        }
        $pending->expectsOutputToContain("Skill 'does-not-exist' not found")
            ->assertFailed();
    }

    #[Test]
    public function it_fails_for_an_invalid_skill_name(): void
    {
        $pending = $this->artisan('arcana:show', ['name' => 'INVALID NAME!']);

        if (!$pending instanceof PendingCommand) {
            throw new \RuntimeException('Unexpected return type from artisan()');
        }
        $pending->expectsOutputToContain('Invalid skill name')
            ->assertFailed();
    }

    #[Test]
    public function json_output_includes_all_fields(): void
    {
        Artisan::call('arcana:show', ['name' => 'example-skill', '--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        foreach (['name', 'description', 'version', 'author', 'tags', 'triggers', 'resources', 'scripts', 'references', 'body'] as $field) {
            self::assertArrayHasKey($field, $decoded, "Missing field: {$field}");
        }
    }
}
