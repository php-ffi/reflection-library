<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE;

use FFI\Reflection\ReflectionLibrary\ReflectionImport;

/**
 * A library referenced by a descriptor of the PE import directory.
 *
 * PE uses a two-level namespace, so every imported symbol belongs to exactly
 * one descriptor and {@see getSymbols()} is the complete list.
 */
final class PeReflectionImport extends ReflectionImport
{
    /**
     * @param non-empty-string $name
     * @param iterable<mixed, PeReflectionImportSymbol> $symbols
     * @param bool $isDelayLoaded Whether the library belongs to the
     *        delay-load import directory, i.e. is loaded on first use of one
     *        of its symbols instead of at image load time.
     */
    public function __construct(
        string $name,
        iterable $symbols,
        bool $isDelayLoaded,
    ) {
        // Delay loading is the only thing that makes a PE library optional:
        // the image starts without it and is told about a failure to load it
        // instead of being aborted. The two are the same bit, so only the
        // format-agnostic one is stored.
        parent::__construct(
            name: $name,
            isOptional: $isDelayLoaded,
            symbols: $symbols,
        );
    }

    /**
     * Gets whether the library is loaded on first use instead of at image
     * load time.
     */
    public function isDelayLoaded(): bool
    {
        return $this->isOptional;
    }
}
