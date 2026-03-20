<?php

declare (strict_types=1);
namespace Psr\Simple_Cache;

/**
 * Marker interface for all exceptions thrown by a PSR-16 implementing library.
 *
 * Catching this interface allows consumers to handle every cache-related
 * error with a single catch block, regardless of the specific failure type.
 * All exceptions from Cache_Interface methods MUST implement this interface
 * so callers can distinguish cache errors from unrelated application exceptions.
 *
 * @since 1.0
 * @see https://www.php-fig.org/psr/psr-16/
 */
interface Cache_Exception extends \Throwable
{
}