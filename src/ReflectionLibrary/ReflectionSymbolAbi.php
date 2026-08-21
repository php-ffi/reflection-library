<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary;

/**
 * Calling convention a function expects, i.e. how the arguments reach it and
 * who cleans up afterwards.
 *
 * Calling a function the wrong way corrupts the stack, so a caller has to
 * spell the convention out whenever it differs from the default one of the
 * platform. The cases mirror the `FFI\CType::ABI_*` constants, which is what
 * an {@see \FFI::cdef()} declaration ends up carrying.
 *
 * Most platforms offer a single convention and record nothing, in which case
 * {@see self::Default} is what a symbol reports.
 */
enum ReflectionSymbolAbi
{
    /**
     * Whatever the platform uses when nothing else is said.
     *
     * Matches to {@see \FFI\CType::ABI_DEFAULT}
     */
    case Default;

    /**
     * The caller pushes the arguments and cleans them up, which makes a
     * variadic function possible.
     *
     * Matches to {@see \FFI\CType::ABI_CDECL}
     */
    case CDecl;

    /**
     * The first arguments travel in registers and the callee cleans the
     * rest up.
     *
     * Matches to {@see \FFI\CType::ABI_FASTCALL}
     */
    case FastCall;

    /**
     * Like {@see self::FastCall}, reserving the first register for the
     * object a method belongs to.
     *
     * Matches to {@see \FFI\CType::ABI_THISCALL}
     */
    case ThisCall;

    /**
     * The callee cleans the arguments up, which rules a variadic function
     * out but shrinks every call site.
     *
     * Matches to {@see \FFI\CType::ABI_STDCALL}
     */
    case StdCall;

    /**
     * Arguments are pushed left to right and the callee cleans them up, a
     * convention inherited from the Pascal runtimes.
     *
     * Matches to {@see \FFI\CType::ABI_PASCAL}
     */
    case Pascal;

    /**
     * Arguments travel in registers as far as they fit, a convention of the
     * Borland compilers.
     *
     * Matches to {@see \FFI\CType::ABI_REGISTER}
     */
    case Register;

    /**
     * The 64-bit convention of Windows, four register arguments and a shadow
     * space reserved by the caller.
     *
     * Matches to {@see \FFI\CType::ABI_MS}
     */
    case MS;

    /**
     * The 64-bit convention of the System V supplement, which the rest of
     * the world follows.
     *
     * Matches to {@see \FFI\CType::ABI_SYSV}
     */
    case SysV;

    /**
     * Like {@see self::FastCall}, passing vector arguments in the wide
     * registers.
     *
     * Matches to {@see \FFI\CType::ABI_VECTORCALL}
     */
    case VectorCall;
}
