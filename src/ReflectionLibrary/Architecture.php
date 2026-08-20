<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary;

/**
 * Instruction set the library is compiled for.
 *
 * Machine code cannot be interpreted by a processor of another family, so the
 * architecture is what decides whether a library is loadable on the running
 * machine at all. Note that a library reports the architecture it was built
 * for and not the one it happens to run on, which differ whenever an emulation
 * layer is involved.
 *
 * Only the families the supported formats can name are listed. A library built
 * for anything else is reported as an unknown architecture, i.e. {@see null},
 * rather than as an approximation.
 */
enum Architecture implements ArchitectureInterface
{
    /**
     * The 32-bit family of Intel, also known as i386 or IA-32.
     */
    case X86;

    /**
     * The 64-bit extension of {@see self::X86}, also known as x86-64, x64 or
     * AMD64.
     */
    case Amd64;

    /**
     * The 32-bit family of ARM, in any of its revisions.
     */
    case Arm;

    /**
     * The 64-bit family of ARM, also known as AArch64.
     */
    case Arm64;

    /**
     * The open instruction set of RISC-V, whose width is told apart by the
     * size of an address rather than by the family itself.
     */
    case RiscV;

    /**
     * The 32-bit family of PowerPC.
     */
    case PowerPc;

    /**
     * The 64-bit family of PowerPC, in either byte order.
     */
    case PowerPc64;

    /**
     * The family of MIPS, whose width is told apart by the size of an
     * address rather than by the family itself.
     */
    case Mips;

    /**
     * The family of SPARC, including its 64-bit revision.
     */
    case Sparc;

    /**
     * The mainframe family of IBM, also known as z/Architecture.
     */
    case S390x;

    /**
     * The family of Loongson, whose width is told apart by the size of an
     * address rather than by the family itself.
     */
    case LoongArch;

    /**
     * The discontinued 64-bit family of Intel, also known as Itanium.
     */
    case Ia64;
}
