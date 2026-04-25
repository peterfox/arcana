<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tests\Feature\Laravel;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use PeterFox\Arcana\Laravel\ListSkillsTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ListSkillsTool::class)]
final class ListSkillsToolTest extends TestCase
{
    private ListSkillsTool $tool;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $app = $this->app ?? throw new \RuntimeException('App not initialized');
        $this->tool = $app->make(ListSkillsTool::class);
    }

    #[Test]
    public function it_has_a_non_empty_description(): void
    {
        self::assertNotEmpty($this->tool->description());
    }

    #[Test]
    public function schema_declares_an_optional_filter_parameter(): void
    {
        $app = $this->app ?? throw new \RuntimeException('App not initialized');
        $schema = $app->make(JsonSchema::class);
        $definition = $this->tool->schema($schema);

        self::assertArrayHasKey('filter', $definition);
    }

    #[Test]
    public function it_returns_all_skills_when_no_filter_is_given(): void
    {
        $request = new Request([]);

        $result = $this->tool->handle($request);
        $data = json_decode($result, true);

        self::assertArrayHasKey('count', $data);
        self::assertArrayHasKey('skills', $data);
        self::assertGreaterThan(0, $data['count']);
        self::assertCount($data['count'], $data['skills']);
    }

    #[Test]
    public function it_filters_skills_by_the_filter_parameter(): void
    {
        $request = new Request(['filter' => 'example']);

        $result = $this->tool->handle($request);
        $data = json_decode($result, true);

        self::assertGreaterThan(0, $data['count']);
        $names = array_column($data['skills'], 'name');
        self::assertContains('example-skill', $names);
    }

    #[Test]
    public function it_returns_empty_result_for_non_matching_filter(): void
    {
        $request = new Request(['filter' => 'this-matches-nothing-xyz']);

        $result = $this->tool->handle($request);
        $data = json_decode($result, true);

        self::assertSame(0, $data['count']);
        self::assertSame([], $data['skills']);
    }

    #[Test]
    public function skill_entries_include_expected_fields(): void
    {
        $request = new Request(['filter' => 'example']);

        $result = $this->tool->handle($request);
        $data = json_decode($result, true);

        $skill = $data['skills'][0];

        foreach (['name', 'description', 'version', 'tags', 'triggers'] as $field) {
            self::assertArrayHasKey($field, $skill, "Missing field: {$field}");
        }
    }

    #[Test]
    public function it_treats_empty_string_filter_as_no_filter(): void
    {
        $withEmpty = json_decode($this->tool->handle(new Request(['filter' => ''])), true);
        $withNull = json_decode($this->tool->handle(new Request([])), true);

        self::assertSame($withNull['count'], $withEmpty['count']);
    }
}
