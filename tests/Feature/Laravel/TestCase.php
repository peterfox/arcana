<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Tests\Feature\Laravel;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PeterFox\Arcana\Laravel\ArcanaServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ArcanaServiceProvider::class];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('arcana.directories', [
            dirname(__DIR__, 2) . '/Fixtures/skills',
        ]);

        $app['config']->set('arcana.cache.enabled', false);

        // laravel/ai instantiates JsonSchemaTypeFactory directly; bind it for tests
        // that call tool->schema() via the container.
        $app->bind(JsonSchema::class, JsonSchemaTypeFactory::class);
    }
}
