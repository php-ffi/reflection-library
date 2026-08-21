<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE;

use FFI\Reflection\Exception\CorruptedBinaryException;
use FFI\Reflection\Exception\ReflectionException;
use FFI\Reflection\ReflectionLibrary\Architecture;
use FFI\Reflection\ReflectionLibrary\Endianness;
use FFI\Reflection\ReflectionLibrary\Reader\CommonLibraryInfo;
use FFI\Reflection\ReflectionLibrary\Reader\LibraryReaderInterface;
use FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata\DataDirectory;
use FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata\DosHeader;
use FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata\ExportDirectory;
use FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata\ExportEntry;
use FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata\FileHeader;
use FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata\ImportDescriptor;
use FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata\ImportThunk;
use FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata\OptionalHeader;
use FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata\PeImage;
use FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata\PeMachine;
use FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata\PeSignature;
use FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata\SectionHeader;
use FFI\Reflection\ReflectionLibrary\ReflectionExportSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionImport;
use FFI\Reflection\ReflectionLibrary\ReflectionLibraryType;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolAbi;
use FFI\Reflection\ReflectionLibrary\Stream\StreamInterface;
use FFI\Reflection\ReflectionLibrary\Stream\TypedStream;

/**
 * Reads PE-COFF images (`*.dll`, `*.exe`).
 *
 * Imports come from the import directory and from the delay-load one, each
 * descriptor naming a library and the symbols taken from it. Exports come
 * from the export directory, which addresses every entry by an ordinal and
 * names only the ones the image chose to name.
 *
 * An implementation based on {@link https://github.com/SerafimArts/LibExportView/tree/master/src/SymbolResolver/PE}
 */
