<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata;

/**
 * Values of the `p_type` field of a program header, i.e. the `PT_*`
 * constants of the ELF specification.
 */
final class ElfProgramType
{
    /**
     * The segment names the interpreter that has to start the image.
     *
     * Only a program carries it, which is what tells a position independent
     * executable apart from a shared library: both are `ET_DYN`.
     */
    public const int PT_INTERP = 3;

    private function __construct() {}
}
