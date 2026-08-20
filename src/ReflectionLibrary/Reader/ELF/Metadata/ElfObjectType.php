<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata;

/**
 * Values of the `e_type` field of an ELF header, i.e. the `ET_*` constants
 * of the ELF specification.
 */
final class ElfObjectType
{
    /**
     * A relocatable file, i.e. the output of a compiler.
     */
    public const int ET_REL = 1;

    /**
     * An executable loaded at the address it was linked for.
     */
    public const int ET_EXEC = 2;

    /**
     * A shared object, which covers both a library and a position
     * independent executable.
     */
    public const int ET_DYN = 3;

    /**
     * A core dump.
     */
    public const int ET_CORE = 4;

    private function __construct() {}
}
