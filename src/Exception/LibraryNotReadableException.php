<?php

declare(strict_types=1);

namespace FFI\Reflection\Exception;

/**
 * The library was located but could not be opened.
 *
 * The file is there and the process is not allowed to read it, or
 * something else holds it in a way that rules reading out.
 */
final class LibraryNotReadableException extends LibraryException
{
    public static function becauseFileIsNotReadable(string $pathname, ?\Throwable $prev = null): self
    {
        $message = \sprintf(
            'FFI library "%s" could not be opened for reading',
            \addcslashes($pathname, '"'),
        );

        return new self($message, previous: $prev);
    }
}
