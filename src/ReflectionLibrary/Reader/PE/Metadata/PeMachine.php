<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata;

/**
 * Values of the `Machine` field of a COFF header, i.e. the
 * `IMAGE_FILE_MACHINE_*` constants of the PE specification.
 */
final class PeMachine
{
    /**
     * Intel 80386 and above.
     */
    public const int I386 = 0x014C;

    /**
     * MIPS with the little-endian byte order.
     */
    public const int R4000 = 0x0166;

    /**
     * ARM, little-endian.
     */
    public const int ARM = 0x01C0;

    /**
     * ARM Thumb-2, which is what every modern 32-bit image of Windows
     * carries.
     */
    public const int ARMNT = 0x01C4;

    /**
     * PowerPC, little-endian.
     */
    public const int POWERPC = 0x01F0;

    /**
     * PowerPC with a floating point unit.
     */
    public const int POWERPCFP = 0x01F1;

    /**
     * Intel Itanium.
     */
    public const int IA64 = 0x0200;

    /**
     * RISC-V, the 32-bit revision.
     */
    public const int RISCV32 = 0x5032;

    /**
     * RISC-V, the 64-bit revision.
     */
    public const int RISCV64 = 0x5064;

    /**
     * LoongArch, the 32-bit revision.
     */
    public const int LOONGARCH32 = 0x6232;

    /**
     * LoongArch, the 64-bit revision.
     */
    public const int LOONGARCH64 = 0x6264;

    /**
     * AMD x86-64.
     */
    public const int AMD64 = 0x8664;

    /**
     * ARM, the 64-bit revision.
     */
    public const int ARM64 = 0xAA64;

    private function __construct() {}
}
