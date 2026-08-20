<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata;

/**
 * Raw contents of the `IMAGE_DOS_HEADER` structure, the MS-DOS stub
 * every PE image still begins with.
 */
final readonly class DosHeader
{
    public function __construct(
        /**
         * Value of the `e_magic` field, normally the `MZ`
         * signature.
         */
        public string $magic,
        /**
         * Value of the `e_lfanew` field, i.e. the file offset of the
         * `IMAGE_NT_HEADERS` structure.
         *
         * @var int<0, max>
         */
        public int $headersOffset,
    ) {}
}
