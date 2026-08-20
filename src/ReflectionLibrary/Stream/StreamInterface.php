<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Stream;

/**
 * A seekable source of bytes.
 */
interface StreamInterface
{
    /**
     * Current position in the stream, in bytes from its beginning.
     */
    public int $offset {
        get;
        set;
    }

    /**
     * Reads the requested number of bytes starting at the current offset and
     * advances it by the same amount.
     *
     * Reading past the end of the stream is not an error: the result is
     * padded with null bytes so that its length always equals the requested
     * one. A caller that needs to distinguish real data from padding has to
     * validate the sizes it reads from the image itself.
     */
    public function read(int $bytes): string;
}
