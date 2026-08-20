<?php

declare(strict_types=1);

namespace FFI\Reflection\Exception;

/**
 * The library could not be located.
 *
 * The name resolved to nothing the platform knows about, so there is no
 * file to look at. A caller usually answers this by spelling the path out
 * instead of relying on the search of the platform.
 */
final class LibraryNotFoundException extends LibraryException
{
    public static function becauseNameIsNotResolvable(string $library, ?\Throwable $prev = null): self
    {
        $message = \sprintf('FFI library "%s" does not exist', \addcslashes($library, '"'));

        return new self($message, previous: $prev);
    }
}
