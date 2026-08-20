<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Stream;

use FFI\Reflection\Exception\LibraryNotReadableException;
use FFI\Reflection\Exception\ReflectionException;
use FFI\Reflection\Exception\StreamException;

/**
 * A stream backed by a PHP stream resource, i.e. by an open file or by a
 * region of memory.
 */
final class Stream implements StreamInterface
{
    /**
     * Position of the file pointer, in bytes from the beginning.
     *
     * A negative value is not an error and leaves the position untouched.
     */
    public int $offset {
        get => \max(0, (int) \ftell($this->stream));
        // A block body keeps the property virtual.
        set {
            \fseek($this->stream, $value);
        }
    }

    /**
     * @throws ReflectionException in case of the given value is not a stream
     */
    public function __construct(
        /**
         * @var resource
         */
        private readonly mixed $stream,
        /**
         * Whether the underlying resource belongs to this object and has to
         * be closed together with it.
         */
        private readonly bool $closable = false,
    ) {
        if (!\is_resource($stream) || \get_resource_type($stream) !== 'stream') {
            throw StreamException::becauseValueIsNotAStream(\get_debug_type($stream));
        }
    }

    /**
     * Opens a file for reading, transferring its ownership to the stream.
     *
     * @param non-empty-string $pathname
     * @throws ReflectionException in case of the file cannot be opened
     */
    public static function createFromPathname(string $pathname): self
    {
        $stream = @\fopen($pathname, 'rb');

        if ($stream === false) {
            throw LibraryNotReadableException::becauseFileIsNotReadable($pathname);
        }

        return new self($stream, true);
    }

    /**
     * Creates an in-memory stream over the given bytes.
     *
     * @throws ReflectionException in case of the memory stream cannot be opened
     */
    public static function createFromString(string $data): self
    {
        $stream = \fopen('php://memory', 'rb+');

        if ($stream === false) {
            throw StreamException::becauseMemoryIsNotAvailable();
        }

        \fwrite($stream, $data);
        \rewind($stream);

        return new self($stream, true);
    }

    public function read(int $bytes): string
    {
        if ($bytes <= 0) {
            return '';
        }

        $result = '';

        // A stream is allowed to return less than requested.
        while (($remaining = $bytes - \strlen($result)) > 0) {
            $chunk = \fread($this->stream, $remaining);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $result .= $chunk;
        }

        return $result . \str_repeat("\x00", $bytes - \strlen($result));
    }

    public function __destruct()
    {
        if ($this->closable && \is_resource($this->stream)) {
            \fclose($this->stream);
        }
    }
}
