<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF;

use FFI\Reflection\ReflectionLibrary\ReflectionImport;

/**
 * A library listed in the `DT_NEEDED` entries of the ELF dynamic
 * section.
 *
 * Note that ELF resolves symbols in a flat namespace: the image records
 * which libraries it needs, but not which symbol comes from which of them.
 * The attribution is only recoverable for symbols carrying a version
 * requirement, so {@see getSymbols()} is normally a subset of what the
 * loader will actually take from this library.
 */
final class ElfReflectionImport extends ReflectionImport
{
    /**
     * @param non-empty-string $name
     * @param iterable<mixed, ElfReflectionImportSymbol> $symbols
     */
    public function __construct(
        string $name,
        iterable $symbols,
        /**
         * Versions of this library required by the image, as declared by
         * the `.gnu.version_r` section.
         *
         * @var list<non-empty-string>
         */
        public readonly array $versions,
    ) {
        parent::__construct(
            name: $name,
            // ELF has no per-entry flag making a DT_NEEDED library optional.
            isOptional: false,
            symbols: $symbols,
        );
    }

    /**
     * Gets the versions of this library required by the image.
     *
     * @return list<non-empty-string>
     */
    public function getVersions(): array
    {
        return $this->versions;
    }
}
