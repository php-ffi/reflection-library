<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata;

/**
 * Raw contents of a single `nlist` or `nlist_64` entry of the symbol table,
 * with its name already resolved.
 */
final readonly class NameListEntry
{
    /**
     * Mask of the {@see $type} field selecting the kind of the symbol
     * (`N_TYPE`).
     */
    public const int MASK_TYPE = 0x0E;

    /**
     * Mask of the {@see $type} field marking a debugging entry (`N_STAB`).
     */
    public const int MASK_DEBUG = 0xE0;

    /**
     * Bit of the {@see $type} field marking an external symbol (`N_EXT`).
     */
    public const int FLAG_EXTERNAL = 0x01;

    /**
     * Value of the `N_TYPE` bits of an undefined symbol (`N_UNDF`).
     */
    public const int TYPE_UNDEFINED = 0x00;

    /**
     * Bit of the {@see $description} field marking a weak reference
     * (`N_WEAK_REF`).
     */
    public const int FLAG_WEAK_REFERENCE = 0x0040;

    public function __construct(
        /**
         * Resolved value of the `n_strx` field.
         */
        public string $name,
        /**
         * Value of the `n_type` field.
         *
         * @var int<0, 255>
         */
        public int $type,
        /**
         * Value of the `n_sect` field, i.e. the one-based index of the
         * section defining the symbol, or zero for an undefined one.
         *
         * @var int<0, 255>
         */
        public int $section,
        /**
         * Value of the `n_desc` field, holding both the flags of the symbol
         * and the ordinal of the library providing it.
         *
         * @var int<0, 65535>
         */
        public int $description,
        /**
         * Value of the `n_value` field, i.e. the virtual address of the
         * symbol, or zero for an undefined one.
         */
        public int $address,
    ) {}

    /**
     * Gets whether the entry describes a debugging symbol rather than a
     * linking one.
     */
    public function isDebug(): bool
    {
        return ($this->type & self::MASK_DEBUG) !== 0;
    }

    /**
     * Gets whether the symbol is visible outside of the image.
     */
    public function isExternal(): bool
    {
        return ($this->type & self::FLAG_EXTERNAL) !== 0;
    }

    /**
     * Gets whether the symbol is referenced but not defined by the image.
     */
    public function isUndefined(): bool
    {
        return ($this->type & self::MASK_TYPE) === self::TYPE_UNDEFINED;
    }

    /**
     * Gets whether the loader is allowed to leave the symbol unresolved.
     */
    public function isWeakReference(): bool
    {
        return ($this->description & self::FLAG_WEAK_REFERENCE) !== 0;
    }

    /**
     * Gets the ordinal of the library providing the symbol, which the
     * `n_desc` field carries in its high byte.
     *
     * @return int<0, 255>
     */
    public function getLibraryOrdinal(): int
    {
        /** @var int<0, 255> */
        return $this->description >> 8 & 0xFF;
    }
}
