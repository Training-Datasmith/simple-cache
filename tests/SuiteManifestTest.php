<?php

declare(strict_types=1);

namespace Psr\SimpleCache\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SuiteManifestTest extends TestCase
{
    private const EXPECTED_TEST_FILES = [
        'CacheExceptionTest.php',
        'CacheInterfaceTest.php',
        'ImplementabilityTest.php',
        'InvalidArgumentExceptionTest.php',
        'SuiteManifestTest.php',
    ];

    private const EXPECTED_PUBLIC_TEST_METHOD_COUNT = 30;

    public function testPhpFilesInTestsRootMatchTheHardcodedList(): void
    {
        $testsDir = dirname(__DIR__) . '/tests';
        $paths = glob($testsDir . '/*.php');
        $this->assertIsArray($paths);

        $basenames = array_map('basename', $paths);
        sort($basenames);

        $this->assertSame(self::EXPECTED_TEST_FILES, $basenames);

        foreach ($paths as $path) {
            $resolved = realpath($path);
            $this->assertIsString($resolved);
            $this->assertFileExists($resolved);
        }
    }

    public function testPublicTestMethodCountIs30(): void
    {
        $count = 0;
        foreach (self::EXPECTED_TEST_FILES as $file) {
            $class = 'Psr\\SimpleCache\\Tests\\' . substr($file, 0, -4);
            $reflection = new ReflectionClass($class);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (str_starts_with($method->getName(), 'test')) {
                    ++$count;
                }
            }
        }

        $this->assertSame(self::EXPECTED_PUBLIC_TEST_METHOD_COUNT, $count);
    }
}
