<?php

declare(strict_types=1);

namespace Psr\SimpleCache\Tests\Fixtures;

use Psr\SimpleCache\InvalidArgumentException;

final class ConformingInvalidArgumentException extends \InvalidArgumentException implements InvalidArgumentException
{
}
