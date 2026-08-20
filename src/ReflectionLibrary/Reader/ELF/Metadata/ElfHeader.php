<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata;

/**
 * Raw contents of the `Elf32_Ehdr` or `Elf64_Ehdr` structure
 * found at the beginning of every ELF image.
 */
final readonly class ElfHeader
{
    public function __construct(
        /**
         * Whether the image uses the 64 bit layout, i.e. the
         * `e_ident[EI_CLASS]` field equals `ELFCLASS64`.
         */
        public bool $is64bit,
        /**
         * Whether multi-byte values are stored least significant byte first,
         * i.e. the `e_ident[EI_DATA]` field equals `ELFDATA2LSB`.
         */
        public bool $littleEndian,
        /**
         * Value of the `e_ident[EI_OSABI]` field.
         *
         * @var int<0, 255>
         */
        public int $osAbi,
        /**
         * Value of the `e_type` field.
         *
         * @var int<0, 65535>
         */
        public int $type,
        /**
         * Value of the `e_machine` field.
         *
         * @var int<0, 65535>
         */
        public int $machine,
        /**
         * Value of the `e_flags` field, whose meaning is specific to
         * the {@see $machine}.
         */
        public int $flags,
        /**
         * Value of the `e_phoff` field, i.e. the file offset of the
         * program header table.
         */
        public int $programOffset,
        /**
         * Value of the `e_phentsize` field, i.e. the size of a single
         * program header.
         *
         * @var int<0, 65535>
         */
        public int $programSize,
        /**
         * Value of the `e_phnum` field, i.e. the number of program
         * headers.
         *
         * @var int<0, 65535>
         */
        public int $programCount,
        /**
         * Value of the `e_shoff` field, i.e. the file offset of the
         * section header table.
         */
        public int $sectionOffset,
        /**
         * Value of the `e_shentsize` field, i.e. the size of a single
         * section header.
         *
         * @var int<0, 65535>
         */
        public int $sectionSize,
        /**
         * Value of the `e_shnum` field, i.e. the number of section
         * headers.
         *
         * @var int<0, 65535>
         */
        public int $sectionCount,
        /**
         * Value of the `e_shstrndx` field, i.e. the index of the
         * section holding the section names.
         *
         * @var int<0, 65535>
         */
        public int $sectionNamesIndex,
    ) {}
}
