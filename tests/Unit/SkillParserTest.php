<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PeterFox\Arcana\Exception\SkillParseException;
use PeterFox\Arcana\Skill;
use PeterFox\Arcana\SkillMetadata;
use PeterFox\Arcana\SkillParser;
use PeterFox\Arcana\SkillReference;
use PeterFox\Arcana\SkillResource;

#[CoversClass(SkillParser::class)]
final class SkillParserTest extends TestCase
{
    private SkillParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SkillParser();
    }

    // -------------------------------------------------------------------------
    // parseMetadataOnly
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_metadata_from_the_example_fixture(): void
    {
        $path = $this->fixturePath('example/SKILL.md');

        $metadata = $this->parser->parseMetadataOnly($path);

        self::assertInstanceOf(SkillMetadata::class, $metadata);
        self::assertSame('example-skill', $metadata->name);
        self::assertSame('1.2.0', $metadata->version);
        self::assertSame('Peter Fox', $metadata->author);
        self::assertSame($path, $metadata->filePath);
        self::assertContains('demo', $metadata->tags);
        self::assertContains('demonstrate skill format', $metadata->triggers);
    }

    #[Test]
    public function it_parses_resources_from_frontmatter(): void
    {
        $path = $this->fixturePath('example/SKILL.md');

        $metadata = $this->parser->parseMetadataOnly($path);

        self::assertCount(1, $metadata->resources);
        self::assertInstanceOf(SkillResource::class, $metadata->resources[0]);
        self::assertSame('overview', $metadata->resources[0]->name);
        self::assertSame('resources/overview.md', $metadata->resources[0]->path);
    }

    #[Test]
    public function it_parses_references_from_frontmatter(): void
    {
        $path = $this->fixturePath('example/SKILL.md');

        $metadata = $this->parser->parseMetadataOnly($path);

        self::assertCount(2, $metadata->references);
        self::assertInstanceOf(SkillReference::class, $metadata->references[0]);
        self::assertSame('Arcana Documentation', $metadata->references[0]->title);
    }

    #[Test]
    public function it_parses_minimal_frontmatter_with_defaults(): void
    {
        $content = <<<'SKILL'
            ---
            name: minimal-skill
            description: A minimal skill for testing
            ---

            # Body
            SKILL;

        $skill = $this->parser->parse(
            content: dedent($content),
            filePath: '/fake/path/SKILL.md',
        );

        self::assertSame('minimal-skill', $skill->metadata->name);
        self::assertSame('1.0.0', $skill->metadata->version);
        self::assertNull($skill->metadata->author);
        self::assertSame([], $skill->metadata->tags);
        self::assertSame([], $skill->metadata->triggers);
        self::assertSame([], $skill->metadata->resources);
        self::assertSame([], $skill->metadata->scripts);
        self::assertSame([], $skill->metadata->references);
    }

    // -------------------------------------------------------------------------
    // parse (full)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_parses_the_full_skill_including_body(): void
    {
        $content = "---\nname: body-test\ndescription: Testing body extraction\n---\n\n# Hello\n\nWorld";
        $skill = $this->parser->parse($content, '/fake/SKILL.md');

        self::assertInstanceOf(Skill::class, $skill);
        self::assertSame('body-test', $skill->metadata->name);
        self::assertStringContainsString('# Hello', $skill->body);
        self::assertStringContainsString('World', $skill->body);
    }

    #[Test]
    public function it_trims_leading_and_trailing_whitespace_from_body(): void
    {
        $content = "---\nname: trim-test\ndescription: Trimming test\n---\n\n\n   # Trimmed\n\n";
        $skill = $this->parser->parse($content, '/fake/SKILL.md');

        self::assertStringStartsWith('#', $skill->body);
        self::assertStringEndsWith('# Trimmed', trim($skill->body));
    }

    #[Test]
    public function it_parses_the_search_fixture_correctly(): void
    {
        $path = $this->fixturePath('search/SKILL.md');
        $skill = $this->parser->parse(file_get_contents($path), $path);

        self::assertSame('web-search', $skill->metadata->name);
        self::assertSame('2.0.1', $skill->metadata->version);
        self::assertContains('internet', $skill->metadata->tags);
        self::assertStringContainsString('# Web Search Skill', $skill->body);
    }

    // -------------------------------------------------------------------------
    // Validation / error cases
    // -------------------------------------------------------------------------

    #[Test]
    public function it_throws_when_frontmatter_is_missing(): void
    {
        $this->expectException(SkillParseException::class);
        $this->expectExceptionMessageMatches('/frontmatter/i');

        $this->parser->parse('# No frontmatter here', '/fake/SKILL.md');
    }

    #[Test]
    public function it_throws_when_name_field_is_missing(): void
    {
        $this->expectException(SkillParseException::class);
        $this->expectExceptionMessageMatches("/Required field 'name'/");

        $content = "---\ndescription: Missing name\n---\n\n# Body";
        $this->parser->parse($content, '/fake/SKILL.md');
    }

    #[Test]
    public function it_throws_when_description_field_is_missing(): void
    {
        $this->expectException(SkillParseException::class);
        $this->expectExceptionMessageMatches("/Required field 'description'/");

        $content = "---\nname: no-description\n---\n\n# Body";
        $this->parser->parse($content, '/fake/SKILL.md');
    }

    #[Test]
    public function it_throws_when_skill_name_is_invalid(): void
    {
        $this->expectException(SkillParseException::class);
        $this->expectExceptionMessageMatches('/Invalid skill name/');

        $content = "---\nname: Invalid Name With Spaces\ndescription: Bad name\n---\n\n# Body";
        $this->parser->parse($content, '/fake/SKILL.md');
    }

    #[Test]
    public function it_throws_on_uppercase_skill_name(): void
    {
        $this->expectException(SkillParseException::class);

        $content = "---\nname: MySkill\ndescription: uppercase\n---\n\n# Body";
        $this->parser->parse($content, '/fake/SKILL.md');
    }

    #[Test]
    public function it_throws_when_file_does_not_exist(): void
    {
        $this->expectException(SkillParseException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->parser->parseMetadataOnly('/nonexistent/path/SKILL.md');
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function fixturePath(string $relative): string
    {
        return realpath(__DIR__ . '/../Fixtures/skills/' . $relative)
            ?: throw new \RuntimeException("Fixture not found: {$relative}");
    }
}

// PHPUnit-compatible dedent helper for inline heredocs in PHP < 8.3
function dedent(string $text): string
{
    $lines = explode("\n", $text);
    $indent = PHP_INT_MAX;

    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $indent = min($indent, strlen($line) - strlen(ltrim($line)));
    }

    if ($indent === PHP_INT_MAX) {
        return $text;
    }

    return implode("\n", array_map(
        fn(string $l) => strlen($l) >= $indent ? substr($l, $indent) : $l,
        $lines,
    ));
}
