<?php

declare (strict_types=1);
namespace Psr\Simple_Cache;

/**
 * PSR-16 Simple Cache Interface.
 *
 * Provides a simplified key-value cache contract compared to PSR-6. Use this
 * when you need basic get/set/delete operations without the overhead of pool
 * and item objects. For per-item TTL control, deferred writes, or batch fetching
 * with hit/miss semantics, use PSR-6 (psr/cache) instead.
 *
 * @since 1.0
 * @see https://www.php-fig.org/psr/psr-16/
 */
interface Cache_Interface
{
    /**
     * Fetches a value from the cache.
     *
     * A null return value is ambiguous: it may mean the key is absent or that
     * null was explicitly cached. To distinguish the two cases, either use a
     * sentinel $default (e.g., a unique object) or check has() first (though
     * has() is subject to a TOCTOU race; see its documentation).
     *
     * @param string $key The unique cache key for the item to fetch. Keys MUST
     *   conform to the PSR-16 key specification (no {}()/\@: characters,
     *   maximum 64 characters).
     * @param mixed $default The value to return on a cache miss. Defaults to null.
     *   Pass a unique sentinel object to distinguish a stored null from a miss.
     *
     * @return mixed The cached value, or $default if the key does not exist or
     *   the entry has expired.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException If $key is not a legal
     *   PSR-16 cache key string.
     *
     * @since 1.0
     */
    public function get(string $key, mixed $default = null): mixed;
    /**
     * Persists data in the cache, uniquely referenced by a key with an optional TTL.
     *
     * Stores $value under $key, overwriting any existing entry for that key.
     * An integer $ttl is interpreted as seconds from now. A DateInterval allows
     * richer duration expressions. Null means use the driver's default TTL (which
     * may be indefinite or driver-configured).
     *
     * @param string $key The cache key under which to store the value. Must be a
     *   valid PSR-16 key (no {}()/\@: characters, maximum 64 characters).
     * @param mixed $value The value to cache. Must be serializable by the
     *   implementing library. Resources and closures are generally not serializable.
     * @param null|int|\DateInterval $ttl The time-to-live for this entry. An integer
     *   is seconds from now; a DateInterval is an explicit duration; null uses the
     *   driver default. A TTL of 0 MAY result in immediate expiry (driver-defined).
     *
     * @return bool True if the value was stored successfully, false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException If $key is not a legal
     *   PSR-16 cache key string.
     *
     * @since 1.0
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool;
    /**
     * Delete an item from the cache by its unique key.
     *
     * Deleting a key that does not exist MUST return true; the post-condition
     * (the key is absent) is satisfied regardless. Returns false only when a
     * backend error prevents the deletion.
     *
     * @param string $key The cache key of the entry to remove.
     *
     * @return bool True if the item was successfully removed or was already absent.
     *   False only if a backend error prevented the deletion.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException If $key is not a legal
     *   PSR-16 cache key string.
     *
     * @since 1.0
     */
    public function delete(string $key): bool;
    /**
     * Wipes all entries from the cache.
     *
     * This operation removes every cached key in the store. Use with caution
     * in production; this is primarily intended for cache warming, maintenance
     * windows, or test setup/teardown.
     *
     * @return bool True if the cache was fully cleared. False if a backend error
     *   prevented the operation or if only a partial clear was possible.
     *
     * @since 1.0
     */
    public function clear(): bool;
    /**
     * Fetches multiple cache items by their unique keys in a single operation.
     *
     * Implementations SHOULD use a single batched backend request (e.g., Redis
     * MGET) rather than issuing one round-trip per key. Keys that are absent or
     * expired return $default in the result map.
     *
     * @param iterable<string> $keys The list of cache keys to retrieve. Each key
     *   must be a valid PSR-16 key string.
     * @param mixed $default The value to use for any key that is absent or expired.
     *
     * @return iterable<string, mixed> A key-value map where each requested key is
     *   present. Missing or expired keys map to $default.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException If $keys is not iterable
     *   or if any individual key is not a legal PSR-16 cache key string.
     *
     * @complexity O(n) where n is the number of keys; implementations SHOULD
     *   issue a single batched backend request.
     * @since 1.0
     */
    public function get_multiple(iterable $keys, mixed $default = null): iterable;
    /**
     * Persists a set of key-value pairs in the cache with a shared TTL.
     *
     * All pairs in $values are stored with the same $ttl. The TTL semantics
     * are identical to set(). Implementations SHOULD batch the writes into a
     * single backend operation for efficiency.
     *
     * @param iterable<string, mixed> $values A key-value map of items to store.
     *   Each key must be a valid PSR-16 key string; each value must be serializable.
     * @param null|int|\DateInterval $ttl The TTL applied to every entry in $values.
     *   An integer is seconds; a DateInterval is an explicit duration; null uses
     *   the driver default.
     *
     * @return bool True if all values were stored successfully. False if any write
     *   failed; partial success is implementation-defined.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException If $values is not iterable
     *   or if any key is not a legal PSR-16 cache key string.
     *
     * @complexity O(n) where n is the number of entries; implementations SHOULD
     *   issue a single batched backend request.
     * @since 1.0
     */
    public function set_multiple(iterable $values, null|int|\DateInterval $ttl = null): bool;
    /**
     * Deletes multiple cache items in a single operation.
     *
     * Like delete(), removing keys that do not exist is not an error. Returns
     * false only if a backend error prevents deletion of at least one key.
     * Implementations SHOULD batch deletes for efficiency.
     *
     * @param iterable<string> $keys The list of cache keys to delete.
     *
     * @return bool True if all specified keys were successfully deleted or were
     *   already absent. False if any deletion encountered a backend error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException If $keys is not iterable
     *   or if any key is not a legal PSR-16 cache key string.
     *
     * @complexity O(n) where n is the number of keys; implementations SHOULD
     *   issue a single batched backend request.
     * @since 1.0
     */
    public function delete_multiple(iterable $keys): bool;
    /**
     * Determines whether an item exists in the cache and has not expired.
     *
     * WARNING: This method is subject to a TOCTOU (time-of-check-to-time-of-use)
     * race condition. Between has() returning true and a subsequent get(), another
     * process may evict the entry, causing get() to return $default. For reliable
     * conditional caching, use get() with a unique sentinel $default instead of
     * has() + get(). Reserve has() for cache warming and cache status dashboards.
     *
     * @param string $key The cache key to check. Must be a valid PSR-16 key string.
     *
     * @return bool True if a valid, non-expired entry exists for $key. False if
     *   the key is absent or the entry has expired.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException If $key is not a legal
     *   PSR-16 cache key string.
     *
     * @since 1.0
     */
    public function has(string $key): bool;
}