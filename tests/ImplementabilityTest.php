<?php

declare(strict_types=1);

namespace Psr\SimpleCache\Tests;

use ArrayIterator;
use DateInterval;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Psr\SimpleCache\Tests\Fixtures\ConformingCacheException;
use Psr\SimpleCache\Tests\Fixtures\ConformingInvalidArgumentException;
use Psr\SimpleCache\Tests\Fixtures\MinimalCache;

final class ImplementabilityTest extends TestCase
{
    public function testMinimalCacheImplementsCacheInterface(): void
    {
        $cache = new MinimalCache();
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    public function testEachMethodAcceptsTheDocumentedArguments(): void
    {
        $cache = new MinimalCache();
        $this->assertInstanceOf(CacheInterface::class, $cache);

        $cache->get('alpha');
        $cache->get('alpha', 'default-value');
        $cache->set('alpha', 'v');
        $cache->set('alpha', 'v', null);
        $cache->set('alpha', 1, 5);
        $cache->set('b_1.ok', 'v', new DateInterval('PT5S'));
        $cache->delete('alpha');
        $cache->clear();
        $cache->getMultiple(['alpha', 'beta']);
        $cache->getMultiple(new ArrayIterator(['alpha']), 'default-value');
        $cache->setMultiple(['alpha' => 'v']);
        $cache->setMultiple(new ArrayIterator(['alpha' => 'v']), null);
        $cache->setMultiple(['alpha' => 'v'], new DateInterval('PT5S'));
        $cache->deleteMultiple(['alpha']);
        $cache->deleteMultiple(new ArrayIterator(['beta']));
        $cache->has('alpha');
    }

    public function testExceptionInterfacesAcceptConformingClasses(): void
    {
        $cacheException = new ConformingCacheException();
        $this->assertInstanceOf(CacheException::class, $cacheException);

        $invalidArgument = new ConformingInvalidArgumentException();
        $this->assertInstanceOf(InvalidArgumentException::class, $invalidArgument);
        $this->assertInstanceOf(CacheException::class, $invalidArgument);
    }
}
