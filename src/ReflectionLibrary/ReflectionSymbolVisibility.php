<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary;

/**
 * How far a definition is offered beyond the library defining it.
 *
 * Not every symbol a library holds is meant for its consumers, and not every
 * symbol meant for them may be replaced by another library. The visibility
 * tells the two apart.
 *
 * Where a format records nothing, everything a library offers is
 * unconditionally public and {@see self::Public} is reported.
 */
enum ReflectionSymbolVisibility
{
    /**
     * Visible to every other library, and open to being overridden by one.
     */
    case Public;

    /**
     * Visible to every other library, but never overridden by one: the
     * definition always reaches its own.
     */
    case Protected;

    /**
     * Visible only inside the library defining it.
     */
    case Private;

    /**
     * Hidden even from the library itself, i.e. unreachable through a
     * pointer taken elsewhere in the same process.
     */
    case Internal;

    /**
     * Visibility reported where the format records no distinction of its
     * own.
     */
    public const self DEFAULT = self::Public;

    /**
     * Gets whether a symbol of this visibility is offered to consumers,
     * i.e. belongs to the public interface of the library.
     */
    public function isExported(): bool
    {
        return $this === self::Public
            || $this === self::Protected;
    }
}
