<?php

declare(strict_types=1);

namespace FFI\Reflection\Exception;

/**
 * The symbols were read, but none of them carries the name asked for.
 *
 * An ordinary lookup miss rather than a defect. Note that a symbol the
 * library records no name for is not reachable this way either, however
 * the caller spells it.
 */
final class SymbolNotFoundException extends SymbolException
{
    /**
     * @param string $context name of the library or of the dependency the
     *        symbol was looked up in
     */
    public static function becauseNameIsNotOffered(
        string $name,
        string $context,
        ?\Throwable $prev = null,
    ): self {
        $message = \sprintf(
            'FFI library "%s" does not reference a symbol named "%s"',
            \addcslashes($context, '"'),
            \addcslashes($name, '"'),
        );

        return new self($message, previous: $prev);
    }
}
