<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader;

use FFI\Reflection\ReflectionLibrary\ArchitectureInterface;
use FFI\Reflection\ReflectionLibrary\Endianness;
use FFI\Reflection\ReflectionLibrary\ReflectionLibraryType;

/**
 * Everything the supported formats describe a file with in the same terms.
 *
 * These are the traits a loader needs before it can make sense of anything
 * else in an image, which is why every format keeps them in its header and
 * why a driver answers them in one go.
 */
final readonly class CommonLibraryInfo
{
    public function __construct(
        /**
         * Number of bytes an address of the library occupies, i.e. the
         * size a pointer takes in memory. The width in bits is this
         * value multiplied by eight.
         *
         * @var int<1, max>
         */
        public int $addressSize,
        /**
         * Order the library stores its multi-byte values in.
         */
        public Endianness $endianness,
        /**
         * Instruction set the library is compiled for, or {@see null} in
         * case of the format names one this library does not know.
         */
        public ?ArchitectureInterface $architecture,
        /**
         * Kind of object the file holds.
         */
        public ReflectionLibraryType $type,
    ) {}
}
