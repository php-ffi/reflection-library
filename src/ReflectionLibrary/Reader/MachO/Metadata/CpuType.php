<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata;

/**
 * Values of the `cputype` field of a Mach header, i.e. the `CPU_TYPE_*`
 * constants of Darwin.
 *
 * A 64-bit family is the 32-bit one with the `CPU_ARCH_ABI64` bit set, which
 * is why the pairs below differ by a single bit.
 */
final class CpuType
{
    /**
     * Motorola 68000.
     */
    public const int CPU_TYPE_MC680X0 = 6;

    /**
     * Intel 80386 and above.
     */
    public const int CPU_TYPE_X86 = 7;

    /**
     * AMD x86-64.
     */
    public const int CPU_TYPE_X86_64 = 0x0100_0007;

    /**
     * ARM, the 32-bit revisions.
     */
    public const int CPU_TYPE_ARM = 12;

    /**
     * ARM, the 64-bit revision.
     */
    public const int CPU_TYPE_ARM64 = 0x0100_000C;

    /**
     * PowerPC.
     */
    public const int CPU_TYPE_POWERPC = 18;

    /**
     * PowerPC, the 64-bit revision.
     */
    public const int CPU_TYPE_POWERPC64 = 0x0100_0012;

    private function __construct() {}
}
