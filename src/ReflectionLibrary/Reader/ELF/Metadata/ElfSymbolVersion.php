<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata;

/**
 * Values of the `.gnu.version` table, which holds one index per dynamic
 * symbol.
 */
final class ElfSymbolVersion
{
    /**
     * The symbol is local to the image and carries no version.
     */
    public const int VER_NDX_LOCAL = 0;

    /**
     * The symbol is global and carries no version.
     */
    public const int VER_NDX_GLOBAL = 1;

    /**
     * Mask removing the "hidden" bit, which marks a version that is not the
     * default one for its name.
     */
    public const int MASK_INDEX = 0x7FFF;

    private function __construct() {}
}
