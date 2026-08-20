<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata;

/**
 * Values of the `e_ident` array, which opens every ELF header and tells how
 * to read the rest of it.
 */
final class ElfIdentity
{
    /**
     * Contents of the `e_ident[EI_MAG0..EI_MAG3]` field.
     */
    public const string MAGIC = "\x7fELF";

    /**
     * Size of the whole `e_ident` array.
     */
    public const int SIZE = 16;

    /**
     * Offset of the `e_ident[EI_CLASS]` field.
     */
    public const int OFFSET_CLASS = 4;

    /**
     * Offset of the `e_ident[EI_DATA]` field.
     */
    public const int OFFSET_DATA = 5;

    /**
     * Offset of the `e_ident[EI_OSABI]` field.
     */
    public const int OFFSET_OS_ABI = 7;

    /**
     * Value of the `e_ident[EI_CLASS]` field of a 64-bit image.
     */
    public const int ELFCLASS64 = 2;

    /**
     * Value of the `e_ident[EI_DATA]` field of a little-endian image.
     */
    public const int ELFDATA2LSB = 1;

    private function __construct() {}
}
