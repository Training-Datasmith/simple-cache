<?php

declare(strict_types=1);

namespace Psr\SimpleCache\Tests;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

final class CacheInterfaceTest extends TestCase
{
    public function testIsInterface(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $this->assertTrue($reflection->isInterface());
    }

    public function testHasNoParentInterface(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $this->assertSame([], $reflection->getInterfaceNames());
    }

    public function testOwnMethodsAreExactlyTheEightSpecMethods(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $names = array_map(
            static fn (ReflectionMethod $method) => $method->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC)
        );
        sort($names);
        $this->assertSame(
            ['clear', 'delete', 'deleteMultiple', 'get', 'getMultiple', 'has', 'set', 'setMultiple'],
            $names
        );
    }

    public function testGetSignature(): void
    {
        $this->assertMethodSignature(
            'get',
            [
                ['name' => 'key', 'type' => 'string', 'optional' => false],
                ['name' => 'default', 'type' => 'mixed', 'optional' => true, 'default' => null],
            ],
            'mixed'
        );
    }

    public function testSetSignature(): void
    {
        $this->assertMethodSignature(
            'set',
            [
                ['name' => 'key', 'type' => 'string', 'optional' => false],
                ['name' => 'value', 'type' => 'mixed', 'optional' => false],
                [
                    'name' => 'ttl',
                    'union' => ['DateInterval', 'int', 'null'],
                    'optional' => true,
                    'default' => null,
                ],
            ],
            'bool'
        );
    }

    public function testDeleteSignature(): void
    {
        $this->assertMethodSignature(
            'delete',
            [['name' => 'key', 'type' => 'string', 'optional' => false]],
            'bool'
        );
    }

    public function testClearSignature(): void
    {
        $this->assertMethodSignature('clear', [], 'bool');
    }

    public function testGetMultipleSignature(): void
    {
        $this->assertMethodSignature(
            'getMultiple',
            [
                ['name' => 'keys', 'type' => 'iterable', 'optional' => false],
                ['name' => 'default', 'type' => 'mixed', 'optional' => true, 'default' => null],
            ],
            'iterable'
        );
    }

    public function testSetMultipleSignature(): void
    {
        $this->assertMethodSignature(
            'setMultiple',
            [
                ['name' => 'values', 'type' => 'iterable', 'optional' => false],
                [
                    'name' => 'ttl',
                    'union' => ['DateInterval', 'int', 'null'],
                    'optional' => true,
                    'default' => null,
                ],
            ],
            'bool'
        );
    }

    public function testDeleteMultipleSignature(): void
    {
        $this->assertMethodSignature(
            'deleteMultiple',
            [['name' => 'keys', 'type' => 'iterable', 'optional' => false]],
            'bool'
        );
    }

    public function testHasSignature(): void
    {
        $this->assertMethodSignature(
            'has',
            [['name' => 'key', 'type' => 'string', 'optional' => false]],
            'bool'
        );
    }

    public function testThrowsDocblocksNameInvalidArgumentException(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $mustThrow = ['get', 'set', 'delete', 'getMultiple', 'setMultiple', 'deleteMultiple', 'has'];

        foreach ($mustThrow as $methodName) {
            $doc = $reflection->getMethod($methodName)->getDocComment();
            $this->assertIsString($doc);
            $this->assertNotSame('', trim($doc));
            $this->assertStringContainsString('MUST', $doc);
            $this->assertSame(
                InvalidArgumentException::class,
                $this->normalizeDocblockClass($this->extractThrowsClass($doc))
            );
        }

        $clearDoc = $reflection->getMethod('clear')->getDocComment();
        $this->assertIsString($clearDoc);
        $this->assertSame([], $this->extractAllThrowsClasses($clearDoc));
    }

    public function testParamDocblocksMatchSignatureTokens(): void
    {
        $expected = [
            'get' => ['string $key', 'mixed $default'],
            'set' => ['string $key', 'mixed $value', 'null|int|\DateInterval $ttl'],
            'delete' => ['string $key'],
            'clear' => [],
            'getMultiple' => ['iterable $keys', 'mixed $default'],
            'setMultiple' => ['iterable $values', 'null|int|\DateInterval $ttl'],
            'deleteMultiple' => ['iterable $keys'],
            'has' => ['string $key'],
        ];

        $reflection = new ReflectionClass(CacheInterface::class);
        foreach ($expected as $methodName => $params) {
            $doc = $reflection->getMethod($methodName)->getDocComment();
            $this->assertIsString($doc);
            $this->assertSame($params, $this->normalizeParamDocblocks($doc));
        }
    }

    public function testReturnDocblockTokens(): void
    {
        $expected = [
            'get' => 'mixed',
            'set' => 'bool',
            'delete' => 'bool',
            'clear' => 'bool',
            'getMultiple' => 'iterable<string,mixed>',
            'setMultiple' => 'bool',
            'deleteMultiple' => 'bool',
            'has' => 'bool',
        ];

        $reflection = new ReflectionClass(CacheInterface::class);
        foreach ($expected as $methodName => $token) {
            $doc = $reflection->getMethod($methodName)->getDocComment();
            $this->assertIsString($doc);
            $this->assertSame($token, $this->normalizeReturnDocblock($doc));
        }
    }

    public function testLivesInPsrSimpleCacheSrc(): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $this->assertSame('Psr\SimpleCache', $reflection->getNamespaceName());

        $fileName = $reflection->getFileName();
        $this->assertIsString($fileName);
        $resolved = realpath($fileName);
        $this->assertIsString($resolved);
        $this->assertSame('CacheInterface.php', basename($resolved));
        $this->assertSame('src', basename(dirname($resolved)));
    }

    /**
     * @param list<array<string, mixed>> $parameters
     */
    private function assertMethodSignature(string $methodName, array $parameters, string $returnTypeName): void
    {
        $reflection = new ReflectionClass(CacheInterface::class);
        $method = $reflection->getMethod($methodName);

        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isAbstract());
        $this->assertFalse($method->isStatic());
        $this->assertCount(count($parameters), $method->getParameters());

        foreach ($parameters as $index => $expected) {
            $parameter = $method->getParameters()[$index];
            $this->assertSame($expected['name'], $parameter->getName());
            $this->assertFalse($parameter->isPassedByReference());
            $this->assertFalse($parameter->isVariadic());

            if (isset($expected['union'])) {
                $type = $parameter->getType();
                $this->assertInstanceOf(ReflectionUnionType::class, $type);
                $names = array_map(
                    static fn (ReflectionNamedType $named) => $named->getName(),
                    $type->getTypes()
                );
                sort($names);
                $unionExpected = $expected['union'];
                sort($unionExpected);
                $this->assertSame($unionExpected, $names);
            } else {
                $type = $parameter->getType();
                $this->assertInstanceOf(ReflectionNamedType::class, $type);
                $this->assertSame($expected['type'], $type->getName());
            }

            if ($expected['optional']) {
                $this->assertTrue($parameter->isOptional());
                $this->assertTrue($parameter->isDefaultValueAvailable());
                $this->assertNull($parameter->getDefaultValue());
            } else {
                $this->assertFalse($parameter->isOptional());
                $this->assertFalse($parameter->isDefaultValueAvailable());
            }
        }

        $returnType = $method->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $returnType);
        $this->assertSame($returnTypeName, $returnType->getName());
    }

    private function extractThrowsClass(string $docblock): string
    {
        $classes = $this->extractAllThrowsClasses($docblock);
        $this->assertCount(1, $classes);

        return $classes[0];
    }

    /**
     * @return list<string>
     */
    private function extractAllThrowsClasses(string $docblock): array
    {
        if (!preg_match_all('/@throws\s+([^\s]+)/', $docblock, $matches)) {
            return [];
        }

        return array_values($matches[1]);
    }

    private function normalizeDocblockClass(string $class): string
    {
        return ltrim($class, '\\');
    }

    /**
     * @return list<string>
     */
    private function normalizeParamDocblocks(string $docblock): array
    {
        if (!preg_match_all('/@param\s+(\S+)\s+(\$\w+)/', $docblock, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $normalized = [];
        foreach ($matches as $match) {
            $type = preg_replace('/<[^>]+>/', '', $match[1]) ?? $match[1];
            $type = preg_replace('/\s+/', '', $type) ?? $type;
            if (str_contains($type, 'DateInterval')) {
                $type = preg_replace('/\\\\?DateInterval/', '\\DateInterval', $type) ?? $type;
            }
            $normalized[] = $type . ' ' . $match[2];
        }

        return $normalized;
    }

    private function normalizeReturnDocblock(string $docblock): string
    {
        if (!preg_match('/@return\s+(\w+(?:<[^>]+>)?)/', $docblock, $matches)) {
            $this->fail('Missing @return in docblock');
        }

        return preg_replace('/\s+/', '', $matches[1]) ?? '';
    }
}
