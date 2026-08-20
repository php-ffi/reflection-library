<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata;

/**
 * Raw contents of the `IMAGE_FILE_HEADER` structure, also known as
 * the COFF header.
 */
final readonly class FileHeader
{
    /**
     * Value of the {@see $characteristics} field marking the image as a
     * dynamic library (`IMAGE_FILE_DLL`).
     */
    public const int CHARACTERISTIC_DLL = 0x2000;

    public function __construct(
        /**
         * Value of the `Machine` field, i.e. the target architecture.
         *
         * @var int<0, 65535>
         */
        public int $machine,
        /**
         * Value of the `NumberOfSections` field.
         *
         * @var int<0, 65535>
         */
        public int $sectionCount,
        /**
         * Value of the `TimeDateStamp` field, i.e. the moment the
         * image was produced.
         */
        public \DateTimeImmutable $createdAt,
        /**
         * Value of the `SizeOfOptionalHeader` field.
         *
         * @var int<0, 65535>
         */
        public int $optionalHeaderSize,
        /**
         * Value of the `Characteristics` field.
         *
         * @var int<0, 65535>
         */
        public int $characteristics,
    ) {}

    /**
     * Gets whether the image is a dynamic library rather than an executable.
     */
    public function isLibrary(): bool
    {
        return ($this->characteristics & self::CHARACTERISTIC_DLL) !== 0;
    }
}
