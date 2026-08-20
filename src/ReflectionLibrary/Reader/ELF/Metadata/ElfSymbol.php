<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata;

/**
 * Raw contents of a single `Elf32_Sym` or `Elf64_Sym` entry of
 * the dynamic symbol table, with the name and the version requirement
 * already resolved.
 */
final readonly class ElfSymbol
{
    public function __construct(
        /**
         * Resolved value of the `st_name` field.
         */
        public string $name,
        /**
         * Value of the `st_value` field, i.e. the virtual address of
         * the symbol, or zero for an undefined one.
         */
        public int $address,
        /**
         * Value of the `st_size` field, i.e. the size of the object
         * the symbol denotes, or zero when unknown.
         */
        public int $size,
        /**
         * Low nibble of the `st_info` field, one of the `STT_*`
         * constants.
         *
         * @var int<0, 15>
         */
        public int $type,
        /**
         * High nibble of the `st_info` field, one of the `STB_*`
         * constants.
         *
         * @var int<0, 15>
         */
        public int $binding,
        /**
         * Low two bits of the `st_other` field, one of the
         * `STV_*` constants.
         *
         * @var int<0, 3>
         */
        public int $visibility,
        /**
         * Value of the `st_shndx` field, i.e. the index of the section
         * defining the symbol. A value of `SHN_UNDEF` marks the symbol
         * as imported.
         *
         * @var int<0, 65535>
         */
        public int $section,
        /**
         * Index of the symbol in the dynamic symbol table.
         *
         * @var int<0, max>
         */
        public int $index,
        /**
         * Version requirement matching the `.gnu.version` entry of the
         * symbol, or {@see null} in case of the symbol carries none.
         */
        public ?ElfVersion $version,
    ) {}

    /**
     * Gets whether the symbol is referenced but not defined by the image.
     */
    public function isUndefined(): bool
    {
        return $this->section === 0;
    }
}
