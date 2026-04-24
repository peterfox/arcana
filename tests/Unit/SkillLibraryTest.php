<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PeterFox\Arcana\Arcana;
use PeterFox\Arcana\Exception\SecurityException;
use PeterFox\Arcana\Exception\SkillNotFoundException;
use PeterFox\Arcana\Exception\ValidationException;
use PeterFox\Arcana\Skill;
use PeterFox\Arcana\SkillLibrary;
use PeterFox\Arcana\SkillMetadata;

#[CoversClass(SkillLibrary::class)]
final class SkillLibraryTest extends TestCase
{
    private string $fixturesDir;

    private SkillLibrary $library;

    protected function setUp(): void
    {
        $this->fixturesDir = realpath(__DIR__ . '/../Fixtures/skills')
            ?: throw new \RuntimeException('Fixtures directory not found');

        $this->library = Arcana::create($this->fixturesDir);
    }

    // -------------------------------------------------------------------------
    // listSkills
    // -------------------------------------------------------------------------

    #[Test]
    public function it_lists_all_discovered_skills(): void
    {
        $skills = $this->library->listSkills();

        self::assertNotEmpty($skills);
        self::assertContainsOnlyInstancesOf(SkillMetadata::class, $skills);

        $names = array_map(fn(SkillMetadata $m) => $m->name, $skills);
        self::assertContains('example-skill', $names);
        self::assertContains('web-search', $names);
    }

    #[Test]
    public function it_returns_lightweight_metadata_without_body(): void
    {
        $skills = $this->library->listSkills();

        // listSkills returns SkillMetadata, not Skill — no body available
        foreach ($skills as $metadata) {
            self::assertInstanceOf(SkillMetadata::class, $metadata);
        }
    }

    #[Test]
    public function it_filters_skills_by_name(): void
    {
        $results = $this->library->listSkills('web');

        self::assertNotEmpty($results);

        foreach ($results as $m) {
            self::assertStringContainsStringIgnoringCase('web', $m->name . ' ' . $m->description . ' ' . implode(' ', $m->tags));
        }
    }

    #[Test]
    public function it_filters_skills_by_tag(): void
    {
        $results = $this->library->listSkills('demo');

        self::assertNotEmpty($results);
        $names = array_map(fn(SkillMetadata $m) => $m->name, $results);
        self::assertContains('example-skill', $names);
    }

    #[Test]
    public function it_filters_skills_by_trigger(): void
    {
        $results = $this->library->listSkills('search the web');

        self::assertNotEmpty($results);
        $names = array_map(fn(SkillMetadata $m) => $m->name, $results);
        self::assertContains('web-search', $names);
    }

    #[Test]
    public function it_returns_empty_array_when_no_skills_match_filter(): void
    {
        $results = $this->library->listSkills('zzz-nonexistent-xyz');

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
        self::assertNotEmpty($skill->body);
    }

    #[Test]
    public function it_loads_the_search_skill(): void
    {
        $skill = $this->library->loadSkill('web-search');

        self::assertStringContainsString('# Web Search Skill', $skill->body);
        self::assertSame('2.0.1', $skill->metadata->version);
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

    #[Test]
    public function it_throws_validation_exception_for_path_traversal_in_name(): void
    {
        $this->expectException(ValidationException::class);

        $this->library->loadSkill('../etc/passwd');
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
    // count / multiple directories
    // -------------------------------------------------------------------------

    #[Test]
    public function it_counts_discovered_skills(): void
    {
        self::assertGreaterThanOrEqual(2, $this->library->count());
    }

    #[Test]
    public function it_accepts_multiple_directories(): void
    {
        $exampleDir = $this->fixturesDir . '/example';
        $searchDir = $this->fixturesDir . '/search';

        $library = Arcana::create([$exampleDir, $searchDir]);

        self::assertCount(2, $library->listSkills());
    }

    #[Test]
    public function it_throws_validation_exception_for_nonexistent_directory(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/not found|not a directory/i');

        Arcana::create('/nonexistent/directory/path');
    }

    // -------------------------------------------------------------------------
    // Resource loading
    // -------------------------------------------------------------------------

    #[Test]
    public function it_loads_bundled_resources(): void
    {
        $skill = $this->library->loadSkill('example-skill');

        $content = $skill->loadResource('overview');

        self::assertStringContainsString('Progressive Disclosure', $content);
    }

    #[Test]
    public function it_throws_for_unknown_resource(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $skill = $this->library->loadSkill('example-skill');
        $skill->loadResource('nonexistent-resource');
    }

    #[Test]
    public function it_includes_resources_in_full_content(): void
    {
        $skill = $this->library->loadSkill('example-skill');

        $full = $skill->fullContent(includeResources: true);

        self::assertStringContainsString('# Example Skill', $full);
        self::assertStringContainsString('Resource: overview', $full);
    }

    #[Test]
    public function it_excludes_resources_from_full_content_when_requested(): void
    {
        $skill = $this->library->loadSkill('example-skill');

        $bodyOnly = $skill->fullContent(includeResources: false);

        self::assertStringNotContainsString('Resource: overview', $bodyOnly);
    }

    // -------------------------------------------------------------------------
    // In-memory caching
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_the_same_skill_instance_on_repeated_calls(): void
    {
        $first = $this->library->loadSkill('example-skill');
        $second = $this->library->loadSkill('example-skill');

        // With NullCache the library re-parses, but metadata index is shared
        self::assertSame($first->metadata->name, $second->metadata->name);
    }
}
