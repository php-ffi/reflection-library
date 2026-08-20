<?php

declare(strict_types=1);

namespace FFI\Reflection\Exception;

/**
 * A driver recognised the file but its contents do not add up.
 *
 * A structure points outside the file, a size makes no sense, a signature
 * contradicts the one before it. Nothing a caller can fix: the file itself
 * is broken, or was produced by something that disagrees with the format.
 */
final class CorruptedBinaryException extends LibraryException
{
    public static function becauseImageIsMalformed(string $message, ?\Throwable $prev = null): self
    {
        return new self($message, previous: $prev);
    }
}
