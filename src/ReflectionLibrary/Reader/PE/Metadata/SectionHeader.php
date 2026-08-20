<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata;

/**
 * Raw contents of a single `IMAGE_SECTION_HEADER` entry.
 */
final readonly class SectionHeader
{
    public function __construct(
        /**
         * Value of the `Name` field, trimmed of its padding.
         */
        public string $name,
        /**
         * Value of the `VirtualSize` field, i.e. the size the section
         * occupies once loaded.
         */
        public int $size,
        /**
         * Value of the `VirtualAddress` field, relative to the image
         * base.
         */
        public int $address,
        /**
         * Value of the `SizeOfRawData` field, i.e. the size the
         * section occupies in the file.
         */
        public int $rawSize,
        /**
         * Value of the `PointerToRawData` field, i.e. the position of
         * the section contents in the file.
         */
        public int $rawOffset,
        /**
         * Value of the `Characteristics` field.
         */
        public int $characteristics,
    ) {}

    /**
     * Converts a relative virtual address into a file offset, or gets
     * {@see null} in case of the address does not belong to this section.
     *
     * An address inside the section but past its raw data belongs to a
     * region without a file representation, like uninitialized data, and is
     * reported as absent too.
     */
    public function findOffsetOf(int $address): ?int
    {
        $size = \max($this->size, $this->rawSize);

        if ($address < $this->address || $address >= $this->address + $size) {
            return null;
        }

        $delta = $address - $this->address;

        return $delta >= $this->rawSize ? null : $this->rawOffset + $delta;
    }
}
