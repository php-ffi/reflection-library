<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata;

/**
 * Values of the `filetype` field of a Mach header, i.e. the `MH_*`
 * constants of Darwin.
 */
final class MachOFileType
{
    /**
     * A relocatable file, i.e. the output of a compiler.
     */
    public const int MH_OBJECT = 1;

    /**
     * A program.
     */
    public const int MH_EXECUTE = 2;

    /**
     * A core dump.
     */
    public const int MH_CORE = 4;

    /**
     * A dynamic library.
     */
    public const int MH_DYLIB = 6;

    /**
     * A bundle, i.e. a module loaded on demand instead of being linked
     * against.
     */
    public const int MH_BUNDLE = 8;

    private function __construct() {}
}
