<?php

declare(strict_types=1);

namespace FFI\Reflection\Exception;

/**
 * The file was opened, but none of the registered drivers recognises it.
 *
 * Either the file is not a shared library at all, or it is one of a format
 * this version does not read yet. A caller may answer this by registering
 * a driver of its own.
 */
final class UnsupportedFormatException extends LibraryException
{
    /**
     * Occurs when every registered driver refuses the file.
     *
     * @param string|null $pathname name of the file behind the stream, used
     *        for the message only
     * @param int<0, max> $drivers number of drivers that were asked
     */
    public static function becauseNoDriverRecognisesIt(
        ?string $pathname = null,
        int $drivers = 0,
        ?\Throwable $prev = null,
    ): self {
        $message = \sprintf(
            'Binary format of the %s is not supported by any of the %d registered drivers',
            $pathname === null ? 'given stream' : '"' . \addcslashes($pathname, '"') . '" file',
            $drivers,
        );

        return new self($message, previous: $prev);
    }

    /**
     * Occurs when a driver recognises the file but refuses to read this
     * particular shape of it, like a container holding several images.
     */
    public static function becauseShapeOfFileIsNotSupported(string $message, ?\Throwable $prev = null): self
    {
        return new self($message, previous: $prev);
    }
}
