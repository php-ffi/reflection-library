<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO;

use FFI\Reflection\ReflectionLibrary\ReflectionImport;

/**
 * A library declared by a dylib load command of a Mach-O image.
 *
 * In a two-level namespace image every undefined symbol carries the ordinal
 * of the command providing it, so {@see getSymbols()} is the complete list.
 */
final class MachOReflectionImport extends ReflectionImport
{
    /**
     * @param non-empty-string $name install name of the library, i.e. the
     *        path the loader will look it up by
     * @param iterable<mixed, MachOReflectionImportSymbol> $symbols
     */
    public function __construct(
        string $name,
        iterable $symbols,
        /**
         * Kind of the load command declaring the dependency.
         */
        public readonly DylibKind $kind,
        /**
         * Version of the library the image was linked against, formatted as
         * `major.minor.patch`.
         *
         * @var non-empty-string
         */
        public readonly string $currentVersion,
        /**
         * Oldest version of the library the image is compatible with,
         * formatted as `major.minor.patch`.
         *
         * @var non-empty-string
         */
        public readonly string $compatibilityVersion,
        /**
         * One-based index of the load command, i.e. the ordinal the symbols
         * of this library reference it by.
         *
         * @var int<1, max>
         */
        public readonly int $ordinal,
    ) {
        parent::__construct(
            name: $name,
            isOptional: $kind->isOptional(),
            symbols: $symbols,
        );
    }

    /**
     * Gets the kind of the load command declaring the dependency.
     */
    public function getKind(): DylibKind
    {
        return $this->kind;
    }

    /**
     * Gets the version of the library the image was linked against.
     *
     * @return non-empty-string
     */
    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    /**
     * Gets the oldest version of the library the image is compatible with.
     *
     * @return non-empty-string
     */
    public function getCompatibilityVersion(): string
    {
        return $this->compatibilityVersion;
    }

    /**
     * Gets the ordinal the symbols of this library reference it by.
     *
     * @return int<1, max>
     */
    public function getOrdinal(): int
    {
        return $this->ordinal;
    }
}
