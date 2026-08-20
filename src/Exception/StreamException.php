<?php

declare(strict_types=1);

namespace FFI\Reflection\Exception;

/**
 * The source of bytes cannot be used at all.
 *
 * Unlike every other failure this one is about the plumbing rather than
 * about a library: a caller handed over something that is not a stream, or
 * the process could not open one.
 */
final class StreamException extends ReflectionException
{
    /**
     * Occurs when the value handed over is not an open stream resource.
     */
    public static function becauseValueIsNotAStream(string $type, ?\Throwable $prev = null): self
    {
        $message = \sprintf('Expected an open stream resource, but %s given', $type);

        return new self($message, previous: $prev);
    }

    /**
     * Occurs when the process cannot open a stream over its own memory.
     */
    public static function becauseMemoryIsNotAvailable(?\Throwable $prev = null): self
    {
        return new self('Could not open an in-memory stream', previous: $prev);
    }
}
