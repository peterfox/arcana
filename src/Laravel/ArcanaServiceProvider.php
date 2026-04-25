<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
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
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../../config/arcana.php',
            key: 'arcana',
        );

        $this->app->singleton(SkillLibraryInterface::class, function (): SkillLibrary {
            $config = $this->resolveConfig();

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
     * @return array<string, mixed>
     */
    private function resolveConfig(): array
    {
        $configRepo = $this->app->make(ConfigRepository::class);
        $raw = $configRepo->get('arcana', []);

        return is_array($raw) ? $raw : [];
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

        if (!$this->app->bound(CacheFactory::class)) {
            return new NullCache();
        }

        $cacheFactory = $this->app->make(CacheFactory::class);
        $store = $cacheConfig['store'] ?? null;

        return is_string($store) && $store !== ''
            ? $cacheFactory->store($store)
            : $cacheFactory->store();
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
