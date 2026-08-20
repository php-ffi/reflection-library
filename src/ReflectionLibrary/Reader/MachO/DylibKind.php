<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO;

/**
 * Kind of the load command declaring a dependency, i.e. the `LC_*_DYLIB`
 * constants of Mach-O.
 *
 * Darwin distinguishes several ways of depending on a library, and the kind
 * is what tells whether the loader has to have it, may skip it or is going
 * to pass its symbols on as if they were the ones of this image.
 */
enum DylibKind
{
    /**
     * A regular dependency, `LC_LOAD_DYLIB`.
     */
    case Load;

    /**
     * A dependency the loader may skip, `LC_LOAD_WEAK_DYLIB`. Symbols taken
     * from a missing library are bound to zero instead of aborting.
     */
    case LoadWeak;

    /**
     * A dependency whose symbols are re-exported by the image itself,
     * `LC_REEXPORT_DYLIB`.
     */
    case Reexport;

    /**
     * A dependency loaded on first use, `LC_LAZY_LOAD_DYLIB`.
     */
    case LazyLoad;

    /**
     * A dependency forming a cycle with the image, `LC_LOAD_UPWARD_DYLIB`.
     */
    case LoadUpward;

    /**
     * Kind of a dependency the loader has to have.
     */
    public const self DEFAULT = self::Load;

    /**
     * Gets whether the loader is allowed to continue without the library.
     */
    public function isOptional(): bool
    {
        return $this === self::LoadWeak;
    }
}
