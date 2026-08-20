<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata;

/**
 * A single entry of an import name table, i.e. one symbol taken from the
 * library of the owning descriptor.
 */
final readonly class ImportThunk
{
    public function __construct(
        /**
         * Resolved name of the `IMAGE_IMPORT_BY_NAME` structure the
         * thunk points at.
         *
         * Equals {@see null} in case of the thunk carries an ordinal
         * instead, which is how a library exporting with the `NONAME`
         * attribute is consumed.
         *
         * @var non-empty-string|null
         */
        public ?string $name,
        /**
         * Index of the symbol in the export table of the providing library.
         *
         * Equals {@see null} in case of the thunk carries a name.
         *
         * @var int<0, 65535>|null
         */
        public ?int $ordinal,
        /**
         * Value of the `Hint` field of the
         * `IMAGE_IMPORT_BY_NAME` structure.
         *
         * @var int<0, 65535>|null
         */
        public ?int $hint,
    ) {}
}
