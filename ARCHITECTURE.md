# Architecture: psr/simple-cache (PSR-16)

## Purpose

This package defines PSR-16: Simple Cache Interface. It provides a minimal
key-value cache contract as a simpler alternative to PSR-6 (psr/cache), intended
for use cases where you only need get/set/delete without explicit item objects,
deferred writes, or per-item hit/miss tracking.

## PSR Standard

**PSR-16** — https://www.php-fig.org/psr/psr-16/

## Directory Structure

```
src/
  Cache_Interface.php              — The eight-method simple cache contract
  Cache_Exception.php              — Marker interface for all cache exceptions
  Invalid_Argument_Exception.php   — Thrown for illegal cache keys or arguments
```

## Key Design Decisions

### Simpler than PSR-6
PSR-16 intentionally omits PSR-6's CacheItemInterface, pool objects, and
deferred writes. The trade-off: less expressive control per entry but drastically
less boilerplate for basic caching needs.

### Null return ambiguity
get() returns null on a cache miss by default. Because null is also a valid
cached value, callers who need to distinguish "miss" from "stored null" should
pass a unique sentinel as $default, or check has() (subject to TOCTOU caveats).

### has() TOCTOU warning
has() may return true but a subsequent get() may still miss if another process
evicts the entry between the two calls. For reliable cache-aside patterns, use
get() with a sentinel $default rather than has() + get().

### Batch operations
get_multiple(), set_multiple(), and delete_multiple() allow implementations to
issue a single batched backend request (e.g., Redis MGET/MSET/DEL), which can
dramatically reduce round-trips when working with many keys.

### Key restrictions
PSR-16 keys MUST NOT contain the characters {}()/\@: and MUST be at most 64
characters. These restrictions match PSR-6 and ensure broad backend compatibility.

## PSR-16 vs PSR-6

| Feature              | PSR-16 (simple-cache) | PSR-6 (cache) |
|----------------------|-----------------------|---------------|
| API complexity       | Low                   | Higher        |
| Per-item TTL control | Yes (via set())       | Yes           |
| Hit/miss object      | No                    | Yes           |
| Deferred writes      | No                    | Yes (commit)  |
| Batch fetch          | Yes (get_multiple)    | Yes (getItems)|

## Extension Points

- Implement `Cache_Interface` to build a new backend (Redis, Memcached, APCu, file).
- Decorate `Cache_Interface` to add namespacing, serialisation, encryption, or
  telemetry without changing consumer code.
- Implement `Cache_Exception` and `Invalid_Argument_Exception` for rich error context.

## Dependency Flow

```
Calling code
    └── Cache_Interface  (injected as dependency)
            └── Backend driver (Redis, Memcached, filesystem, etc.)
```
