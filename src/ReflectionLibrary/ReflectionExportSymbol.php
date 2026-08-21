<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary;

/**
 * A symbol the image offers to its consumers, i.e. a part of its public
 * interface.
 *
 * Such a symbol is defined by the library, so it has an address of its own,
 * unless the library passes every call on to another one instead.
 *
 * Note that it is never optional: being offered is unconditional. What comes
 * closest is a weak {@see $binding}, which only decides who wins when
 * several libraries define the same name.
 */
abstract readonly class ReflectionExportSymbol extends ReflectionSymbol
{
    /**
     * @param non-empty-string|null $nativeName
     * @param non-empty-string|null $name
     */
    public function __construct(
        ?string $nativeName,
        ?string $name,
        /**
         * Address of the symbol relative to the base of the library.
         */
        private ?int $address,
        /**
         * Target the library passes every call on to, formatted as
         * `Library.Symbol`.
         *
         * @var non-empty-string|null
         */
        private ?string $forwarder,
        /**
         * How the definition behaves when several images offer the same
         * symbol.
         */
        private ?ReflectionSymbolResolution $binding,
        /**
         * How far the definition is offered beyond this library.
         */
        private ReflectionSymbolVisibility $visibility,
        /**
         * Calling convention the symbol expects.
         */
        private ReflectionSymbolAbi $abi,
    ) {
        parent::__construct($nativeName, $name);
    }

    /**
     * Gets the address of the symbol relative to the base of the library.
     */
    public function getAddress(): ?int
    {
        return $this->address;
    }

    /**
     * Gets the target the library passes every call on to.
     *
     * @return non-empty-string|null
     */
    public function getForwarder(): ?string
    {
        return $this->forwarder;
    }

    /**
     * Gets how the definition behaves when several images offer the same
     * symbol.
     */
    public function getBinding(): ?ReflectionSymbolResolution
    {
        return $this->binding;
    }

    /**
     * Gets how far the definition is offered beyond this library.
     */
    public function getVisibility(): ReflectionSymbolVisibility
    {
        return $this->visibility;
    }

    /**
     * Gets the calling convention the symbol expects.
     */
    public function getAbi(): ReflectionSymbolAbi
    {
        return $this->abi;
    }

    /**
     * Gets whether the library passes every call on to another one.
     */
    public function isForwarded(): bool
    {
        return $this->forwarder !== null;
    }

    /**
     * Gets whether another library defining the same symbol is allowed to
     * override this definition.
     */
    public function isWeak(): bool
    {
        return $this->binding === ReflectionSymbolResolution::Weak;
    }
}
