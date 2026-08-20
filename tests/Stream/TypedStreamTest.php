<?php

declare(strict_types=1);

namespace FFI\Reflection\Tests\Stream;

use FFI\Reflection\ReflectionLibrary\Endianness;
use FFI\Reflection\ReflectionLibrary\Stream\Stream;
use FFI\Reflection\ReflectionLibrary\Stream\Type;
use FFI\Reflection\ReflectionLibrary\Stream\TypedStream;
use FFI\Reflection\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TypedStream::class)]
#[CoversClass(Stream::class)]
#[CoversClass(Type::class)]
final class TypedStreamTest extends TestCase
{
    private static function create(string $data, Endianness $endianness = Endianness::Little): TypedStream
    {
        return new TypedStream(Stream::createFromString($data), $endianness);
    }

    public function testUnsignedValuesFollowTheByteOrderOfTheStream(): void
    {
        $stream = self::create("\x01\x02\x03\x04");

        self::assertSame(0x04030201, $stream->uint32());

        $stream = self::create("\x01\x02\x03\x04", Endianness::Big);

        self::assertSame(0x01020304, $stream->uint32());
    }

    public function testByteOrderCanBeOverriddenPerRead(): void
    {
        $stream = self::create("\x01\x02\x01\x02");

        self::assertSame(0x0201, $stream->uint16());
        self::assertSame(0x0102, $stream->uint16(Endianness::Big));
    }

    public function testByteOrderCopyKeepsThePosition(): void
    {
        $stream = self::create("\x01\x02\x03\x04");
        $stream->uint16();

        $swapped = $stream->withBigEndian();

        self::assertSame(2, $swapped->offset);
        self::assertSame(0x0304, $swapped->uint16());

        // Both objects delegate to the same underlying stream.
        self::assertSame(4, $stream->offset);
    }

    public function testByteOrderCopyOfTheSameOrderIsTheSameInstance(): void
    {
        $stream = self::create('', Endianness::Little);

        self::assertSame($stream, $stream->withLittleEndian());
        self::assertNotSame($stream, $stream->withBigEndian());
    }

    public function testReadingPastTheEndIsPaddedWithNullBytes(): void
    {
        $stream = self::create("\x41");

        self::assertSame("\x41\x00\x00\x00", $stream->read(4));
    }

    public function testNullTerminatedStringStopsAtTheTerminator(): void
    {
        $stream = self::create("libc.so.6\x00rest");

        self::assertSame('libc.so.6', $stream->string());
        self::assertSame('rest', $stream->string());
    }

    public function testFixedWidthStringIsTrimmedOfItsPadding(): void
    {
        $stream = self::create(".text\x00\x00\x00rest");

        self::assertSame('.text', $stream->string(8));
        self::assertSame('rest', $stream->string(4));
    }

    public function testLookaheadRestoresThePosition(): void
    {
        $stream = self::create("\x01\x02\x03\x04");

        $result = $stream->lookahead(static function (TypedStream $stream): int {
            $stream->offset += 2;

            return $stream->uint16();
        });

        self::assertSame(0x0403, $result);
        self::assertSame(0, $stream->offset);
    }

    public function testSliceIsIndependentOfTheOuterStream(): void
    {
        $stream = self::create("\x01\x02\x03\x04\x05\x06");
        $stream->offset = 2;

        $slice = $stream->slice(2);

        self::assertSame(4, $stream->offset);
        self::assertSame(0x0403, $slice->uint16());

        // Rewinding the slice must not disturb the outer stream.
        $slice->offset = 0;

        self::assertSame(4, $stream->offset);
    }

    public function testSliceKeepsTheByteOrder(): void
    {
        $stream = self::create("\x01\x02", Endianness::Big);

        self::assertSame(Endianness::Big, $stream->slice(2)->endianness);
    }

    public function testUleb128DecodesAMultiByteValue(): void
    {
        // The canonical example of the DWARF specification.
        $stream = self::create("\xE5\x8E\x26");

        self::assertSame(624485, $stream->uleb128());
    }

    public function testUleb128DecodesASingleByteValue(): void
    {
        $stream = self::create("\x7F\x02");

        self::assertSame(127, $stream->uleb128());
        self::assertSame(2, $stream->uleb128());
    }

    public function testAddressFollowsTheWidthOfTheImage(): void
    {
        $stream = self::create("\x01\x00\x00\x00\x00\x00\x00\x00");

        self::assertSame(1, $stream->address(is64bit: true));
        self::assertSame(8, $stream->offset);

        $stream->offset = 0;

        self::assertSame(1, $stream->address(is64bit: false));
        self::assertSame(4, $stream->offset);
    }

    public function testBitmaskReadsTheMostSignificantBitFirst(): void
    {
        $stream = self::create("\xA5");

        self::assertSame(
            [true, false, true, false, false, true, false, true],
            $stream->bitmask(1),
        );
    }

    public function testArrayReadsASequenceOfTheSameType(): void
    {
        $stream = self::create("\x01\x00\x02\x00\x03\x00");

        self::assertSame([1, 2, 3], $stream->array(3, Type::UInt16le));
    }

    public function testTypeSizeMatchesTheFormat(): void
    {
        self::assertSame(1, Type::UInt8->getSize());
        self::assertSame(2, Type::UInt16le->getSize());
        self::assertSame(4, Type::UInt32be->getSize());
        self::assertSame(8, Type::UInt64le->getSize());
    }

    public function testTypeByteOrderIsSwappable(): void
    {
        self::assertSame(Type::UInt32be, Type::UInt32le->withByteOrder(Endianness::Big));
        self::assertSame(Type::UInt32le, Type::UInt32be->withByteOrder(Endianness::Little));

        // A type without endianness-specific variants is returned as is.
        self::assertSame(Type::UInt8, Type::UInt8->withByteOrder(Endianness::Big));
    }
}
