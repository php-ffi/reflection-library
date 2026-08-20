<?php

declare(strict_types=1);

namespace FFI\Reflection\Exception;

/**
 * The library was read, but it does not depend on the library asked for.
 *
 * An ordinary lookup miss rather than a defect: the file is fine and the
 * name simply is not among its dependencies.
 */
final class ImportNotFoundException extends LibraryException
{
    public static function becauseLibraryDoesNotDependOnIt(
        string $name,
        string $library,
        ?\Throwable $prev = null,
    ): self {
        $message = \sprintf(
            'FFI library "%s" does not depend on a library named "%s"',
            \addcslashes($library, '"'),
            \addcslashes($name, '"'),
        );

        return new self($message, previous: $prev);
    }
}
