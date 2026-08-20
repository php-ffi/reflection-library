<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary;

/**
 * A symbol of a library.
 *
 * A symbol carries two names. {@see $nativeName} is what the library stores,
 * decorations and all, and {@see $name} is the one a caller has to spell out
 * to reach the symbol through {@see \FFI::cdef()}. The two differ whenever
 * the platform decorates its symbols, and either of them is {@see null}
 * whenever the library records nothing usable.
 *
 * The two directions carry different information beyond that, so a symbol is
 * always one of {@see ReflectionImportSymbol} and {@see ReflectionExportSymbol}.
 */
abstract readonly class ReflectionSymbol implements \Reflector
{
    /**
     * Matches the names a C compiler accepts as an identifier.
     */
    private const string IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public function __construct(
        /**
         * Name of the symbol exactly as the library stores it.
         *
         * This is the name a disassembler shows, which for a decorated
         * symbol is not the one a loader takes: a platform may prefix or
         * suffix what the source called the symbol.
         *
         * Equals {@see null} in case of the library records no name at
         * all and leaves a slot number as the only way of addressing the
         * symbol.
         *
         * @var non-empty-string|null
         */
        private ?string $nativeName,
        /**
         * Name a caller spells out to reach the symbol, i.e. the one to put
         * into an {@see \FFI::cdef()} block.
         *
         * Equals {@see null} in case of no such name exists, either because
         * the library records none at all or because the recorded one is
         * not spellable as a C identifier. A decoration carrying the
         * calling convention is the usual reason for the latter.
         *
         * @var non-empty-string|null
         */
        public ?string $name,
    ) {}

    /**
     * Checks that a caller can spell the given name out, i.e. that it is a
     * valid C identifier.
     *
     * A decorated name fails this: the suffix or prefix a platform adds is
     * part of the name the loader knows but not something a C declaration
     * can carry.
     */
    public static function isResolvableName(string $name): bool
    {
        return \preg_match(self::IDENTIFIER_PATTERN, $name) === 1;
    }

    /**
     * Indexes the symbols by the name a caller spells out, dropping the ones
     * that have none.
     *
     * @template TArgSymbol of self
     * @param iterable<mixed, TArgSymbol> $symbols
     * @return array<non-empty-string, TArgSymbol>
     */
    public static function groupByName(iterable $symbols): array
    {
        $result = [];

        foreach ($symbols as $symbol) {
            if ($symbol->name !== null && !isset($result[$symbol->name])) {
                $result[$symbol->name] = $symbol;
            }
        }

        return $result;
    }

    /**
     * Gets the name of the symbol exactly as the library stores it.
     *
     * @return non-empty-string|null
     */
    public function getNativeName(): ?string
    {
        return $this->nativeName;
    }

    /**
     * Gets the name a caller spells out to reach the symbol.
     *
     * @return non-empty-string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Gets whether the library records no name for the symbol at all, which
     * leaves its slot number as the only way of addressing it.
     */
    public function isAnonymous(): bool
    {
        return $this->nativeName === null;
    }

    /**
     * Gets whether a caller can reach the symbol by name at all, i.e.
     * whether it can be declared in an {@see \FFI::cdef()} block and then
     * resolved by the loader.
     */
    public function isResolvable(): bool
    {
        return $this->name !== null;
    }

    public function __toString(): string
    {
        return $this->nativeName
            ?? '<anonymous>';
    }
}
