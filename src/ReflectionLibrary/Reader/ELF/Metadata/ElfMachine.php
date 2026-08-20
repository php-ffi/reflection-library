<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata;

/**
 * Values of the `e_machine` field of an ELF header, i.e. the `EM_*`
 * constants of the ELF specification.
 *
 * The specification registers a couple of hundred of them, so only the
 * families a shared library is realistically built for are listed.
 */
final class ElfMachine
{
    /**
     * SPARC.
     */
    public const int EM_SPARC = 2;

    /**
     * Intel 80386.
     */
    public const int EM_386 = 3;

    /**
     * MIPS R3000, in either byte order.
     */
    public const int EM_MIPS = 8;

    /**
     * PowerPC.
     */
    public const int EM_PPC = 20;

    /**
     * PowerPC, the 64-bit revision.
     */
    public const int EM_PPC64 = 21;

    /**
     * IBM S/390, i.e. the z/Architecture.
     */
    public const int EM_S390 = 22;

    /**
     * ARM, the 32-bit revisions.
     */
    public const int EM_ARM = 40;

    /**
     * SPARC, the 64-bit revision.
     */
    public const int EM_SPARCV9 = 43;

    /**
     * Intel Itanium.
     */
    public const int EM_IA_64 = 50;

    /**
     * AMD x86-64.
     */
    public const int EM_X86_64 = 62;

    /**
     * ARM, the 64-bit revision.
     */
    public const int EM_AARCH64 = 183;

    /**
     * RISC-V, in both the 32-bit and the 64-bit revision.
     */
    public const int EM_RISCV = 243;

    /**
     * Loongson LoongArch, in both revisions.
     */
    public const int EM_LOONGARCH = 258;

    private function __construct() {}
}
