<?php

declare(strict_types=1);

namespace PeterFox\Arcana\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * A no-op PSR-16 cache implementation used as the default.
 *
 * All writes succeed silently and all reads return the default value.
 * Use this when you want zero caching overhead, or as a safe default
 * in test environments.
 */
final class NullCache implements CacheInterface
{
    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    #[\Override]
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        return true;
    }

    #[\Override]
    public function delete(string $key): bool
    {
        return true;
    }

    #[\Override]
    public function clear(): bool
    {
        return true;
    }

    /** @param iterable<string> $keys */
    #[\Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $default;
        }
    }

    /**
     * @param iterable<string, mixed> $values
     */
    #[\Override]
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        return true;
    }

    /** @param iterable<string> $keys */
    #[\Override]
    public function deleteMultiple(iterable $keys): bool
    {
        return true;
    }

    #[\Override]
    public function has(string $key): bool
    {
        return false;
    }
}
