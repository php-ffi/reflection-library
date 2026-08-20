<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF;

use FFI\Reflection\ReflectionLibrary\ReflectionExportSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolAbi;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolResolution;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolVisibility;

/**
 * A defined entry of the ELF dynamic symbol table (`.dynsym`) that other
 * objects are allowed to bind to.
 */
final readonly class ElfReflectionExportSymbol extends ReflectionExportSymbol
{
    /**
     * @param non-empty-string $nativeName
     * @param non-empty-string|null $name
     */
    public function __construct(
        string $nativeName,
        ?string $name,
        int $address,
        ?ReflectionSymbolResolution $binding,
        ReflectionSymbolVisibility $visibility,
        /**
         * Size of the object the symbol denotes, or zero when unknown.
         */
        public int $size,
        /**
         * What the symbol denotes, i.e. a function, an object or a thread
         * local.
         *
         * Equals {@see null} in case of an OS- or processor-specific value
         * outside of the standard range.
         */
        public ?SymbolType $type,
        /**
         * Row of the symbol in the dynamic symbol table.
         *
         * @var int<0, max>
         */
        public int $index,
    ) {
        parent::__construct(
            nativeName: $nativeName,
            name: $name,
            address: $address,
            // ELF has no forwarding, every definition holds a body.
            forwarder: null,
            binding: $binding,
            visibility: $visibility,

            // The format records no calling convention, because the
            // platform offers a single one.
            abi: ReflectionSymbolAbi::Default,
        );
    }

    /**
     * Gets the size of the object the symbol denotes.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Gets what the symbol denotes.
     */
    public function getType(): ?SymbolType
    {
        return $this->type;
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
