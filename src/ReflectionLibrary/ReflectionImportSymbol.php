<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary;

/**
 * A symbol the image takes from one of its dependencies.
 *
 * Such a symbol has no body here, so it carries neither an address nor a
 * size: both are only known once the loader has bound it. Which library
 * provides it is a property of the owning {@see ReflectionImport} rather
 * than of the symbol.
 */
abstract readonly class ReflectionImportSymbol extends ReflectionSymbol
{
    /**
     * @param non-empty-string|null $nativeName
     * @param non-empty-string|null $name
     */
    public function __construct(
        ?string $nativeName,
        ?string $name,
        /**
         * Whether the loader is allowed to leave the symbol unresolved
         * instead of failing.
         */
        private bool $isOptional,
        /**
         * Version of the symbol the library was linked against.
         *
         * A platform that versions its symbols lets one library keep
         * several definitions of the same name apart, so that an old
         * consumer keeps reaching the definition it was built for.
         *
         * Equals {@see null} in case of the symbol carries no version
         * requirement, which is always so where the platform has no such
         * notion.
         *
         * @var non-empty-string|null
         */
        private ?string $version,
    ) {
        parent::__construct($nativeName, $name);
    }

    /**
     * Gets whether the loader is allowed to leave the symbol unresolved.
     */
    public function isOptional(): bool
    {
        return $this->isOptional;
    }

    /**
     * Gets the version of the symbol the library was linked against.
     *
     * @return non-empty-string|null
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function __toString(): string
    {
        $result = parent::__toString();

        if ($this->version !== null) {
            $result .= '@' . $this->version;
        }

        return $result;
    }
}
