<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tests\Feature\Laravel;

use PeterFox\Arcana\Contract\SkillLibraryInterface;
use PeterFox\Arcana\Laravel\ArcanaServiceProvider;
use PeterFox\Arcana\Laravel\Facades\Arcana;
use PeterFox\Arcana\SkillLibrary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ArcanaServiceProvider::class)]
final class ArcanaServiceProviderTest extends TestCase
{
    #[Test]
    public function it_binds_skill_library_interface(): void
    {
        $app = $this->app ?? throw new \RuntimeException('App not initialized');
        $library = $app->make(SkillLibraryInterface::class);

        self::assertInstanceOf(SkillLibrary::class, $library);
    }

    #[Test]
    public function it_registers_arcana_library_alias(): void
    {
        $app = $this->app ?? throw new \RuntimeException('App not initialized');
        $library = $app->make('arcana.library');

        self::assertInstanceOf(SkillLibraryInterface::class, $library);
    }

    #[Test]
    public function it_returns_the_same_singleton_instance(): void
    {
        $app = $this->app ?? throw new \RuntimeException('App not initialized');
        $first = $app->make(SkillLibraryInterface::class);
        $second = $app->make(SkillLibraryInterface::class);

        self::assertSame($first, $second);
    }

    #[Test]
    public function it_merges_default_config(): void
    {
        $app = $this->app ?? throw new \RuntimeException('App not initialized');
        $config = $app['config']['arcana'];

        self::assertIsArray($config);
        self::assertArrayHasKey('directories', $config);
        self::assertArrayHasKey('cache', $config);
        self::assertArrayHasKey('preprocessor', $config);
    }

    #[Test]
    public function it_uses_the_configured_skill_directory(): void
    {
        $app = $this->app ?? throw new \RuntimeException('App not initialized');
        $library = $app->make(SkillLibraryInterface::class);
        $skills = $library->listSkills();

        self::assertNotEmpty($skills, 'Library should discover skills from the test fixtures directory');
    }

    #[Test]
    public function facade_resolves_to_skill_library(): void
    {
        $library = Arcana::getFacadeRoot();

        self::assertInstanceOf(SkillLibraryInterface::class, $library);
    }
}
