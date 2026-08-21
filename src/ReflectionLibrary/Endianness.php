<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary;

/**
 * Whether the machine running this code stores multi-byte values least
 * significant byte first.
 */
define('FFI\\Reflection\\ReflectionLibrary\\IS_LITTLE_ENDIAN', \pack('S', 1) === "\x01\x00");

/**
 * Order in which the bytes of a multi-byte value are stored.
 *
 * A number wider than a byte can be laid out in memory in two directions, and
 * a processor family picks one of them. Reading a value the wrong way round
 * yields a different number rather than an error, so the order is what every
 * number taken out of a binary file has to be interpreted with.
 */
enum Endianness
{
    /**
     * Least significant byte first, i.e. the order of the Intel and ARM
     * families, which is what nearly every modern platform uses.
     */
    case Little;

    /**
     * Most significant byte first, i.e. the order values travel the network
     * in, still used by the mainframe and some embedded families.
     */
    case Big;

    /**
     * Order the machine running this code uses.
     *
     * You didn't know you could do this either? Me neither... =)
     * This is magic shit, available since PHP 8.3
     */
    public const self HOST = IS_LITTLE_ENDIAN
        ? self::Little
        : self::Big;

    public static function fromBool(bool $isLittleEndian): self
    {
        if ($isLittleEndian) {
            return self::Little;
        }

        return self::Big;
    }
}
