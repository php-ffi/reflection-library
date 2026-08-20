<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE;

use FFI\Reflection\ReflectionLibrary\ReflectionExportSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolAbi;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolResolution;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolVisibility;

/**
 * An entry of the export directory of a PE image.
 *
 * Two kinds of entry end up without a spellable
 * {@see ReflectionExportSymbol::$name}. One is declared with the `NONAME`
 * attribute and sits in the export address table without a name at all, so
 * it is reported under a placeholder like `example.#42`. The other carries
 * the decoration of its calling convention, like the `@@24` a MSVC
 * `__vectorcall` function gets, which no C declaration can spell out.
 */
final readonly class PeReflectionExportSymbol extends ReflectionExportSymbol
{
    /**
     * @param non-empty-string|null $nativeName
     * @param non-empty-string|null $name
     * @param non-empty-string|null $forwarder
     */
    public function __construct(
        ?string $nativeName,
        ?string $name,
        ?int $address,
        ?string $forwarder,
        ReflectionSymbolAbi $abi,
        /**
         * Position of the symbol in the export address table, i.e. its index
         * shifted by the base of the directory.
         *
         * @var int<0, max>
         */
        public int $ordinal,
    ) {
        parent::__construct(
            nativeName: $nativeName,
            name: $name,
            address: $address,
            forwarder: $forwarder,
            // PE has no weak exports and no visibility of its own: every
            // entry of the directory is offered unconditionally.
            binding: ReflectionSymbolResolution::Global,
            visibility: ReflectionSymbolVisibility::Public,
            abi: $abi,
        );
    }

    /**
     * Gets the position of the symbol in the export address table.
     *
     * @return int<0, max>
     */
    public function getOrdinal(): int
    {
        return $this->ordinal;
    }

    public function __toString(): string
    {
        if ($this->getNativeName() !== null) {
            return parent::__toString();
        }

        return '#' . $this->ordinal;
    }
}
