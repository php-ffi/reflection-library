<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata;

/**
 * Raw contents of the `IMAGE_EXPORT_DIRECTORY` structure, with its
 * entries resolved.
 */
final readonly class ExportDirectory
{
    public function __construct(
        /**
         * Resolved value of the `Name` field, i.e. the name the
         * library gives itself, which may differ from its file name.
         */
        public string $name,
        /**
         * Value of the `Base` field, i.e. the ordinal of the first
         * entry of the export address table.
         */
        public int $base,
        /**
         * Value of the `TimeDateStamp` field.
         */
        public \DateTimeImmutable $createdAt,
        /**
         * Symbols offered by the image, in ordinal order.
         *
         * @var list<ExportEntry>
         */
        public array $entries,
    ) {}
}
