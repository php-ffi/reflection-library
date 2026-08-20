<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata;

/**
 * Values of the `cmd` field of a load command, i.e. the `LC_*` constants of
 * the Mach-O specification.
 */
final class LoadCommandType
{
    /**
     * The command declares the symbol table.
     */
    public const int LC_SYMTAB = 0x02;

    /**
     * The command declares a dependency the loader binds at load time.
     */
    public const int LC_LOAD_DYLIB = 0x0C;

    /**
     * The command declares a dependency loaded on first use.
     */
    public const int LC_LAZY_LOAD_DYLIB = 0x20;

    /**
     * The command declares a dependency the loader may skip.
     */
    public const int LC_LOAD_WEAK_DYLIB = 0x8000_0018;

    /**
     * The command declares a dependency whose symbols the image re-exports.
     */
    public const int LC_REEXPORT_DYLIB = 0x8000_001F;

    /**
     * The command declares a dependency forming a cycle with the image.
     */
    public const int LC_LOAD_UPWARD_DYLIB = 0x8000_0023;

    /**
     * The command declares the tables dyld binds the image with, the export
     * trie among them.
     */
    public const int LC_DYLD_INFO = 0x22;

    /**
     * Like {@see self::LC_DYLD_INFO}, declaring that no other linker is
     * expected to be able to read the image.
     */
    public const int LC_DYLD_INFO_ONLY = 0x8000_0022;

    /**
     * The command declares the export trie on its own, which is what a
     * modern image uses instead of {@see self::LC_DYLD_INFO_ONLY}.
     */
    public const int LC_DYLD_EXPORTS_TRIE = 0x8000_0033;

    /**
     * Offset of the export trie inside an `LC_DYLD_INFO` command, i.e.
     * behind the rebase, bind, weak bind and lazy bind pairs.
     */
    public const int DYLD_INFO_EXPORT_OFFSET = 40;

    /**
     * Offset of the payload of a command carrying nothing but a pair of
     * fields, i.e. right behind `cmd` and `cmdsize`.
     */
    public const int PAYLOAD_OFFSET = 8;

    private function __construct() {}
}
