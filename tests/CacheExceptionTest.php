<?php

declare(strict_types=1);

namespace Psr\SimpleCache\Tests;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheException;
use ReflectionClass;

final class CacheExceptionTest extends TestCase
{
    public function testIsInterface(): void
    {
        $reflection = new ReflectionClass(CacheException::class);
        $this->assertTrue($reflection->isInterface());
    }

    public function testExtendsOnlyThrowable(): void
    {
        $reflection = new ReflectionClass(CacheException::class);
        $fileName = $reflection->getFileName();
        $this->assertIsString($fileName);
        $source = file_get_contents($fileName);
        $this->assertIsString($source);
        $this->assertMatchesRegularExpression(
            '/interface\s+CacheException\s+extends\s+\\\\?Throwable\b/',
            $source
        );
    }

    public function testDeclaresNoOwnMethodsOrConstants(): void
    {
        $reflection = new ReflectionClass(CacheException::class);

        $ownMethods = array_filter(
            $reflection->getMethods(),
            static fn ($method) => $method->getDeclaringClass()->getName() === CacheException::class
        );
        $this->assertSame([], array_values($ownMethods));

        $this->assertSame([], $reflection->getConstants());
    }

    public function testLivesInPsrSimpleCacheSrc(): void
    {
        $reflection = new ReflectionClass(CacheException::class);
        $this->assertSame('Psr\SimpleCache', $reflection->getNamespaceName());

        $fileName = $reflection->getFileName();
        $this->assertIsString($fileName);
        $resolved = realpath($fileName);
        $this->assertIsString($resolved);
        $this->assertSame('CacheException.php', basename($resolved));
        $this->assertSame('src', basename(dirname($resolved)));
    }
}
