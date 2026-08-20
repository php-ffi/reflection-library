<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata;

/**
 * Values packed into the `st_info` and `st_other` fields of a symbol table
 * entry, i.e. the `STB_*`, `STT_*` and `STV_*` constants of the ELF
 * specification.
 *
 * The binding occupies the high nibble of the `st_info` field and what the
 * symbol denotes occupies the low one, while the visibility occupies the low
 * two bits of the `st_other` field.
 */
final class ElfSymbolInfo
{
    /**
     * A symbol local to the object defining it, `STB_LOCAL`.
     */
    public const int STB_LOCAL = 0;

    /**
     * An ordinary symbol every object may bind to, `STB_GLOBAL`.
     */
    public const int STB_GLOBAL = 1;

    /**
     * A symbol an ordinary definition of the same name takes precedence
     * over, `STB_WEAK`.
     */
    public const int STB_WEAK = 2;

    /**
     * A symbol the loader keeps unique across the whole process,
     * `STB_GNU_UNIQUE`.
     */
    public const int STB_GNU_UNIQUE = 10;

    /**
     * A symbol whose kind the image does not state, `STT_NOTYPE`.
     */
    public const int STT_NOTYPE = 0;

    /**
     * A piece of data, `STT_OBJECT`.
     */
    public const int STT_OBJECT = 1;

    /**
     * A function or another executable piece of code, `STT_FUNC`.
     */
    public const int STT_FUNC = 2;

    /**
     * A section of the image itself, `STT_SECTION`.
     */
    public const int STT_SECTION = 3;

    /**
     * Name of the source file the following symbols came from, `STT_FILE`.
     */
    public const int STT_FILE = 4;

    /**
     * An uninitialized piece of data, `STT_COMMON`.
     */
    public const int STT_COMMON = 5;

    /**
     * A thread local variable, `STT_TLS`.
     */
    public const int STT_TLS = 6;

    /**
     * A function whose implementation is picked at load time,
     * `STT_GNU_IFUNC`.
     */
    public const int STT_GNU_IFUNC = 10;

    /**
     * A symbol offered to every other object, `STV_DEFAULT`.
     */
    public const int STV_DEFAULT = 0;

    /**
     * A symbol unreachable even through a pointer taken elsewhere,
     * `STV_INTERNAL`.
     */
    public const int STV_INTERNAL = 1;

    /**
     * A symbol visible only inside the object defining it, `STV_HIDDEN`.
     */
    public const int STV_HIDDEN = 2;

    /**
     * A symbol offered to every other object but never overridden by one,
     * `STV_PROTECTED`.
     */
    public const int STV_PROTECTED = 3;

    /**
     * Mask selecting the visibility of the `st_other` field.
     */
    public const int MASK_VISIBILITY = 0x03;

    /**
     * Value of the `st_shndx` field of an undefined symbol.
     */
    public const int SHN_UNDEF = 0;

    private function __construct() {}
}
