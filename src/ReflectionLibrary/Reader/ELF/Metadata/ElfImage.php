<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata;

/**
 * Everything read out of an ELF image in a single pass.
 *
 * The object is a plain carrier of the raw structures of the format: turning
 * them into reflection objects is the job of the driver.
 */
final readonly class ElfImage
{
    public function __construct(
        public ElfHeader $header,
        /**
         * Whether the program header table holds a `PT_INTERP` entry,
         * i.e. whether the image asks for an interpreter to start it.
         *
         * This is what tells a position independent executable apart from
         * a library, both of which are `ET_DYN`.
         */
        public bool $hasInterpreter,
        /**
         * Entries of the section header table, in file order.
         *
         * @var list<ElfSection>
         */
        public array $sections,
        /**
         * Resolved `DT_NEEDED` entries of the dynamic section, in the
         * order the loader processes them.
         *
         * @var list<non-empty-string>
         */
        public array $needed,
        /**
         * Entries of the dynamic symbol table, in table order.
         *
         * @var list<ElfSymbol>
         */
        public array $symbols,
        /**
         * Every version requirement declared by `.gnu.version_r`,
         * keyed by the index the symbols reference it by.
         *
         * @var array<int, ElfVersion>
         */
        public array $versions,
    ) {}

    /**
     * Gets the versions of the given library required by the image.
     *
     * @return list<non-empty-string>
     */
    public function getVersionsOf(string $library): array
    {
        $result = [];

        foreach ($this->versions as $version) {
            if ($version->library === $library) {
                $result[$version->name] = true;
            }
        }

        return \array_keys($result);
    }
}
