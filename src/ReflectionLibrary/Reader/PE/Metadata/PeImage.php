<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata;

/**
 * Everything read out of a PE image in a single pass.
 *
 * The object is a plain carrier of the raw structures of the format: turning
 * them into reflection objects is the job of the driver.
 */
final readonly class PeImage
{
    public function __construct(
        public DosHeader $dos,
        public FileHeader $file,
        public OptionalHeader $optional,
        /**
         * Entries of the section table, in file order.
         *
         * @var list<SectionHeader>
         */
        public array $sections,
        /**
         * Entries of the data directory, indexed by their well known
         * position.
         *
         * @var list<DataDirectory>
         */
        public array $directories,
        /**
         * Descriptors of the import and delay-load import directories, the
         * regular ones first.
         *
         * @var list<ImportDescriptor>
         */
        public array $imports,
        /**
         * Export directory of the image, or {@see null} in case of the image
         * offers no symbols.
         */
        public ?ExportDirectory $exports,
    ) {}

    /**
     * Gets a data directory by its well known index.
     */
    public function findDirectory(int $index): ?DataDirectory
    {
        $directory = $this->directories[$index] ?? null;

        return $directory?->isPresent() === true ? $directory : null;
    }

    /**
     * Converts a relative virtual address into a file offset, or gets
     * {@see null} in case of no section holds it.
     */
    public function findOffsetOf(int $address): ?int
    {
        foreach ($this->sections as $section) {
            $offset = $section->findOffsetOf($address);

            if ($offset !== null) {
                return $offset;
            }
        }

        return null;
    }
}
