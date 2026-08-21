<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary;

/**
 * Kind of object the file holds.
 *
 * Every supported format describes more than shared libraries, and reflection
 * happily reads a file that is not one. The type is what tells a caller that
 * the file it opened is loadable in the way it expects.
 */
enum ReflectionLibraryType
{
    /**
     * A shared library, i.e. a file meant to be loaded into a process that
     * is already running and have its symbols resolved.
     */
    case Library;

    /**
     * A program, i.e. a file meant to be started rather than loaded.
     *
     * Note that a position independent executable is built the same way a
     * library is, and only the interpreter it asks for gives it away.
     */
    case Executable;

    /**
     * Anything else a format describes, like a relocatable file of the
     * compiler or a dump of the memory of a process. Nothing of the kind is
     * loadable, so there is no reason to tell one from another here.
     */
    case Other;
}
