<?php

declare(strict_types=1);

namespace FFI\Reflection\Exception;

/**
 * The library is well formed but carries no table to read symbols from.
 *
 * This is not a defect: a library may be built without the tables a loader
 * does not strictly need. It simply cannot answer questions about symbols,
 * so no name will ever be found in it.
 */
final class SymbolTableNotFoundException extends SymbolException
{
    public static function becauseTableIsAbsent(string $message, ?\Throwable $prev = null): self
    {
        return new self($message, previous: $prev);
    }
}
