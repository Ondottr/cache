<?php

declare(strict_types=1);

namespace PHP_SF\Cache\Abstracts;

use DateInterval;
use PHP_SF\Cache\Adapter\APCuCacheAdapter;
use PHP_SF\Cache\Adapter\FileSystemCacheAdapter;
use PHP_SF\Cache\Adapter\MemcachedCacheAdapter;
use PHP_SF\Cache\Adapter\RedisCacheAdapter;
use PHP_SF\Cache\CacheInterface;
use PHP_SF\Cache\Exception\CacheKeyExceptionCache;
use PHP_SF\Cache\Exception\CacheValueException;
use PHP_SF\Cache\Exception\InvalidCacheArgumentException;

/**
 * Base class for all cache adapters.
 *
 * Provides a singleton registry (one instance per concrete adapter class) and default
 * implementations of the PSR-16 batch operations built on top of the scalar
 * {@link get()}, {@link set()}, and {@link delete()} primitives.
 *
 * Concrete adapters must implement: {@link get()}, {@link set()}, {@link delete()},
 * {@link clear()}, {@link has()}, and {@link deleteByKeyPattern()}.
 */
abstract class AbstractCacheAdapter implements CacheInterface
{
    /** Fully-qualified class name of {@link APCuCacheAdapter}. For use with {@link ca()}. */
    public const string APCU_CACHE_ADAPTER       = APCuCacheAdapter::class;

    /** Fully-qualified class name of {@link RedisCacheAdapter}. For use with {@link ca()}. */
    public const string REDIS_CACHE_ADAPTER      = RedisCacheAdapter::class;

    /** Fully-qualified class name of {@link MemcachedCacheAdapter}. For use with {@link ca()}. */
    public const string MEMCACHED_CACHE_ADAPTER  = MemcachedCacheAdapter::class;

    /** Fully-qualified class name of {@link FileSystemCacheAdapter}. For use with {@link ca()}. */
    public const string FILESYSTEM_CACHE_ADAPTER = FileSystemCacheAdapter::class;

    /** Default TTL in seconds (24 hours). Applied when no explicit TTL is passed to {@link set()}. */
    protected const int DEFAULT_TTL = 86400;

    /** @var static[] Singleton registry keyed by concrete adapter class name. */
    private static array $instances = [];


    /**
     * Returns the singleton instance for the calling adapter class.
     * Creates it on first call; subsequent calls return the same object.
     */
    public static function getInstance(): static
    {
        if (array_key_exists(static::class, self::$instances) === false) {
            self::setInstance();
        }

        return self::$instances[ static::class ];
    }

    /** Instantiates and caches the singleton for the calling adapter class. */
    private static function setInstance(): void
    {
        self::$instances[ static::class ] = match (static::class) {
            self::APCU_CACHE_ADAPTER       => new APCuCacheAdapter(),
            self::REDIS_CACHE_ADAPTER      => new RedisCacheAdapter(),
            self::MEMCACHED_CACHE_ADAPTER  => new MemcachedCacheAdapter(),
            self::FILESYSTEM_CACHE_ADAPTER => FileSystemCacheAdapter::getInstance(),
            default                        => throw new InvalidCacheArgumentException('Invalid cache adapter type'),
        };
    }


    /**
     * Fetches multiple cache values in a single call.
     *
     * @param iterable<string> $keys    Cache keys to fetch.
     * @param mixed            $default Default value for missing keys.
     *
     * @return array<string, mixed> Map of key → value; missing keys map to $default.
     *
     * @throws CacheKeyExceptionCache If any key is not a string.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            if (is_string($key) === false) {
                throw new CacheKeyExceptionCache();
            }

            $result[ $key ] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * Persists multiple key-value pairs in a single call.
     *
     * Values must be scalar. Use {@link FileSystemCacheAdapter::setMultiple()} for arrays/objects.
     *
     * @param iterable<string, scalar> $values Key-value pairs to store.
     * @param DateInterval|int|null    $ttl    TTL in seconds, a DateInterval, or null for no expiry.
     *
     * @throws CacheKeyExceptionCache If any key is not a string.
     * @throws CacheValueException    If any value is not scalar.
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = self::DEFAULT_TTL): bool
    {
        $result = true;

        foreach ($values as $key => $value) {
            if (is_string($key) === false) {
                throw new CacheKeyExceptionCache();
            }

            if (is_scalar($value) === false) {
                throw new CacheValueException();
            }

            $result = $result && $this->set($key, $value, $ttl);
        }

        return $result;
    }

    /**
     * Deletes multiple cache keys in a single call.
     *
     * @param iterable<string> $keys Keys to delete.
     *
     * @throws CacheKeyExceptionCache If any key is not a string.
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $result = true;

        foreach ($keys as $key) {
            if (is_string($key) === false) {
                throw new CacheKeyExceptionCache();
            }

            $result = $result && $this->delete($key);
        }

        return $result;
    }


    private function __clone(): void
    {
    }

}