final readonly class PeLibraryReader implements LibraryReaderInterface
{
    /**
     * Upper bound on the number of import descriptors, protecting against an
     * unterminated directory in a malformed image.
     */
    private const int MAX_DESCRIPTORS = 4096;

    /**
     * Upper bound on the number of entries of a single table.
     */
    private const int MAX_ENTRIES = 65536;

    /**
     * @var \WeakMap<TypedStream, PeImage>
     */
    private \WeakMap $metadata;

    public function __construct()
    {
        $this->metadata = new \WeakMap();
    }

    public function supports(StreamInterface $stream): bool
    {
        $stream->offset = 0;

        if ($stream->read(2) !== PeSignature::DOS_MAGIC) {
            return false;
        }

        // Every PE image is little endian, regardless of the architecture
        // it targets.
        $typed = new TypedStream($stream, Endianness::Little);
        $typed->offset = PeSignature::E_LFANEW_OFFSET;
        $typed->offset = $typed->uint32();

        return $typed->read(4) === PeSignature::NT_MAGIC;
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    public function getCommonInfo(TypedStream $stream): CommonLibraryInfo
    {
        $image = $this->getMetadata($stream);

        return new CommonLibraryInfo(
            addressSize: $image->optional->is64bit ? 8 : 4,
            endianness: Endianness::Little,
            architecture: $this->readArchitecture($image->file->machine),
            // A relocatable file carries a bare COFF header without the PE
            // signature, so everything reaching this point is one of the two.
            type: $image->file->isLibrary()
                ? ReflectionLibraryType::Library
                : ReflectionLibraryType::Executable,
        );
    }

    private function readArchitecture(int $machine): ?Architecture
    {
        return match ($machine) {
            PeMachine::I386 => Architecture::X86,
            PeMachine::AMD64 => Architecture::Amd64,
            PeMachine::ARM, PeMachine::ARMNT => Architecture::Arm,
            PeMachine::ARM64 => Architecture::Arm64,
            PeMachine::RISCV32, PeMachine::RISCV64 => Architecture::RiscV,
            PeMachine::POWERPC, PeMachine::POWERPCFP => Architecture::PowerPc,
            PeMachine::R4000 => Architecture::Mips,
            PeMachine::LOONGARCH32, PeMachine::LOONGARCH64 => Architecture::LoongArch,
            PeMachine::IA64 => Architecture::Ia64,
            default => null,
        };
    }

    /**
     * @return iterable<array-key, ReflectionImport>
     * @throws ReflectionException in case of the image cannot be read
     */
    public function getImports(TypedStream $stream): iterable
    {
        foreach ($this->getMetadata($stream)->imports as $descriptor) {
            $symbols = [];

            foreach ($descriptor->thunks as $thunk) {
                $symbols[] = new PeReflectionImportSymbol(
                    // A thunk asking for a slot number rather than for a
                    // name leaves the symbol without one entirely.
                    nativeName: $thunk->name,
                    name: $this->createPublicName($thunk->name),
                    isOptional: $descriptor->isDelayLoaded,
                    // A PE image records no ordinal for the symbols it takes
                    // by name.
                    ordinal: $thunk->ordinal,
                );
            }

            yield new PeReflectionImport(
                name: $descriptor->library,
                symbols: $symbols,
                isDelayLoaded: $descriptor->isDelayLoaded,
            );
        }
    }

    /**
     * @return iterable<array-key, ReflectionExportSymbol>
     * @throws ReflectionException in case of the image cannot be read
     */
    public function getSymbols(TypedStream $stream): iterable
    {
        $image = $this->getMetadata($stream);
        $exports = $image->exports;

        if ($exports === null) {
            return;
        }

        foreach ($exports->entries as $entry) {
            yield new PeReflectionExportSymbol(
                // An entry declared with the NONAME attribute sits in the
                // export address table without a name of any kind.
                nativeName: $entry->name,
                name: $this->createPublicName($entry->name),
                address: $entry->address,
                forwarder: $entry->forwarder,
                abi: $this->readAbi($entry->name, $image->optional->is64bit),
                ordinal: $entry->ordinal,
            );
        }
    }

    /**
     * Recovers the calling convention a 32-bit MSVC compiler spelled out in
     * the decoration of the name. A 64-bit image decorates nothing.
     *
     * @param non-empty-string|null $nativeName
     */
    private function readAbi(?string $nativeName, bool $is64bit): ReflectionSymbolAbi
    {
        if ($nativeName === null || $is64bit) {
            return ReflectionSymbolAbi::Default;
        }

        return match (true) {
            // The number closing every decoration counts the argument bytes,
            // so only the punctuation tells the conventions apart.
            (bool) \preg_match('/^\w+@@\d+$/', $nativeName) => ReflectionSymbolAbi::VectorCall,
            (bool) \preg_match('/^@\w+@\d+$/', $nativeName) => ReflectionSymbolAbi::FastCall,
            (bool) \preg_match('/^_\w+@\d+$/', $nativeName) => ReflectionSymbolAbi::StdCall,
            default => ReflectionSymbolAbi::CDecl,
        };
    }

    /**
     * Builds the name a caller spells out to reach the symbol. A decorated
     * export never yields one, since no C declaration can carry a suffix
     * like the `@@24` of a `__vectorcall` function.
     *
     * @param non-empty-string|null $nativeName
     * @return non-empty-string|null
     */
    private function createPublicName(?string $nativeName): ?string
    {
        if ($nativeName === null) {
            return null;
        }

        return ReflectionSymbol::isResolvableName($nativeName) ? $nativeName : null;
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    private function getMetadata(TypedStream $stream): PeImage
    {
        return $this->metadata[$stream] ??= $this->read($stream);
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    private function read(TypedStream $stream): PeImage
    {
        // Every PE image is little endian, regardless of the architecture
        // it targets.
        $stream = $stream->withLittleEndian();

        $dos = $this->readDosHeader($stream);
        $stream->offset = $dos->headersOffset;

        if ($stream->read(4) !== PeSignature::NT_MAGIC) {
            throw CorruptedBinaryException::becauseImageIsMalformed(
                'The PE image contains no NT headers',
            );
        }

        $file = $this->readFileHeader($stream);
        $optionalOffset = $dos->headersOffset + PeSignature::OPTIONAL_HEADER_OFFSET;

        if ($file->optionalHeaderSize < 2) {
            throw CorruptedBinaryException::becauseImageIsMalformed(
                'The PE image contains no optional header',
            );
        }

        $stream->offset = $optionalOffset;
        $optional = $this->readOptionalHeader($stream->slice($file->optionalHeaderSize));

        $stream->offset = $optionalOffset;
        $directories = $this->readDirectories($stream->slice($file->optionalHeaderSize), $optional);

        $stream->offset = $optionalOffset + $file->optionalHeaderSize;
        $sections = $this->readSections($stream, $file->sectionCount);

        return new PeImage(
            dos: $dos,
            file: $file,
            optional: $optional,
            sections: $sections,
            directories: $directories,
            imports: [
                ...$this->readImportDescriptors($stream, $sections, $directories, $optional),
                ...$this->readDelayImportDescriptors($stream, $sections, $directories, $optional),
            ],
            exports: $this->readExportDirectory($stream, $sections, $directories),
        );
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readDosHeader(TypedStream $stream): DosHeader
    {
        $stream->offset = 0;
        $magic = $stream->read(2);

        if ($magic !== PeSignature::DOS_MAGIC) {
            throw CorruptedBinaryException::becauseImageIsMalformed('Not a PE image');
        }

        $stream->offset = PeSignature::E_LFANEW_OFFSET;

        return new DosHeader(
            magic: $magic,
            headersOffset: $stream->uint32(),
        );
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readFileHeader(TypedStream $stream): FileHeader
    {
        return new FileHeader(
            machine: $stream->uint16(),
            sectionCount: $stream->uint16(),
            createdAt: $stream->timestamp(),
            // PointerToSymbolTable and NumberOfSymbols, both deprecated.
            optionalHeaderSize: $stream->lookahead(static function (TypedStream $stream): int {
                $stream->offset += 8;

                return $stream->uint16();
            }),
            characteristics: $stream->lookahead(static function (TypedStream $stream): int {
                $stream->offset += 10;

                return $stream->uint16();
            }),
        );
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readOptionalHeader(TypedStream $stream): OptionalHeader
    {
        $stream->offset = 0;
        $is64bit = $stream->uint16() === OptionalHeader::MAGIC_PE32PLUS;

        $linker = \sprintf('%d.%d', $stream->uint8(), $stream->uint8());

        $stream->offset = 16;
        $entryPoint = $stream->uint32();

        // A PE32 image carries an extra BaseOfData field before the base,
        // which a PE32+ one replaces by widening the base itself.
        $stream->offset = $is64bit ? 24 : 28;
        $base = $stream->address($is64bit);

        $stream->offset = 40;
        $operatingSystem = \sprintf('%d.%d', $stream->uint16(), $stream->uint16());

        $stream->offset = 68;
        $subsystem = $stream->uint16();
        $characteristics = $stream->uint16();

        return new OptionalHeader(
            is64bit: $is64bit,
            base: $base,
            entryPoint: $entryPoint,
            subsystem: $subsystem,
            characteristics: $characteristics,
            operatingSystemVersion: $operatingSystem,
            linkerVersion: $linker,
        );
    }

    /**
     * @return list<DataDirectory>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readDirectories(TypedStream $stream, OptionalHeader $optional): array
    {
        $stream->offset = $optional->is64bit ? 108 : 92;
        $count = $stream->uint32();

        $result = [];

        for ($i = 0; $i < \min($count, self::MAX_ENTRIES); ++$i) {
            $result[] = new DataDirectory(
                address: $stream->uint32(),
                size: $stream->uint32(),
            );
        }

        return $result;
    }

    /**
     * @param int<0, 65535> $count
     * @return list<SectionHeader>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readSections(TypedStream $stream, int $count): array
    {
        if ($count === 0) {
            return [];
        }

        $entries = $stream->slice($count * PeSignature::SECTION_HEADER_SIZE);
        $result = [];

        for ($i = 0; $i < $count; ++$i) {
            $entries->offset = $i * 40;

            $result[] = new SectionHeader(
                name: $entries->string(8),
                size: $entries->uint32(),
                address: $entries->uint32(),
                rawSize: $entries->uint32(),
                rawOffset: $entries->uint32(),
                characteristics: $entries->lookahead(static function (TypedStream $stream): int {
                    // PointerToRelocations, PointerToLinenumbers,
                    // NumberOfRelocations and NumberOfLinenumbers.
                    $stream->offset += 12;

                    return $stream->uint32();
                }),
            );
        }

        return $result;
    }

    /**
     * @param list<SectionHeader> $sections
     * @param list<DataDirectory> $directories
     * @return list<ImportDescriptor>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readImportDescriptors(
        TypedStream $stream,
        array $sections,
        array $directories,
        OptionalHeader $optional,
    ): array {
        $offset = $this->findDirectoryOffset($sections, $directories, DataDirectory::INDEX_IMPORT);

        if ($offset === null) {
            return [];
        }

        $result = [];

        for ($i = 0; $i < self::MAX_DESCRIPTORS; ++$i) {
            $stream->offset = $offset + $i * PeSignature::IMPORT_DESCRIPTOR_SIZE;

            $names = $stream->uint32();
            $stream->offset += 8; // TimeDateStamp and ForwarderChain
            $library = $stream->uint32();
            $addresses = $stream->uint32();

            if ($names === 0 && $library === 0 && $addresses === 0) {
                break;
            }

            $name = $this->findString($stream, $sections, $library);

            // The name is what the loader looks a library up by, so a
            // descriptor carrying none declares no dependency.
            if ($name === null) {
                continue;
            }

            // The import name table is optional: a bound image may carry the
            // address table alone, which still holds the original thunks.
            $result[] = new ImportDescriptor(
                library: $name,
                thunks: $this->readThunks($stream, $sections, $optional, $names !== 0 ? $names : $addresses),
                isDelayLoaded: false,
            );
        }

        return $result;
    }

    /**
     * @param list<SectionHeader> $sections
     * @param list<DataDirectory> $directories
     * @return list<ImportDescriptor>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readDelayImportDescriptors(
        TypedStream $stream,
        array $sections,
        array $directories,
        OptionalHeader $optional,
    ): array {
        $offset = $this->findDirectoryOffset($sections, $directories, DataDirectory::INDEX_DELAY_IMPORT);

        if ($offset === null) {
            return [];
        }

        $result = [];

        for ($i = 0; $i < self::MAX_DESCRIPTORS; ++$i) {
            $stream->offset = $offset + $i * PeSignature::DELAY_IMPORT_DESCRIPTOR_SIZE;

            $attributes = $stream->uint32();
            $library = $stream->uint32();
            $stream->offset += 4; // ModuleHandleRVA
            $addresses = $stream->uint32();
            $names = $stream->uint32();

            if ($library === 0 && $names === 0 && $addresses === 0) {
                break;
            }

            // Linkers older than Visual Studio 2005 store virtual addresses
            // instead of relative ones, marked by the low attribute bit.
            if (($attributes & 1) === 0) {
                $library = \max(0, $library - $optional->base);
                $names = $names === 0 ? 0 : \max(0, $names - $optional->base);
                $addresses = $addresses === 0 ? 0 : \max(0, $addresses - $optional->base);
            }

            $name = $this->findString($stream, $sections, $library);

            // The name is what the loader looks a library up by, so a
            // descriptor carrying none declares no dependency.
            if ($name === null) {
                continue;
            }

            $result[] = new ImportDescriptor(
                library: $name,
                thunks: $this->readThunks($stream, $sections, $optional, $names !== 0 ? $names : $addresses),
                isDelayLoaded: true,
            );
        }

        return $result;
    }

    /**
     * @param list<SectionHeader> $sections
     * @return list<ImportThunk>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readThunks(
        TypedStream $stream,
        array $sections,
        OptionalHeader $optional,
        int $address,
    ): array {
        $offset = $this->findOffset($sections, $address);

        if ($offset === null) {
            return [];
        }

        $size = $optional->is64bit ? 8 : 4;
        $result = [];

        for ($i = 0; $i < self::MAX_ENTRIES; ++$i) {
            $stream->offset = $offset + $i * $size;
            $thunk = $stream->address($optional->is64bit);

            if ($thunk === 0) {
                break;
            }

            // The most significant bit marks a symbol addressed by ordinal,
            // which on a 64-bit image makes the value negative.
            $byOrdinal = $optional->is64bit ? $thunk < 0 : ($thunk & PeSignature::ORDINAL_FLAG_32) !== 0;

            if ($byOrdinal) {
                /** @var int<0, 65535> $ordinal */
                $ordinal = $thunk & 0xFFFF;

                $result[] = new ImportThunk(name: null, ordinal: $ordinal, hint: null);

                continue;
            }

            $entry = $this->findOffset($sections, $thunk & PeSignature::THUNK_ADDRESS_MASK);

            if ($entry === null) {
                continue;
            }

            $stream->offset = $entry;
            $hint = $stream->uint16();
            $name = $stream->string();

            if ($name !== '') {
                $result[] = new ImportThunk(name: $name, ordinal: null, hint: $hint);
            }
        }

        return $result;
    }

    /**
     * @param list<SectionHeader> $sections
     * @param list<DataDirectory> $directories
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readExportDirectory(
        TypedStream $stream,
        array $sections,
        array $directories,
    ): ?ExportDirectory {
        $directory = $directories[DataDirectory::INDEX_EXPORT] ?? null;
        $offset = $this->findDirectoryOffset($sections, $directories, DataDirectory::INDEX_EXPORT);

        if ($directory === null || $offset === null) {
            return null;
        }

        $stream->offset = $offset + 4;
        $createdAt = $stream->timestamp();

        $stream->offset = $offset + 12;
        $name = $this->findString($stream, $sections, $stream->uint32());

        $stream->offset = $offset + 16;
        $base = $stream->uint32();
        $functionCount = \min($stream->uint32(), self::MAX_ENTRIES);
        $nameCount = \min($stream->uint32(), self::MAX_ENTRIES);
        $functionTable = $stream->uint32();
        $nameTable = $stream->uint32();
        $ordinalTable = $stream->uint32();

        return new ExportDirectory(
            name: $name,
            base: $base,
            createdAt: $createdAt,
            entries: $this->readExportEntries(
                stream: $stream,
                sections: $sections,
                directory: $directory,
                base: $base,
                functions: $this->readAddresses($stream, $sections, $functionTable, $functionCount),
                names: $this->readExportNames(
                    stream: $stream,
                    sections: $sections,
                    nameTable: $nameTable,
                    ordinalTable: $ordinalTable,
                    count: $nameCount,
                ),
            ),
        );
    }

    /**
     * @param list<SectionHeader> $sections
     * @param list<int> $functions
     * @param array<int, non-empty-string> $names
     * @return list<ExportEntry>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readExportEntries(
        TypedStream $stream,
        array $sections,
        DataDirectory $directory,
        int $base,
        array $functions,
        array $names,
    ): array {
        $result = [];

        foreach ($functions as $index => $address) {
            // An unused slot of the export address table holds a zero and
            // belongs to no symbol at all.
            if ($address === 0) {
                continue;
            }

            // An address inside the export directory is the name of the
            // symbol taking the call over, not code.
            $forwarded = $directory->contains($address);

            /** @var int<0, max> $ordinal */
            $ordinal = \max(0, $base + $index);

            $result[] = new ExportEntry(
                name: $names[$index] ?? null,
                ordinal: $ordinal,
                address: $forwarded ? null : $address,
                forwarder: $forwarded ? $this->findString($stream, $sections, $address) : null,
            );
        }

        return $result;
    }

    /**
     * Reads the export name table, mapping the index of an entry of the
     * export address table onto the name offered for it.
     *
     * @param list<SectionHeader> $sections
     * @return array<int, non-empty-string>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readExportNames(
        TypedStream $stream,
        array $sections,
        int $nameTable,
        int $ordinalTable,
        int $count,
    ): array {
        $names = $this->readAddresses($stream, $sections, $nameTable, $count);
        $ordinals = $this->findOffset($sections, $ordinalTable);

        if ($ordinals === null) {
            return [];
        }

        $result = [];

        foreach ($names as $index => $address) {
            $stream->offset = $ordinals + $index * 2;
            $ordinal = $stream->uint16();

            $name = $this->findString($stream, $sections, $address);

            if ($name !== null) {
                $result[$ordinal] = $name;
            }
        }

        return $result;
    }

    /**
     * Reads a table of relative addresses.
     *
     * @param list<SectionHeader> $sections
     * @return list<int>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readAddresses(TypedStream $stream, array $sections, int $address, int $count): array
    {
        $offset = $this->findOffset($sections, $address);

        if ($offset === null || $count <= 0) {
            return [];
        }

        $stream->offset = $offset;
        $entries = $stream->slice($count * 4);

        $result = [];

        for ($i = 0; $i < $count; ++$i) {
            $result[] = $entries->uint32();
        }

        return $result;
    }

    /**
     * @param list<SectionHeader> $sections
     * @param list<DataDirectory> $directories
     */
    private function findDirectoryOffset(array $sections, array $directories, int $index): ?int
    {
        $directory = $directories[$index] ?? null;

        if ($directory === null || !$directory->isPresent()) {
            return null;
        }

        return $this->findOffset($sections, $directory->address);
    }

    /**
     * @param list<SectionHeader> $sections
     */
    private function findOffset(array $sections, int $address): ?int
    {
        foreach ($sections as $section) {
            $offset = $section->findOffsetOf($address);

            if ($offset !== null) {
                return $offset;
            }
        }

        return null;
    }

    /**
     * Name a relative address of the image points at.
     *
     * @param list<SectionHeader> $sections
     * @return non-empty-string|null {@see null} in case of the image holds
     *         no name at that address
     * @throws ReflectionException in case of the image cannot be read
     */
    private function findString(TypedStream $stream, array $sections, int $address): ?string
    {
        // A relative address of 0 means that the field is not set.
        if ($address === 0) {
            return null;
        }

        $offset = $this->findOffset($sections, $address);

        if ($offset === null) {
            return null;
        }

        $stream->offset = $offset;
        $result = $stream->string();

        return $result === '' ? null : $result;
    }
}
