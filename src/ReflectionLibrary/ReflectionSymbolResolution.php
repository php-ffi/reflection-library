<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary;

/**
 * How a definition behaves when several libraries offer the same symbol.
 *
 * A process resolves every symbol once, so a name defined more than once has
 * to be arbitrated. The binding is what decides the outcome, which matters
 * whenever a caller wonders which of two libraries a call will actually
 * reach.
 */
enum ReflectionSymbolResolution
{
    /**
     * An ordinary definition. Two of them colliding is an error the loader
     * is free to report.
     */
    case Global;

    /**
     * A definition another library is allowed to override, i.e. one that
     * gives way to an ordinary definition of the same name.
     */
    case Weak;

    /**
     * A definition the loader keeps unique across the whole process, so that
     * every library ends up sharing the very same one.
     */
    case Unique;
}
