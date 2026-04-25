<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tests\Unit\Flysystem;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PeterFox\Arcana\Exception\SkillNotFoundException;
use PeterFox\Arcana\Exception\ValidationException;
use PeterFox\Arcana\Flysystem\FlysystemSkillLibrary;
use PeterFox\Arcana\Skill;
use PeterFox\Arcana\SkillMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlysystemSkillLibrary::class)]
final class FlysystemSkillLibraryTest extends TestCase
{
    private Filesystem $filesystem;

    private FlysystemSkillLibrary $library;

    #[\Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());

        $this->filesystem->write('example-skill/SKILL.md', <<<'MD'
            ---
            name: example-skill
            description: An example skill for testing.
            version: 1.0.0
            author: Test Author
            tags:
              - example
              - demo
            triggers:
              - show me an example
            resources:
              - name: notes
                description: Supplementary notes.
                path: resources/notes.md
            ---

            # Example Skill

            Body content for the example skill.
            MD);

        $this->filesystem->write('example-skill/resources/notes.md', "# Notes\n\nSupplementary content.");

        $this->filesystem->write('web-search/SKILL.md', <<<'MD'
            ---
            name: web-search
            description: Enables web searching capabilities.
            version: 2.0.0
            tags:
              - search
              - web
            triggers:
              - search the web
            ---

            # Web Search Skill

            Use this skill to retrieve current information from the internet.
            MD);

