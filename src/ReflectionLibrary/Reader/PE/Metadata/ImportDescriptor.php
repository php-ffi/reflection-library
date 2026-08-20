<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata;

/**
 * A single `IMAGE_IMPORT_DESCRIPTOR` or
 * `IMAGE_DELAYLOAD_DESCRIPTOR` entry, with its thunks resolved.
 */
final readonly class ImportDescriptor
{
    public function __construct(
        /**
         * Resolved value of the `Name` field, i.e. the library the
         * symbols are taken from.
         *
         * @var non-empty-string
         */
        public string $library,
        /**
         * Entries of the import name table of this descriptor.
         *
         * @var list<ImportThunk>
         */
        public array $thunks,
        /**
         * Whether the descriptor belongs to the delay-load import directory.
         */
        public bool $isDelayLoaded,
    ) {}
}
