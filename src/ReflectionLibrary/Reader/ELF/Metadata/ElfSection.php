<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata;

/**
 * Raw contents of a single `Elf32_Shdr` or `Elf64_Shdr` entry
 * of the section header table.
 */
final readonly class ElfSection
{
    public function __construct(
        /**
         * Value of the `sh_name` field, i.e. the offset of the section
         * name in the section name string table.
         */
        public int $nameOffset,
        /**
         * Value of the `sh_type` field, one of the `SHT_*`
         * constants.
         */
        public int $type,
        /**
         * Value of the `sh_flags` field.
         */
        public int $flags,
        /**
         * Value of the `sh_addr` field, i.e. the virtual address the
         * section is loaded at.
         */
        public int $address,
        /**
         * Value of the `sh_offset` field, i.e. the position of the
         * section contents in the file.
         */
        public int $offset,
        /**
         * Value of the `sh_size` field.
         */
        public int $size,
        /**
         * Value of the `sh_link` field, whose meaning depends on the
         * {@see $type}. For a symbol table it is the index of the section
         * holding the symbol names.
         */
        public int $link,
        /**
         * Value of the `sh_info` field, whose meaning depends on the
         * {@see $type}. For a version requirement table it is the number of
         * entries.
         */
        public int $info,
        /**
         * Value of the `sh_entsize` field, i.e. the size of a single
         * entry of a section holding a table.
         */
        public int $entrySize,
    ) {}
}
