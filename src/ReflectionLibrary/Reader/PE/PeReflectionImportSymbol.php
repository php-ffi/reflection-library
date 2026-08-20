<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE;

use FFI\Reflection\ReflectionLibrary\ReflectionImportSymbol;

/**
 * An entry of the import name table of a PE descriptor.
 *
 * A thunk whose high bit is set asks the loader for a slot number instead of
 * a name, so such an entry carries no
 * {@see ReflectionImportSymbol::$nativeName} at all and {@see $ordinal} is
 * the only way of addressing it.
 */
final readonly class PeReflectionImportSymbol extends ReflectionImportSymbol
{
    /**
     * @param non-empty-string|null $nativeName
     * @param non-empty-string|null $name
     */
    public function __construct(
        ?string $nativeName,
        ?string $name,
        bool $isOptional,
        /**
         * Position of the symbol in the export table of the library
         * defining it.
         *
         * Equals {@see null} unless the symbol is anonymous, since a PE
         * image records no ordinal for the symbols it takes by name.
         *
         * @var int<0, max>|null
         */
        public ?int $ordinal,
    ) {
        parent::__construct(
            nativeName: $nativeName,
            name: $name,
            isOptional: $isOptional,
            // PE does not version its symbols.
            version: null,
        );
    }

    /**
     * Gets the position of the symbol in the export table of the library
     * defining it.
     *
     * @return int<0, max>|null
     */
    public function getOrdinal(): ?int
    {
        return $this->ordinal;
    }

    public function __toString(): string
    {
        if ($this->nativeName !== null) {
            return parent::__toString();
        }

        return '#' . $this->ordinal;
    }
}
