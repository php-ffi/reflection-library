<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Stream;

use FFI\Reflection\ReflectionLibrary\Endianness;

/**
 * A binary type, backed by the format character of {@see \unpack()}.
 *
 * Note that PHP offers no explicit byte order for signed integers, so the
 * {@see self::Int16}, {@see self::Int32} and {@see self::Int64} cases always
 * use the byte order of the host. Reading a signed value from an image of
 * the opposite endianness requires reading it as unsigned and converting it
 * by hand.
 */
enum Type: string
{
    /**
     * Signed integer of the size and byte order of the host.
     */
    case Int = 'i';

    /**
     * Unsigned integer of the size and byte order of the host.
     */
    case UInt = 'I';

    /**
     * Signed char, 8 bit.
     */
    case Int8 = 'c';

    /**
     * Unsigned char, 8 bit.
     */
    case UInt8 = 'C';

    /**
     * Signed short, 16 bit, byte order of the host.
     */
    case Int16 = 's';

    /**
     * Unsigned short, 16 bit, big endian.
     */
    case UInt16be = 'n';

    /**
     * Unsigned short, 16 bit, little endian.
     */
    case UInt16le = 'v';

    /**
     * Unsigned short, 16 bit, byte order of the host.
     */
    case UInt16 = 'S';

    /**
     * Signed long, 32 bit, byte order of the host.
     */
    case Int32 = 'l';

    /**
     * Unsigned long, 32 bit, big endian.
     */
    case UInt32be = 'N';

    /**
     * Unsigned long, 32 bit, little endian.
     */
    case UInt32le = 'V';

    /**
     * Unsigned long, 32 bit, byte order of the host.
     */
    case UInt32 = 'L';

    /**
     * Signed long long, 64 bit, byte order of the host.
     */
    case Int64 = 'q';

    /**
     * Unsigned long long, 64 bit, big endian.
     */
    case UInt64be = 'J';

    /**
     * Unsigned long long, 64 bit, little endian.
     */
    case UInt64le = 'P';

    /**
     * Unsigned long long, 64 bit, byte order of the host.
     */
    case UInt64 = 'Q';

    /**
     * Float, 32 bit, big endian.
     */
    case Float32be = 'G';

    /**
     * Float, 32 bit, little endian.
     */
    case Float32le = 'g';

    /**
     * Float, 32 bit, representation of the host.
     */
    case Float32 = 'f';

    /**
     * Double, 64 bit, big endian.
     */
    case Float64be = 'E';

    /**
     * Double, 64 bit, little endian.
     */
    case Float64le = 'e';

    /**
     * Double, 64 bit, representation of the host.
     */
    case Float64 = 'd';

    /**
     * Gets the number of bytes occupied by a value of this type.
     *
     * @return int<1, max>
     */
    public function getSize(): int
    {
        return match ($this) {
            self::Int,
            self::UInt => \PHP_INT_SIZE,
            self::Int8,
            self::UInt8 => 1,
            self::Int16,
            self::UInt16be,
            self::UInt16le,
            self::UInt16 => 2,
            self::Int32,
            self::UInt32be,
            self::UInt32le,
            self::UInt32,
            self::Float32be,
            self::Float32le,
            self::Float32 => 4,
            self::Int64,
            self::UInt64be,
            self::UInt64le,
            self::UInt64,
            self::Float64be,
            self::Float64le,
            self::Float64 => 8,
        };
    }

    /**
     * Gets the same type with an explicit byte order, in case of the type
     * has endianness-specific variants.
     */
    public function withByteOrder(Endianness $endianness): self
    {
        return match ($this) {
            self::UInt16,
            self::UInt16be,
            self::UInt16le => $endianness === Endianness::Little
                ? self::UInt16le
                : self::UInt16be,
            self::UInt32,
            self::UInt32be,
            self::UInt32le => $endianness === Endianness::Little
                ? self::UInt32le
                : self::UInt32be,
            self::UInt64,
            self::UInt64be,
            self::UInt64le => $endianness === Endianness::Little
                ? self::UInt64le
                : self::UInt64be,
            self::Float32,
            self::Float32be,
            self::Float32le => $endianness === Endianness::Little
                ? self::Float32le
                : self::Float32be,
            self::Float64,
            self::Float64be,
            self::Float64le => $endianness === Endianness::Little
                ? self::Float64le
                : self::Float64be,
            default => $this,
        };
    }
}
