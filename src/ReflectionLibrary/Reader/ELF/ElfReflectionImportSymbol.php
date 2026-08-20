<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF;

use FFI\Reflection\ReflectionLibrary\ReflectionImportSymbol;

/**
 * An undefined entry of the ELF dynamic symbol table (`.dynsym`).
 */
final readonly class ElfReflectionImportSymbol extends ReflectionImportSymbol
{
    /**
     * @param non-empty-string $nativeName
     * @param non-empty-string|null $name
     * @param non-empty-string|null $version
     */
    public function __construct(
        string $nativeName,
        ?string $name,
        bool $isOptional,
        ?string $version,
        /**
         * Row of the symbol in the dynamic symbol table.
         *
         * This is not a counter over the imports: the table interleaves
         * defined and undefined entries, and the index is what the
         * `.gnu.version` table and the relocations reference the symbol by.
         *
         * @var int<0, max>
         */
        public int $index,
    ) {
        parent::__construct(
            nativeName: $nativeName,
            name: $name,
            isOptional: $isOptional,
            version: $version,
        );
    }

    /**
     * Gets the row of the symbol in the dynamic symbol table.
     *
     * @return int<0, max>
     */
    public function getIndex(): int
    {
        return $this->index;
    }
}
