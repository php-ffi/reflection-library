<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata;

use FFI\Reflection\ReflectionLibrary\Reader\MachO\DylibKind;

/**
 * Raw contents of a single `dylib_command` load command, i.e. one library
 * the image depends on.
 */
final readonly class Dylib
{
    public function __construct(
        /**
         * Resolved value of the `dylib.name` field, i.e. the path the loader
         * will look the library up by.
         *
         * @var non-empty-string
         */
        public string $name,
        /**
         * Kind of the load command declaring the dependency.
         */
        public DylibKind $kind,
        /**
         * Value of the `dylib.current_version` field, formatted as
         * `major.minor.patch`.
         *
         * @var non-empty-string
         */
        public string $currentVersion,
        /**
         * Value of the `dylib.compatibility_version` field, formatted as
         * `major.minor.patch`.
         *
         * @var non-empty-string
         */
        public string $compatibilityVersion,
        /**
         * One-based index of the load command among the dylib ones, i.e. the
         * ordinal the symbols reference this library by.
         *
         * @var int<1, max>
         */
        public int $ordinal,
    ) {}
}
