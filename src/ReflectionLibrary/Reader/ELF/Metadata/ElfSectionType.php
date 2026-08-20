<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata;

/**
 * Values of the `sh_type` field of a section header, i.e. the `SHT_*`
 * constants of the ELF specification.
 */
final class ElfSectionType
{
    /**
     * The section holds the dynamic linking table.
     */
    public const int SHT_DYNAMIC = 6;

    /**
     * The section holds the dynamic symbol table.
     */
    public const int SHT_DYNSYM = 11;

    /**
     * The section holds one version index per dynamic symbol.
     */
    public const int SHT_GNU_VERSYM = 0x6FFF_FFFF;

    /**
     * The section holds the versions the image requires of its dependencies.
     */
    public const int SHT_GNU_VERNEED = 0x6FFF_FFFE;

    private function __construct() {}
}