        $this->library = new FlysystemSkillLibrary($this->filesystem);
    }

    // -------------------------------------------------------------------------
    // listSkills
    // -------------------------------------------------------------------------

    #[Test]
    public function it_lists_all_discovered_skills(): void
    {
        $skills = $this->library->listSkills();

        self::assertCount(2, $skills);

        $names = array_map(fn(SkillMetadata $m) => $m->name, $skills);
        self::assertContains('example-skill', $names);
        self::assertContains('web-search', $names);
    }

    #[Test]
    public function it_returns_lightweight_metadata_without_body(): void
    {
        $skills = $this->library->listSkills();

        foreach ($skills as $metadata) {
            self::assertInstanceOf(SkillMetadata::class, $metadata);
        }
    }

    #[Test]
    public function it_filters_skills_by_name(): void
    {
        $results = $this->library->listSkills('web');

        self::assertCount(1, $results);
        self::assertSame('web-search', $results[0]->name);
    }

    #[Test]
    public function it_filters_skills_by_tag(): void
    {
        $results = $this->library->listSkills('demo');

        self::assertCount(1, $results);
        self::assertSame('example-skill', $results[0]->name);
    }

    #[Test]
    public function it_filters_skills_by_trigger(): void
    {
        $results = $this->library->listSkills('search the web');

        self::assertCount(1, $results);
        self::assertSame('web-search', $results[0]->name);
    }

    #[Test]
    public function it_returns_empty_array_when_filter_matches_nothing(): void
    {
        $results = $this->library->listSkills('zzz-nonexistent');

        self::assertSame([], $results);
    }

    // -------------------------------------------------------------------------
    // loadSkill
    // -------------------------------------------------------------------------

    #[Test]
    public function it_loads_a_skill_by_name(): void
    {
        $skill = $this->library->loadSkill('example-skill');

        self::assertInstanceOf(Skill::class, $skill);
        self::assertSame('example-skill', $skill->metadata->name);
        self::assertStringContainsString('# Example Skill', $skill->body);
    }

    #[Test]
    public function it_loads_the_web_search_skill(): void
    {
        $skill = $this->library->loadSkill('web-search');

        self::assertStringContainsString('# Web Search Skill', $skill->body);
        self::assertSame('2.0.0', $skill->metadata->version);
    }

    #[Test]
    public function it_throws_skill_not_found_for_unknown_name(): void
    {
        $this->expectException(SkillNotFoundException::class);

        $this->library->loadSkill('does-not-exist');
    }

    #[Test]
    public function it_throws_validation_exception_for_invalid_name(): void
    {
        $this->expectException(ValidationException::class);

        $this->library->loadSkill('INVALID NAME');
    }

    // -------------------------------------------------------------------------
    // hasSkill
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_true_for_existing_skill(): void
    {
        self::assertTrue($this->library->hasSkill('example-skill'));
    }

    #[Test]
    public function it_returns_false_for_nonexistent_skill(): void
    {
        self::assertFalse($this->library->hasSkill('not-here'));
    }

    #[Test]
    public function it_returns_false_for_invalid_name_in_has_skill(): void
    {
        self::assertFalse($this->library->hasSkill('Invalid/Name'));
    }

    // -------------------------------------------------------------------------
    // count
    // -------------------------------------------------------------------------

    #[Test]
    public function it_counts_discovered_skills(): void
    {
        self::assertSame(2, $this->library->count());
    }

    // -------------------------------------------------------------------------
    // Resource loading via FlysystemResourceLoader
    // -------------------------------------------------------------------------

    #[Test]
    public function it_loads_bundled_resources(): void
    {
        $skill = $this->library->loadSkill('example-skill');

        $content = $skill->loadResource('notes');

        self::assertStringContainsString('Supplementary content', $content);
    }

    #[Test]
    public function it_includes_resources_in_full_content(): void
    {
        $skill = $this->library->loadSkill('example-skill');

        $full = $skill->fullContent(includeResources: true);

        self::assertStringContainsString('# Example Skill', $full);
        self::assertStringContainsString('Resource: notes', $full);
        self::assertStringContainsString('Supplementary content', $full);
    }

    #[Test]
    public function it_excludes_resources_from_full_content_when_requested(): void
    {
        $skill = $this->library->loadSkill('example-skill');

        $bodyOnly = $skill->fullContent(includeResources: false);

        self::assertStringNotContainsString('Resource: notes', $bodyOnly);
    }

    // -------------------------------------------------------------------------
    // withBody preserves the FlysystemResourceLoader
    // -------------------------------------------------------------------------

    #[Test]
    public function with_body_preserves_flysystem_resource_loader(): void
    {
        $original = $this->library->loadSkill('example-skill');
        $modified = $original->withBody('Modified body.');

        // If the resource loader were lost, this would throw or return wrong content
        $content = $modified->loadResource('notes');

        self::assertStringContainsString('Supplementary content', $content);
        self::assertSame('Modified body.', $modified->body);
    }

    // -------------------------------------------------------------------------
    // flush / in-memory index
    // -------------------------------------------------------------------------

    #[Test]
    public function flush_clears_the_in_memory_index(): void
    {
        // Warm the index
        self::assertSame(2, $this->library->count());

        // Add a new skill after initial discovery
        $this->filesystem->write('new-skill/SKILL.md', <<<'MD'
            ---
            name: new-skill
            description: A skill added after initial discovery.
            ---

            # New Skill
            MD);

        // Index is cached — new skill not yet visible
        self::assertSame(2, $this->library->count());

        // After flush, index is rebuilt
        $this->library->flush();
        self::assertSame(3, $this->library->count());
    }

    // -------------------------------------------------------------------------
    // Graceful handling of malformed files
    // -------------------------------------------------------------------------

    #[Test]
    public function it_skips_malformed_skill_files_during_discovery(): void
    {
        $this->filesystem->write('bad-skill/SKILL.md', 'no frontmatter here');

        $this->library->flush();
        $skills = $this->library->listSkills();

        // bad-skill is skipped; the two valid skills are still returned
        $names = array_map(fn(SkillMetadata $m) => $m->name, $skills);
        self::assertNotContains('bad-skill', $names);
        self::assertCount(2, $skills);
    }

    #[Test]
    public function it_returns_empty_list_for_an_empty_filesystem(): void
    {
        $emptyLibrary = new FlysystemSkillLibrary(
            new Filesystem(new InMemoryFilesystemAdapter()),
        );

        self::assertSame([], $emptyLibrary->listSkills());
        self::assertSame(0, $emptyLibrary->count());
    }
}
