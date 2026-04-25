<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tests\Unit\Flysystem;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PeterFox\Arcana\Exception\SecurityException;
use PeterFox\Arcana\Exception\SkillParseException;
use PeterFox\Arcana\Flysystem\FlysystemResourceLoader;
use PeterFox\Arcana\SkillResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlysystemResourceLoader::class)]
final class FlysystemResourceLoaderTest extends TestCase
{
    private Filesystem $filesystem;

    private FlysystemResourceLoader $loader;

    #[\Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->filesystem->write('my-skill/resources/notes.md', "# Notes\n\nResource content.");
        $this->loader = new FlysystemResourceLoader($this->filesystem);
    }

    #[Test]
    public function it_loads_a_resource_file(): void
    {
        $resource = new SkillResource('notes', 'Some notes', 'resources/notes.md');

        $content = $this->loader->load($resource, 'my-skill');

        self::assertStringContainsString('Resource content', $content);
    }

    #[Test]
    public function it_throws_security_exception_for_absolute_path(): void
    {
        $this->expectException(SecurityException::class);

        $resource = new SkillResource('evil', '', '/etc/passwd');
        $this->loader->load($resource, 'my-skill');
    }

    #[Test]
    public function it_throws_security_exception_for_backslash_absolute_path(): void
    {
        $this->expectException(SecurityException::class);

        $resource = new SkillResource('evil', '', '\\windows\\system32');
        $this->loader->load($resource, 'my-skill');
    }

    #[Test]
    public function it_throws_security_exception_for_traversal_sequence(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessageMatches('/traversal/i');

        $resource = new SkillResource('evil', '', '../other-skill/SKILL.md');
        $this->loader->load($resource, 'my-skill');
    }

    #[Test]
    public function it_throws_skill_parse_exception_for_missing_file(): void
    {
        $this->expectException(SkillParseException::class);

        $resource = new SkillResource('missing', '', 'resources/does-not-exist.md');
        $this->loader->load($resource, 'my-skill');
    }
}
