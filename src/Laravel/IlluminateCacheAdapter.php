<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Laravel;

use Illuminate\Contracts\Cache\Repository;
use Psr\SimpleCache\CacheInterface;

/**
 * Thin PSR-16 adapter over an Illuminate Cache Repository.
 *
 * Used internally by the service provider on Laravel versions where
 * the cache repository does not yet natively implement PSR-16.
 *
 * @internal
 */
final class IlluminateCacheAdapter implements CacheInterface
{
    public function __construct(
        private readonly Repository $cache,
    ) {}

    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($key, $default);
    }

    #[\Override]
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        if ($ttl instanceof \DateInterval) {
            return $this->cache->put($key, $value, $ttl);
        }

        if ($ttl === null) {
            return $this->cache->forever($key, $value);
        }

        return $this->cache->put($key, $value, $ttl);
    }

    #[\Override]
    public function delete(string $key): bool
    {
        return $this->cache->forget($key);
    }

    #[\Override]
    public function clear(): bool
    {
        return $this->cache->clear();
    }

    /** @param iterable<string> $keys */
    #[\Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    /** @param iterable<string, mixed> $values */
    #[\Override]
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /** @param iterable<string> $keys */
    #[\Override]
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    #[\Override]
    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }
}
