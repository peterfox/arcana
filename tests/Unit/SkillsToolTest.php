<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tests\Unit;

use PeterFox\Arcana\Arcana;
use PeterFox\Arcana\Exception\SkillNotFoundException;
use PeterFox\Arcana\Exception\ValidationException;
use PeterFox\Arcana\SkillLibrary;
use PeterFox\Arcana\Tool\ListSkillsTool;
use PeterFox\Arcana\Tool\LoadSkillTool;
use PeterFox\Arcana\Tool\SkillsTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SkillsTool::class)]
#[CoversClass(ListSkillsTool::class)]
#[CoversClass(LoadSkillTool::class)]
final class SkillsToolTest extends TestCase
{
    private SkillLibrary $library;

    private SkillsTool $tool;

    #[\Override]
    protected function setUp(): void
    {
        $dir = realpath(__DIR__ . '/../Fixtures/skills')
            ?: throw new \RuntimeException('Fixtures not found');

        $this->library = Arcana::create($dir);
        $this->tool = Arcana::tool($this->library);
    }

    // -------------------------------------------------------------------------
    // ListSkillsTool (invokable)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_json_with_skills_array(): void
    {
        $listTool = new ListSkillsTool($this->library);
        $json = $listTool();

        $data = json_decode($json, true);

        self::assertIsArray($data);
        self::assertArrayHasKey('count', $data);
        self::assertArrayHasKey('skills', $data);
        self::assertGreaterThanOrEqual(2, $data['count']);
    }

    #[Test]
    public function it_filters_via_list_tool(): void
    {
        $listTool = new ListSkillsTool($this->library);
        $json = $listTool('web');

        $data = json_decode($json, true);

        self::assertIsArray($data);

        foreach ($data['skills'] as $skill) {
            $haystack = strtolower($skill['name'] . ' ' . $skill['description'] . ' ' . implode(' ', $skill['tags']));
            self::assertStringContainsString('web', $haystack);
        }
    }

    #[Test]
    public function it_returns_skill_body_via_load_tool(): void
    {
        $loadTool = new LoadSkillTool($this->library);
        $body = $loadTool('example-skill');

        self::assertStringContainsString('# Example Skill', $body);
    }

    #[Test]
    public function it_load_tool_propagates_skill_not_found(): void
    {
        $this->expectException(SkillNotFoundException::class);

        $loadTool = new LoadSkillTool($this->library);
        $loadTool('nonexistent-skill');
    }

    // -------------------------------------------------------------------------
    // SkillsTool direct methods
    // -------------------------------------------------------------------------

    #[Test]
    public function it_lists_skills_as_json_via_tool(): void
    {
        $json = $this->tool->listSkills();
        $data = json_decode($json, true);

        self::assertIsArray($data);
        self::assertArrayHasKey('skills', $data);
    }

    #[Test]
    public function it_loads_skill_body_via_tool(): void
    {
        $body = $this->tool->loadSkill('web-search');

        self::assertStringContainsString('# Web Search Skill', $body);
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    #[Test]
    public function it_dispatches_list_skills(): void
    {
        $result = $this->tool->dispatch('list_skills', []);

        $data = json_decode($result, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('skills', $data);
    }

    #[Test]
    public function it_dispatches_list_skills_with_filter(): void
    {
        $result = $this->tool->dispatch('list_skills', ['filter' => 'web']);

        $data = json_decode($result, true);
        self::assertIsArray($data);
        self::assertLessThanOrEqual(2, $data['count']);
    }

    #[Test]
    public function it_dispatches_load_skill(): void
    {
        $result = $this->tool->dispatch('load_skill', ['name' => 'web-search']);

        self::assertStringContainsString('# Web Search Skill', $result);
    }

    #[Test]
    public function it_dispatch_throws_for_missing_name_argument(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches("/Missing required argument 'name'/");

        $this->tool->dispatch('load_skill', []);
    }

    #[Test]
    public function it_dispatch_throws_for_unknown_function(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/Unknown tool function/');

        $this->tool->dispatch('do_something_else', []);
    }

    // -------------------------------------------------------------------------
    // Function definitions
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_two_openai_definitions(): void
    {
        $defs = $this->tool->definitions();

        self::assertCount(2, $defs);
        self::assertSame('function', $defs[0]['type']);
        self::assertSame('list_skills', $defs[0]['function']['name']);
        self::assertSame('load_skill', $defs[1]['function']['name']);
    }

    #[Test]
    public function it_definitions_include_parameters_schema(): void
    {
        $defs = $this->tool->definitions();

        $listParams = $defs[0]['function']['parameters'];
        self::assertSame('object', $listParams['type']);
        self::assertArrayHasKey('filter', $listParams['properties']);

        $loadParams = $defs[1]['function']['parameters'];
        self::assertContains('name', $loadParams['required']);
    }

    #[Test]
    public function it_returns_anthropic_compatible_definitions(): void
    {
        $defs = $this->tool->anthropicDefinitions();

        self::assertCount(2, $defs);
        self::assertSame('list_skills', $defs[0]['name']);
        self::assertArrayHasKey('input_schema', $defs[0]);
        self::assertArrayHasKey('description', $defs[0]);
        self::assertArrayNotHasKey('type', $defs[0]);   // no 'type: function' wrapper
    }

    #[Test]
    public function it_anthropic_definitions_have_correct_input_schema(): void
    {
        $defs = $this->tool->anthropicDefinitions();

        $loadSkill = $defs[1];
        self::assertSame('load_skill', $loadSkill['name']);
        self::assertSame('object', $loadSkill['input_schema']['type']);
        self::assertContains('name', $loadSkill['input_schema']['required']);
    }
}
