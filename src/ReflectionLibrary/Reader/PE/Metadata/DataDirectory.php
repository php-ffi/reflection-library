<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata;

/**
 * A single `IMAGE_DATA_DIRECTORY` entry, i.e. the location of one of
 * the well known tables of the image.
 */
final readonly class DataDirectory
{
    /**
     * Index of the export table.
     */
    public const int INDEX_EXPORT = 0;

    /**
     * Index of the import table.
     */
    public const int INDEX_IMPORT = 1;

    /**
     * Index of the resource table.
     */
    public const int INDEX_RESOURCE = 2;

    /**
     * Index of the debug directory.
     */
    public const int INDEX_DEBUG = 6;

    /**
     * Index of the delay-load import table.
     */
    public const int INDEX_DELAY_IMPORT = 13;

    public function __construct(
        /**
         * Value of the `VirtualAddress` field, relative to the image
         * base, or zero in case of the table is absent.
         */
        public int $address,
        /**
         * Value of the `Size` field.
         */
        public int $size,
    ) {}

    /**
     * Gets whether the image contains this table.
     */
    public function isPresent(): bool
    {
        return $this->address !== 0;
    }

    /**
     * Gets whether the given relative address falls inside this table.
     */
    public function contains(int $address): bool
    {
        return $address >= $this->address
            && $address < $this->address + $this->size;
    }
}
