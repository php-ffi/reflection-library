<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata;

/**
 * Everything read out of a Mach-O image in a single pass.
 *
 * The object is a plain carrier of the raw structures of the format: turning
 * them into reflection objects is the job of the driver.
 */
final readonly class MachOImage
{
    public function __construct(
        public MachHeader $header,
        /**
         * Libraries declared by the dylib load commands, in the order that
         * defines their ordinals.
         *
         * @var list<Dylib>
         */
        public array $dylibs,
        /**
         * Entries of the symbol table, in table order.
         *
         * @var list<NameListEntry>
         */
        public array $symbols,
        /**
         * Symbols the image offers to other objects.
         *
         * @var list<ExportEntry>
         */
        public array $exports,
    ) {}

    /**
     * Gets the library the given ordinal references, or {@see null} in case
     * of the ordinal is one of the reserved values.
     */
    public function findDylibByOrdinal(int $ordinal): ?Dylib
    {
        foreach ($this->dylibs as $dylib) {
            if ($dylib->ordinal === $ordinal) {
                return $dylib;
            }
        }

        return null;
    }
}
