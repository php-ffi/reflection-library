<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata;

/**
 * A single `Elf_Vernaux` entry of the `.gnu.version_r` section,
 * i.e. one version of one library required by the image.
 */
final readonly class ElfVersion
{
    public function __construct(
        /**
         * Resolved value of the `vna_name` field, e.g.
         * `GLIBC_2.2.5`.
         *
         * @var non-empty-string
         */
        public string $name,
        /**
         * Resolved value of the `vn_file` field of the owning
         * `Elf_Verneed` entry, i.e. the library declaring the version.
         *
         * @var non-empty-string
         */
        public string $library,
        /**
         * Value of the `vna_other` field, i.e. the index the
         * `.gnu.version` entries reference this version by.
         *
         * @var int<0, 65535>
         */
        public int $index,
    ) {}
}
