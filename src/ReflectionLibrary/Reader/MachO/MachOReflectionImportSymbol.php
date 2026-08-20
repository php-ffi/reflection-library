<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO;

use FFI\Reflection\ReflectionLibrary\ReflectionImportSymbol;

/**
 * An undefined external entry of the Mach-O symbol table.
 */
final readonly class MachOReflectionImportSymbol extends ReflectionImportSymbol
{
    /**
     * Library ordinal meaning "the image itself".
     */
    public const int SELF_LIBRARY_ORDINAL = 0;

    /**
     * Library ordinal meaning "resolved by a flat namespace lookup".
     */
    public const int DYNAMIC_LOOKUP_ORDINAL = 0xFE;

    /**
     * Library ordinal meaning "the executable loading this image".
     */
    public const int EXECUTABLE_ORDINAL = 0xFF;

    /**
     * @param non-empty-string $nativeName raw symbol name, including the
     *        leading underscore of the C name mangling
     * @param non-empty-string|null $name
     */
    public function __construct(
        string $nativeName,
        ?string $name,
        bool $isOptional,
        /**
         * Ordinal of the library providing the symbol, i.e. the one-based
         * index of the dylib load command declaring it.
         *
         * @var int<0, 255>
         */
        public int $libraryOrdinal,
    ) {
        parent::__construct(
            nativeName: $nativeName,
            name: $name,
            isOptional: $isOptional,
            // Mach-O does not version its symbols: the current and
            // compatibility versions it records belong to the library.
            version: null,
        );
    }

    /**
     * Gets the ordinal of the library providing the symbol.
     *
     * @return int<0, 255>
     */
    public function getLibraryOrdinal(): int
    {
        return $this->libraryOrdinal;
    }

    /**
     * Gets whether the symbol is resolved using a flat namespace lookup
     * instead of being bound to a specific library.
     */
    public function isDynamicLookup(): bool
    {
        return $this->libraryOrdinal === self::DYNAMIC_LOOKUP_ORDINAL;
    }
}
