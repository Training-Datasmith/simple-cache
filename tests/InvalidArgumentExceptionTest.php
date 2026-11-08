<?php

declare(strict_types=1);

namespace Psr\SimpleCache\Tests;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\InvalidArgumentException;
use Psr\SimpleCache\Tests\Fixtures\ConformingInvalidArgumentException;
use ReflectionClass;

final class InvalidArgumentExceptionTest extends TestCase
{
    public function testIsInterface(): void
    {
        $reflection = new ReflectionClass(InvalidArgumentException::class);
        $this->assertTrue($reflection->isInterface());
    }

    public function testExtendsOnlyCacheException(): void
    {
        $this->assertSame(
            ['Psr\SimpleCache\CacheException'],
            $this->directParentInterfaces(InvalidArgumentException::class, CacheException::class)
        );
    }

    /**
     * @return list<string>
     */
    private function directParentInterfaces(string $child, string $parent): array
    {
        $childNames = (new ReflectionClass($child))->getInterfaceNames();
        $parentNames = (new ReflectionClass($parent))->getInterfaceNames();
        $direct = array_values(array_diff($childNames, $parentNames));
        sort($direct);

        return $direct;
    }

    public function testIsThrowableThroughCacheException(): void
    {
        $reflection = new ReflectionClass(InvalidArgumentException::class);
        $this->assertTrue($reflection->implementsInterface(\Throwable::class));
    }

    public function testDeclaresNoOwnMethodsOrConstants(): void
    {
        $reflection = new ReflectionClass(InvalidArgumentException::class);

        $ownMethods = array_filter(
            $reflection->getMethods(),
            static fn ($method) => $method->getDeclaringClass()->getName() === InvalidArgumentException::class
        );
        $this->assertSame([], array_values($ownMethods));
        $this->assertSame([], $reflection->getConstants());
    }

    public function testThrownValueIsCatchableAsCacheException(): void
    {
        try {
            throw new ConformingInvalidArgumentException('bad');
        } catch (CacheException $caught) {
            $this->assertInstanceOf(InvalidArgumentException::class, $caught);

            return;
        }

        $this->fail('Expected CacheException to be caught');
    }

    public function testLivesInPsrSimpleCacheSrc(): void
    {
        $reflection = new ReflectionClass(InvalidArgumentException::class);
        $this->assertSame('Psr\SimpleCache', $reflection->getNamespaceName());

        $fileName = $reflection->getFileName();
        $this->assertIsString($fileName);
        $resolved = realpath($fileName);
        $this->assertIsString($resolved);
        $this->assertSame('InvalidArgumentException.php', basename($resolved));
        $this->assertSame('src', basename(dirname($resolved)));
    }
}
