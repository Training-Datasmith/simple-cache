<?php

declare(strict_types=1);

/**
 * Example: PSR-16 Simple Cache — basic usage patterns.
 *
 * PSR-16 is the simpler alternative to PSR-6. Use it when you need
 * plain key-value caching without explicit item objects or deferred writes.
 */

use Psr\SimpleCache\Cache_Interface;

// --- Cache-aside pattern ---

function get_product(Cache_Interface $cache, int $product_id): array
{
    $key = 'product_' . $product_id;

    // get() returns null on a miss (unless you pass a custom $default).
    $cached = $cache->get($key);
    if ($cached !== null) {
        return $cached;  // Cache hit.
    }

    // Cache miss: fetch from database (simulated).
    $product = ['id' => $product_id, 'name' => 'Widget', 'price' => 9.99];

    // Store for 10 minutes.
    $cache->set($key, $product, 600);

    return $product;
}

// --- Distinguishing stored null from a cache miss ---

function get_optional_setting(Cache_Interface $cache, string $key): mixed
{
    $sentinel = new \stdClass();  // Unique object that can never be a cached value.
    $value    = $cache->get($key, $sentinel);

    if ($value === $sentinel) {
        return null;  // Genuine cache miss.
    }

    return $value;  // May legitimately be null if null was cached.
}

// --- Batch operations: fetch multiple keys at once ---

function get_user_preferences(Cache_Interface $cache, int $user_id): array
{
    $keys = [
        'pref_theme_'    . $user_id,
        'pref_language_' . $user_id,
        'pref_timezone_' . $user_id,
    ];

    // Single backend round-trip (e.g., Redis MGET).
    $results = $cache->get_multiple($keys, 'default');

    return [
        'theme'    => $results['pref_theme_'    . $user_id],
        'language' => $results['pref_language_' . $user_id],
        'timezone' => $results['pref_timezone_' . $user_id],
    ];
}

// --- Batch write ---

function cache_product_catalogue(Cache_Interface $cache, array $products): void
{
    $pairs = [];
    foreach ($products as $product) {
        $pairs['product_' . $product['id']] = $product;
    }

    // Single backend round-trip (e.g., Redis MSET).
    $cache->set_multiple($pairs, new \DateInterval('PT1H'));  // 1 hour TTL.
}

// --- Minimal in-memory implementation for illustration ---

final class Array_Cache implements Cache_Interface
{
    /** @var array<string, array{value: mixed, expires: ?int}> */
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->store[$key])) {
            return $default;
        }
        ['value' => $value, 'expires' => $expires] = $this->store[$key];
        if ($expires !== null && $expires < time()) {
            unset($this->store[$key]);
            return $default;
        }
        return $value;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $expires = null;
        if (is_int($ttl)) {
            $expires = time() + $ttl;
        } elseif ($ttl instanceof \DateInterval) {
            $expires = (new \DateTimeImmutable())->add($ttl)->getTimestamp();
        }
        $this->store[$key] = ['value' => $value, 'expires' => $expires];
        return true;
    }

    public function delete(string $key): bool          { unset($this->store[$key]); return true; }
    public function clear(): bool                       { $this->store = []; return true; }
    public function has(string $key): bool              { return $this->get($key, $s = new \stdClass()) !== $s; }
    public function get_multiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }
    public function set_multiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }
    public function delete_multiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }
}

// Usage
$cache = new Array_Cache();
$cache->set('greeting', 'Hello!', 60);
echo $cache->get('greeting');  // Hello!
