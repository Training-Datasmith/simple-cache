<?php

declare(strict_types=1);

namespace Psr\SimpleCache\Tests\Fixtures;

use Psr\SimpleCache\CacheException;

final class ConformingCacheException extends \Exception implements CacheException
{
}
