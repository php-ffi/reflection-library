<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata;

/**
 * Raw contents of the `mach_header` or `mach_header_64` structure found at
 * the beginning of every Mach-O image.
 */
final readonly class MachHeader
{
    /**
     * Value of the {@see $flags} field marking an image that resolves its
     * symbols through a two-level namespace (`MH_TWOLEVEL`).
     */
    public const int FLAG_TWO_LEVEL = 0x80;

    public function __construct(
        /**
         * Whether the image uses the 64 bit layout.
         */
        public bool $is64bit,
        /**
         * Whether multi-byte values are stored least significant byte first.
         */
        public bool $littleEndian,
        /**
         * Value of the `cputype` field.
         */
        public int $cpuType,
        /**
         * Value of the `cpusubtype` field.
         */
        public int $cpuSubType,
        /**
         * Value of the `filetype` field.
         */
        public int $fileType,
        /**
         * Value of the `ncmds` field, i.e. the number of load commands
         * following the header.
         */
        public int $commandCount,
        /**
         * Value of the `flags` field.
         */
        public int $flags,
    ) {}

    /**
     * Gets whether the image resolves its symbols through a two-level
     * namespace, which is what makes the library ordinals meaningful.
     */
    public function isTwoLevel(): bool
    {
        return ($this->flags & self::FLAG_TWO_LEVEL) !== 0;
    }

    /**
     * Gets whether the image is a dynamic library.
     */
    public function isLibrary(): bool
    {
        return $this->fileType === MachOFileType::MH_DYLIB;
    }
}
