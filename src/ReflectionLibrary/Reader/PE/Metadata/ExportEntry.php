<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata;

/**
 * A single symbol offered by the export directory of an image.
 */
final readonly class ExportEntry
{
    public function __construct(
        /**
         * Name of the symbol taken from the export name table.
         *
         * Equals {@see null} in case of the symbol is offered by ordinal
         * only, i.e. carries the `NONAME` attribute.
         *
         * @var non-empty-string|null
         */
        public ?string $name,
        /**
         * Ordinal of the symbol, i.e. its index in the export address table
         * shifted by the base of the directory.
         *
         * @var int<0, max>
         */
        public int $ordinal,
        /**
         * Address of the symbol relative to the image base.
         *
         * Equals {@see null} for a forwarded symbol, whose slot holds the
         * forwarder string instead of code.
         */
        public ?int $address,
        /**
         * Target of a forwarded symbol, formatted as `Library.Symbol`,
         * or {@see null} in case of the symbol is defined by this image.
         *
         * @var non-empty-string|null
         */
        public ?string $forwarder,
    ) {}
}
