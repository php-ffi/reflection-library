<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary;

use FFI\Reflection\Exception\ReflectionException;
use FFI\Reflection\Exception\SymbolNotFoundException;

/**
 * An external library the image depends on, together with the symbols
 * referenced from it.
 *
 * Only the members declared here are available in every supported binary
 * format, everything else lives in the format-specific subclasses.
 */
abstract class ReflectionImport implements \Reflector
{
    /**
     * Symbols referenced from this library.
     *
     * @var list<ReflectionImportSymbol>
     */
    private readonly array $symbols;

    /**
     * @var array<non-empty-string, ReflectionImportSymbol>
     */
    private array $symbolByNames {
        get => $this->symbolByNames ??= ReflectionImportSymbol::groupByName($this->symbols);
    }

    /**
     * @param iterable<mixed, ReflectionImportSymbol> $symbols
     */
    public function __construct(
        /**
         * Name of the library, as recorded by the image.
         *
         * @var non-empty-string
         */
        public string $name,
        /**
         * Whether the loader is allowed to continue without this library
         * instead of failing.
         */
        private readonly bool $isOptional,
        iterable $symbols,
    ) {
        $this->symbols = \iterator_to_array($symbols, false);
    }

    /**
     * @template TArgImport of self
     * @param iterable<mixed, TArgImport> $imports
     * @param bool $lowercase whether the keys are lowercased, which is what
     *        a case-insensitive lookup calls for
     * @return array<($lowercase is true ? non-empty-lowercase-string : non-empty-string), TArgImport>
     */
    public static function groupByName(iterable $imports, bool $lowercase = false): array
    {
        $result = [];

        foreach ($imports as $import) {
            $name = $import->name;

            if ($lowercase) {
                $name = \strtolower($name);
            }

            if (!isset($result[$name])) {
                $result[$name] = $import;
            }
        }

        return $result;
    }

    /**
     * Gets the name of the library.
     *
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets whether the loader is allowed to continue without this library.
     */
    public function isOptional(): bool
    {
        return $this->isOptional;
    }

    /**
     * Gets the list of symbols referenced from this library.
     *
     * @return list<ReflectionImportSymbol>
     */
    public function getSymbols(): array
    {
        return $this->symbols;
    }

    /**
     * Gets the symbol with the given name.
     *
     * @param non-empty-string $name
     * @throws ReflectionException in case of no such symbol is referenced
     */
    public function getSymbol(string $name): ReflectionImportSymbol
    {
        return $this->findSymbol($name)
            ?? throw SymbolNotFoundException::becauseNameIsNotOffered($name, $this->name);
    }

    /**
     * Gets the symbol with the given name or {@see null} in case of the
     * library does not provide it.
     *
     * @param non-empty-string $name
     */
    public function findSymbol(string $name): ?ReflectionImportSymbol
    {
        return $this->symbolByNames[$name] ?? null;
    }

    /**
     * Gets whether a symbol with the given name is referenced from this
     * library.
     *
     * @param non-empty-string $name
     */
    public function hasSymbol(string $name): bool
    {
        return isset($this->symbolByNames[$name]);
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
