<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tests\Feature\Laravel;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use PeterFox\Arcana\Exception\SkillNotFoundException;
use PeterFox\Arcana\Laravel\LoadSkillTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(LoadSkillTool::class)]
final class LoadSkillToolTest extends TestCase
{
    private LoadSkillTool $tool;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $app = $this->app ?? throw new \RuntimeException('App not initialized');
        $this->tool = $app->make(LoadSkillTool::class);
    }

    #[Test]
    public function it_has_a_non_empty_description(): void
    {
        self::assertNotEmpty($this->tool->description());
    }

    #[Test]
    public function schema_declares_a_required_name_parameter(): void
    {
        $app = $this->app ?? throw new \RuntimeException('App not initialized');
        $schema = $app->make(JsonSchema::class);
        $definition = $this->tool->schema($schema);

        self::assertArrayHasKey('name', $definition);
    }

    #[Test]
    public function it_loads_skill_content_by_name(): void
    {
        $request = new Request(['name' => 'example-skill']);

        $result = $this->tool->handle($request);

        self::assertStringContainsString('Example Skill', $result);
    }

    #[Test]
    public function loaded_content_includes_the_body(): void
    {
        $request = new Request(['name' => 'example-skill']);

        $result = $this->tool->handle($request);

        self::assertStringContainsString('Capabilities', $result);
        self::assertStringContainsString('Instructions', $result);
    }

    #[Test]
    public function loaded_content_includes_bundled_resources(): void
    {
        $request = new Request(['name' => 'example-skill']);

        $result = $this->tool->handle($request);

        // The example-skill fixture has a resources/overview.md file
        self::assertStringContainsString('overview', strtolower($result));
    }

    #[Test]
    public function it_throws_for_an_unknown_skill(): void
    {
        $this->expectException(SkillNotFoundException::class);

        $this->tool->handle(new Request(['name' => 'no-such-skill']));
    }
}
