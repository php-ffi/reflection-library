<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata;

/**
 * A single terminal node of the dyld export trie, i.e. one symbol the image
 * offers to other objects.
 */
final readonly class ExportEntry
{
    /**
     * Mask of the {@see $flags} field selecting the kind of the symbol.
     */
    public const int MASK_KIND = 0x03;

    /**
     * Bit of the {@see $flags} field marking a weak definition, i.e. one
     * another image is allowed to override.
     */
    public const int FLAG_WEAK_DEFINITION = 0x04;

    /**
     * Bit of the {@see $flags} field marking a symbol whose definition lives
     * in one of the libraries the image re-exports.
     */
    public const int FLAG_REEXPORT = 0x08;

    /**
     * Bit of the {@see $flags} field marking a symbol resolved by running
     * code at load time.
     */
    public const int FLAG_STUB_AND_RESOLVER = 0x10;

    public function __construct(
        /**
         * Name of the symbol, assembled from the edges leading to this node.
         *
         * @var non-empty-string
         */
        public string $name,
        /**
         * Address of the symbol relative to the base of the image, or
         * {@see null} for a re-exported one, which has no body here.
         */
        public ?int $address,
        /**
         * Flags of the terminal node.
         */
        public int $flags,
        /**
         * Ordinal of the library defining a re-exported symbol, or
         * {@see null} in case of the symbol is defined by this image.
         *
         * @var int<0, 255>|null
         */
        public ?int $reexportOrdinal,
        /**
         * Name the symbol carries in the library it is re-exported from, in
         * case of it differs from {@see $name}.
         *
         * @var non-empty-string|null
         */
        public ?string $reexportName,
    ) {}

    /**
     * Gets whether another image is allowed to override the definition.
     */
    public function isWeak(): bool
    {
        return ($this->flags & self::FLAG_WEAK_DEFINITION) !== 0;
    }

    /**
     * Gets whether the definition lives in one of the libraries the image
     * re-exports.
     */
    public function isReexport(): bool
    {
        return ($this->flags & self::FLAG_REEXPORT) !== 0;
    }
}
