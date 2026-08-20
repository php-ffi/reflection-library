<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata;

/**
 * Raw contents of the `IMAGE_OPTIONAL_HEADER32` or
 * `IMAGE_OPTIONAL_HEADER64` structure.
 *
 * The name is a leftover of COFF: the header is mandatory for an image.
 */
final readonly class OptionalHeader
{
    /**
     * Value of the `Magic` field of a PE32+ image.
     */
    public const int MAGIC_PE32PLUS = 0x020B;

    public function __construct(
        /**
         * Whether the image uses the PE32+ layout, i.e. 64 bit addresses.
         */
        public bool $is64bit,
        /**
         * Value of the `ImageBase` field, i.e. the preferred virtual
         * address the image is loaded at.
         */
        public int $base,
        /**
         * Value of the `AddressOfEntryPoint` field, relative to the
         * {@see $base}.
         */
        public int $entryPoint,
        /**
         * Value of the `Subsystem` field.
         *
         * @var int<0, 65535>
         */
        public int $subsystem,
        /**
         * Value of the `DllCharacteristics` field, holding the
         * mitigations the image opts into.
         *
         * @var int<0, 65535>
         */
        public int $characteristics,
        /**
         * Value of the `MajorOperatingSystemVersion` and
         * `MinorOperatingSystemVersion` fields, joined.
         *
         * @var non-empty-string
         */
        public string $operatingSystemVersion,
        /**
         * Value of the `MajorLinkerVersion` and
         * `MinorLinkerVersion` fields, joined.
         *
         * @var non-empty-string
         */
        public string $linkerVersion,
    ) {}
}
