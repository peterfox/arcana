<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Laravel;

use Illuminate\Contracts\Cache\Repository as IlluminateCacheRepository;
use Illuminate\Support\ServiceProvider;
use PeterFox\Arcana\Cache\NullCache;
use PeterFox\Arcana\Contract\SkillLibraryInterface;
use PeterFox\Arcana\Contract\SkillPreprocessorInterface;
use PeterFox\Arcana\SkillLibrary;
use Psr\SimpleCache\CacheInterface;

/**
 * Laravel service provider for Arcana.
 *
 * Auto-discovered by Laravel's package discovery. Registers:
 *
 *   - SkillLibraryInterface  (singleton → SkillLibrary)
 *   - 'arcana.library'       (alias for the above)
 *   - Arcana facade          (via composer.json extra.laravel.aliases)
 *
 * Publish the config:
 *   php artisan vendor:publish --tag=arcana-config
 */
final class ArcanaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../../config/arcana.php',
            key: 'arcana',
        );

        $this->app->singleton(SkillLibraryInterface::class, function (): SkillLibrary {
            /** @var array<string, mixed> $config */
            $config = $this->app['config']['arcana'];

            return new SkillLibrary(
                directories: (array) ($config['directories'] ?? []),
                cache: $this->resolveCache($config),
                preprocessor: $this->resolvePreprocessor($config),
                cacheTtl: (int) ($config['cache']['ttl'] ?? 3600),
                cachePrefix: (string) ($config['cache']['prefix'] ?? 'arcana.'),
            );
        });

        $this->app->alias(SkillLibraryInterface::class, SkillLibrary::class);
        $this->app->alias(SkillLibraryInterface::class, 'arcana.library');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/arcana.php' => config_path('arcana.php'),
            ], 'arcana-config');

            $this->commands([
                Commands\ListSkillsCommand::class,
                Commands\ShowSkillCommand::class,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveCache(array $config): CacheInterface
    {
        $cacheConfig = $config['cache'] ?? [];

        if (!($cacheConfig['enabled'] ?? true)) {
            return new NullCache();
        }

        // Wrap Laravel's cache repository as a PSR-16 adapter if available.
        if ($this->app->bound(IlluminateCacheRepository::class)) {
            $store = $cacheConfig['store'] ?? null;
            $repository = $store
                ? $this->app['cache']->store($store)
                : $this->app['cache']->store();

            // Laravel's cache repository implements PSR-16 in Laravel 11+
            if ($repository instanceof CacheInterface) {
                return $repository;
            }

            // For older versions, wrap in a simple adapter.
            return new IlluminateCacheAdapter($repository);
        }

        return new NullCache();
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolvePreprocessor(array $config): ?SkillPreprocessorInterface
    {
        $class = $config['preprocessor'] ?? null;

        if ($class === null || $class === '') {
            return null;
        }

        if (!class_exists($class)) {
            return null;
        }

        $instance = $this->app->make($class);

        if (!$instance instanceof SkillPreprocessorInterface) {
            return null;
        }

        return $instance;
    }
}
