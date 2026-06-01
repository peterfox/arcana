<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tests\Unit;

use PeterFox\Arcana\Arcana;
use PeterFox\Arcana\Exception\SecurityException;
use PeterFox\Arcana\Exception\ValidationException;
use PeterFox\Arcana\NativeScriptRunner;
use PeterFox\Arcana\Skill;
use PeterFox\Arcana\SkillLibrary;
use PeterFox\Arcana\SkillMetadata;
use PeterFox\Arcana\SkillResource;
use PeterFox\Arcana\SkillScript;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Security-focused tests covering path traversal protection and input validation.
 */
#[CoversClass(SkillLibrary::class)]
#[CoversClass(Skill::class)]
#[CoversClass(NativeScriptRunner::class)]
final class SkillSecurityTest extends TestCase
{
    private SkillLibrary $library;

    #[\Override]
    protected function setUp(): void
    {
        $dir = realpath(__DIR__ . '/../Fixtures/skills')
            ?: throw new \RuntimeException('Fixtures not found');

        $this->library = Arcana::create($dir);
    }

    // -------------------------------------------------------------------------
    // Skill name validation (prevents directory traversal via loadSkill)
    // -------------------------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function invalidSkillNamesProvider(): array
    {
        return [
            'path traversal dots'     => ['../etc/passwd'],
            'absolute path'           => ['/etc/passwd'],
            'null byte'               => ["skill\0name"],
            'uppercase letters'       => ['My-Skill'],
            'spaces'                  => ['my skill'],
            'forward slash'           => ['my/skill'],
            'backslash'               => ['my\\skill'],
            'leading hyphen'          => ['-my-skill'],
            'empty string'            => [''],
            'exceeds 64 chars'        => [str_repeat('a', 65)],
            'colon separator'         => ['skill:name'],
            'at sign'                 => ['skill@name'],
        ];
    }

    #[Test]
    #[DataProvider('invalidSkillNamesProvider')]
    public function it_rejects_invalid_skill_names(string $name): void
    {
        $this->expectException(ValidationException::class);

        $this->library->loadSkill($name);
    }

    #[Test]
    #[DataProvider('invalidSkillNamesProvider')]
    public function has_skill_returns_false_for_invalid_names(string $name): void
    {
        // hasSkill must never throw — it must return false for bad inputs
        self::assertFalse($this->library->hasSkill($name));
    }

    // -------------------------------------------------------------------------
    // Resource path traversal (SecurityException via Skill::loadResource)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_throws_security_exception_for_traversal_resource_path(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessageMatches('/traversal/i');

        $skill = $this->makeSkillWithResource('../../../etc/passwd');
        $skill->loadResource('evil');
    }

    #[Test]
    public function it_throws_security_exception_for_absolute_resource_path(): void
    {
        $this->expectException(SecurityException::class);

        // Construct a path that leaves the skill directory
        $skill = $this->makeSkillWithResource('/etc/passwd');
        $skill->loadResource('evil');
    }

    #[Test]
    public function it_throws_for_unknown_resource_name(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/is not declared/');

        $skill = $this->library->loadSkill('example-skill');
        $skill->loadResource('resource-that-does-not-exist');
    }

    // -------------------------------------------------------------------------
    // Script path traversal (SecurityException via NativeScriptRunner)
    // -------------------------------------------------------------------------

    #[Test]
    public function it_throws_security_exception_for_traversal_script_path(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessageMatches('/traversal/i');
        $this->expectExceptionMessageMatches('/safety/i');

        $runner = $this->makeScriptRunner();
        $script = new SkillScript(name: 'evil', description: '', path: '../../../etc/passwd', language: 'php');
        $skillDir = realpath(__DIR__ . '/../Fixtures/skills/example') ?: '/tmp';

        $runner->run($script, $skillDir);
    }

    #[Test]
    public function it_throws_security_exception_for_absolute_script_path(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessageMatches('/safety/i');

        $runner = $this->makeScriptRunner();
        $script = new SkillScript(name: 'evil', description: '', path: '/etc/passwd', language: 'php');
        $skillDir = realpath(__DIR__ . '/../Fixtures/skills/example') ?: '/tmp';

        $runner->run($script, $skillDir);
    }

    #[Test]
    public function security_exception_message_names_the_script(): void
    {
        $runner = $this->makeScriptRunner();
        $script = new SkillScript(name: 'my-script', description: '', path: '../escape.php', language: 'php');
        $skillDir = realpath(__DIR__ . '/../Fixtures/skills/example') ?: '/tmp';

        try {
            $runner->run($script, $skillDir);
            self::fail('Expected SecurityException was not thrown.');
        } catch (SecurityException $e) {
            self::assertStringContainsString('my-script', $e->getMessage());
            self::assertStringContainsString('script', $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Directory validation
    // -------------------------------------------------------------------------

    #[Test]
    public function it_rejects_nonexistent_directory(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/not found|not a directory/i');

        Arcana::create('/this/does/not/exist/at/all');
    }

    #[Test]
    public function it_rejects_file_path_as_directory(): void
    {
        $this->expectException(ValidationException::class);

        $filePath = realpath(__DIR__ . '/../Fixtures/skills/example/SKILL.md')
            ?: throw new \RuntimeException('Fixture not found');
        Arcana::create($filePath);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeScriptRunner(): NativeScriptRunner
    {
        return new class extends NativeScriptRunner {
            #[\Override]
            protected function execute(SkillScript $script, string $resolvedPath): string
            {
                return '';
            }
        };
    }

    private function makeSkillWithResource(string $resourcePath): Skill
    {
        $metadata = new SkillMetadata(
            name: 'security-test',
            description: 'Security test skill',
            version: '1.0.0',
            author: null,
            tags: [],
            triggers: [],
            resources: [
                new SkillResource(
                    name: 'evil',
                    description: 'Malicious resource',
                    path: $resourcePath,
                ),
            ],
            scripts: [],
            references: [],
            filePath: realpath(__DIR__ . '/../Fixtures/skills/example/SKILL.md') ?: '/tmp/SKILL.md',
        );

        return new Skill(metadata: $metadata, body: 'test');
    }
}
