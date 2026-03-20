<?php

declare (strict_types=1);
namespace Psr\Simple_Cache;

/**
 * Exception interface for invalid cache arguments.
 *
 * Thrown when a cache method receives an argument that violates the PSR-16
 * contract. The most common trigger is a key string that contains reserved
 * characters ({}()/\@:) or exceeds 64 characters in length.
 *
 * @since 1.0
 * @see Cache_Interface::get() Primary method that throws this for invalid keys.
 */
interface InvalidArgumentException extends Cache_Exception
{
}