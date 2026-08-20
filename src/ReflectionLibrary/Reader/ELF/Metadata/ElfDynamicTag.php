<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata;

/**
 * Values of the `d_tag` field of a dynamic section entry, i.e. the `DT_*`
 * constants of the ELF specification.
 */
final class ElfDynamicTag
{
    /**
     * Terminates the dynamic section.
     */
    public const int DT_NULL = 0;

    /**
     * The entry names a library the image requires.
     */
    public const int DT_NEEDED = 1;

    private function __construct() {}
}
