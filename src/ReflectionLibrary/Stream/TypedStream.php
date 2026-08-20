<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Stream;

use FFI\Reflection\Exception\CorruptedBinaryException;
use FFI\Reflection\ReflectionLibrary\Endianness;

/**
 * A stream reading values of the binary types instead of raw bytes.
 *
 * The byte order belongs to the stream itself, taken from the header of the
 * image being read, and an individual read may still name one of its own.
 */
final class TypedStream implements StreamInterface
{
    /**
     * Position of the underlying stream, shared with every byte order copy
     * of this one.
     */
    public int $offset {
        get => $this->stream->offset;
        // A block body keeps the property virtual.
        set {
            $this->stream->offset = $value;
        }
    }

    public function __construct(
        private readonly StreamInterface $stream,
        /**
         * Order the bytes of a multi-byte value are read in.
         */
        public private(set) Endianness $endianness = Endianness::HOST,
    ) {}

    public function withLittleEndian(): self
    {
        return $this->withByteOrder(Endianness::Little);
    }

    public function withBigEndian(): self
    {
        return $this->withByteOrder(Endianness::Big);
    }

    /**
     * Gets a copy of this stream reading values in the given byte order.
     *
     * The copy shares its position with the original, so moving one of them
     * moves the other.
     */
    public function withByteOrder(Endianness $endianness): self
    {
        if ($this->endianness === $endianness) {
            return $this;
        }

        $self = clone $this;
        $self->endianness = $endianness;

        return $self;
    }

    public function read(int $bytes): string
    {
        return $this->stream->read($bytes);
    }

    /**
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    private function readAs(Type $type): int|float
    {
        $result = \unpack($type->value, $this->read($type->getSize()));
        $value = $result === false ? false : \reset($result);

        if (!\is_int($value) && !\is_float($value)) {
            throw CorruptedBinaryException::becauseImageIsMalformed(\sprintf(
                'Could not read a %s value at offset 0x%X',
                $type->name,
                $this->offset,
            ));
        }

        return $value;
    }

    /**
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    public function int8(): int
    {
        return (int) $this->readAs(Type::Int8);
    }

    /**
     * @return int<0, 255>
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    public function uint8(): int
    {
        /** @var int<0, 255> */
        return (int) $this->readAs(Type::UInt8);
    }

    /**
     * Note that PHP has no endianness-aware format for signed values, so the
     * byte order of the host is used.
     *
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    public function int16(): int
    {
        return (int) $this->readAs(Type::Int16);
    }

    /**
     * @return int<0, 65535>
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    public function uint16(?Endianness $endianness = null): int
    {
        /** @var int<0, 65535> */
        return (int) $this->readAs(
            Type::UInt16->withByteOrder($endianness ?? $this->endianness),
        );
    }

    /**
     * Note that PHP has no endianness-aware format for signed values, so the
     * byte order of the host is used.
     *
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    public function int32(): int
    {
        return (int) $this->readAs(Type::Int32);
    }

    /**
     * @return int<0, 4294967295>
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    public function uint32(?Endianness $endianness = null): int
    {
        /** @var int<0, 4294967295> */
        return (int) $this->readAs(
            Type::UInt32->withByteOrder($endianness ?? $this->endianness),
        );
    }

    /**
     * Note that PHP has no endianness-aware format for signed values, so the
     * byte order of the host is used.
     *
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    public function int64(): int
    {
        return (int) $this->readAs(Type::Int64);
    }

    /**
     * Note that PHP integers are signed, so a value above
     * {@see \PHP_INT_MAX} is returned as a negative number.
     *
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    public function uint64(?Endianness $endianness = null): int
    {
        return (int) $this->readAs(
            Type::UInt64->withByteOrder($endianness ?? $this->endianness),
        );
    }

    /**
     * Reads an unsigned little endian base 128 value.
     *
     * The encoding stores seven bits of the value per byte, the high bit
     * marking that another byte follows, which keeps a small number
     * small. Binary formats use it wherever most values are near zero.
     *
     * @return int<0, max>
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    public function uleb128(): int
    {
        $result = 0;
        $shift = 0;

        do {
            $byte = $this->uint8();
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while (($byte & 0x80) !== 0 && $shift < 64);

        /** @var int<0, max> */
        return $result;
    }

    /**
     * Reads an unsigned value of the native width of the image.
     *
     * @param bool $is64bit whether the image uses 64 bit addresses
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    public function address(bool $is64bit, ?Endianness $endianness = null): int
    {
        return $is64bit
            ? $this->uint64($endianness)
            : $this->uint32($endianness);
    }

    /**
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    public function float32(?Endianness $endianness = null): float
    {
        return (float) $this->readAs(
            Type::Float32->withByteOrder($endianness ?? $this->endianness),
        );
    }

    /**
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    public function float64(?Endianness $endianness = null): float
    {
        return (float) $this->readAs(
            Type::Float64->withByteOrder($endianness ?? $this->endianness),
        );
    }

    /**
     * Reads a 32 bit unix timestamp.
     *
     * @throws CorruptedBinaryException in case of the value cannot be decoded
     */
    public function timestamp(?Endianness $endianness = null): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . $this->uint32($endianness));
    }

    /**
     * Reads a sequence of values of the same type.
     *
     * @return list<int|float>
     * @throws CorruptedBinaryException in case of a value cannot be decoded
     */
    public function array(int $size, Type $type): array
    {
        $result = [];

        for ($i = 0; $i < $size; ++$i) {
            $result[] = $this->readAs($type);
        }

        return $result;
    }

    /**
     * Reads a single byte as a string.
     */
    public function char(): string
    {
        return $this->read(1);
    }

    /**
     * Reads a string.
     *
     * Without a size the string is read up to the first null byte, which is
     * also how the end of the stream is reported. With a size exactly that
     * many bytes are consumed and the trailing null bytes are trimmed, which
     * is the layout of the fixed width name fields of most binary formats.
     */
    public function string(?int $size = null): string
    {
        if ($size !== null) {
            return \rtrim($this->read($size), "\x00");
        }

        $result = '';

        while (($char = $this->read(1)) !== "\x00") {
            $result .= $char;
        }

        return $result;
    }

    /**
     * Runs the given handler and restores the position afterwards.
     *
     * @template TResult of mixed
     * @param callable(self): TResult $handler
     * @return TResult
     */
    public function lookahead(callable $handler): mixed
    {
        $offset = $this->offset;

        try {
            return $handler($this);
        } finally {
            $this->offset = $offset;
        }
    }

    /**
     * Reads the given number of bytes into an independent in-memory stream.
     *
     * The result keeps the byte order of this stream but has its own
     * position, which makes it the tool of choice for walking a table
     * without disturbing the outer read.
     *
     * @throws CorruptedBinaryException in case of the memory stream cannot be opened
     */
    public function slice(int $bytes): self
    {
        return new self(
            stream: Stream::createFromString($this->read($bytes)),
            endianness: $this->endianness,
        );
    }

    /**
     * Reads the given number of bytes as a list of bits, most significant
     * bit of every byte first.
     *
     * @return list<bool>
     */
    public function bitmask(int $bytes): array
    {
        $result = [];

        for ($i = 0; $i < $bytes; ++$i) {
            $byte = \ord($this->read(1));

            for ($bit = 7; $bit >= 0; --$bit) {
                $result[] = ($byte >> $bit & 1) === 1;
            }
        }

        return $result;
    }
}
