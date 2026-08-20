<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF;

/**
 * What a symbol denotes, i.e. the low nibble of the `Elf_Sym.st_info`
 * field (the `STT_*` constants of the ELF specification).
 *
 * ELF is the only supported format recording the distinction, which is why
 * the type lives next to its driver rather than in the public API.
 */
enum SymbolType
{
    /**
     * A symbol whose kind the image does not state, `STT_NOTYPE`.
     */
    case NoType;

    /**
     * A piece of data, like a variable or a constant, `STT_OBJECT`.
     */
    case Object;

    /**
     * A function or another executable piece of code, `STT_FUNC`.
     */
    case Func;

    /**
     * A section of the image itself, present for the sake of the
     * relocations referring to it, `STT_SECTION`.
     */
    case Section;

    /**
     * Name of the source file the following symbols came from,
     * `STT_FILE`.
     */
    case File;

    /**
     * An uninitialized piece of data the linker is free to merge with a
     * definition of the same name, `STT_COMMON`.
     */
    case Common;

    /**
     * A thread local variable, i.e. one whose address differs per thread,
     * `STT_TLS`.
     */
    case Tls;

    /**
     * A function whose real implementation is picked at load time by
     * calling the symbol itself, `STT_GNU_IFUNC`.
     */
    case GnuIFunc;
}
