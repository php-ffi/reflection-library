<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\ELF;

use FFI\Reflection\Exception\CorruptedBinaryException;
use FFI\Reflection\Exception\ReflectionException;
use FFI\Reflection\Exception\SymbolTableNotFoundException;
use FFI\Reflection\ReflectionLibrary\Architecture;
use FFI\Reflection\ReflectionLibrary\Endianness;
use FFI\Reflection\ReflectionLibrary\Reader\CommonLibraryInfo;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata\ElfDynamicTag;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata\ElfHeader;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata\ElfIdentity;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata\ElfImage;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata\ElfMachine;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata\ElfObjectType;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata\ElfProgramType;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata\ElfSection;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata\ElfSectionType;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata\ElfSymbol;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata\ElfSymbolInfo;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata\ElfSymbolVersion;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\Metadata\ElfVersion;
use FFI\Reflection\ReflectionLibrary\Reader\LibraryReaderInterface;
use FFI\Reflection\ReflectionLibrary\ReflectionExportSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionImport;
use FFI\Reflection\ReflectionLibrary\ReflectionLibraryType;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolResolution;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolVisibility;
use FFI\Reflection\ReflectionLibrary\Stream\StreamInterface;
use FFI\Reflection\ReflectionLibrary\Stream\TypedStream;

/**
 * Reads ELF images (also known as DSO, `*.so`).
 *
 * Both imports and exports live in the dynamic symbol table. ELF resolves the
 * undefined ones in a flat namespace, so an import is only attributable to a
 * library when it carries a version requirement.
 *
 * An implementation based on {@see https://github.com/ircmaxell/php-object-symbolresolver/tree/master/lib/ELF}
 */
final readonly class ElfLibraryReader implements LibraryReaderInterface
{
    /**
     * @var \WeakMap<TypedStream, ElfImage>
     */
    private \WeakMap $metadata;

    public function __construct()
    {
        $this->metadata = new \WeakMap();
    }

    public function supports(StreamInterface $stream): bool
    {
        $stream->offset = 0;

        return $stream->read(4) === ElfIdentity::MAGIC;
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    public function getCommonInfo(TypedStream $stream): CommonLibraryInfo
    {
        $image = $this->getMetadata($stream);

        return new CommonLibraryInfo(
            addressSize: $image->header->is64bit ? 8 : 4,
            endianness: $this->readEndianness($image->header->littleEndian),
            architecture: $this->readArchitecture($image->header->machine),
            type: $this->readObjectType($image),
        );
    }

    private function readArchitecture(int $machine): ?Architecture
    {
        return match ($machine) {
            ElfMachine::EM_386 => Architecture::X86,
            ElfMachine::EM_X86_64 => Architecture::Amd64,
            ElfMachine::EM_ARM => Architecture::Arm,
            ElfMachine::EM_AARCH64 => Architecture::Arm64,
            ElfMachine::EM_RISCV => Architecture::RiscV,
            ElfMachine::EM_PPC => Architecture::PowerPc,
            ElfMachine::EM_PPC64 => Architecture::PowerPc64,
            ElfMachine::EM_MIPS => Architecture::Mips,
            ElfMachine::EM_SPARC, ElfMachine::EM_SPARCV9 => Architecture::Sparc,
            ElfMachine::EM_S390 => Architecture::S390x,
            ElfMachine::EM_LOONGARCH => Architecture::LoongArch,
            ElfMachine::EM_IA_64 => Architecture::Ia64,
            default => null,
        };
    }

    private function readObjectType(ElfImage $image): ReflectionLibraryType
    {
        return match ($image->header->type) {
            ElfObjectType::ET_EXEC => ReflectionLibraryType::Executable,
            // A shared object is a library unless it names an interpreter,
            // in which case it is a position independent executable.
            ElfObjectType::ET_DYN => $image->hasInterpreter
                ? ReflectionLibraryType::Executable
                : ReflectionLibraryType::Library,
            default => ReflectionLibraryType::Other,
        };
    }

    /**
     * @return iterable<array-key, ReflectionImport>
     * @throws ReflectionException in case of the image cannot be read
     */
    public function getImports(TypedStream $stream): iterable
    {
        $image = $this->getMetadata($stream);

        $symbols = [];

        foreach ($image->symbols as $symbol) {
            if ($symbol->isUndefined() && $symbol->version !== null && $symbol->name !== '') {
                $symbols[$symbol->version->library][] = $this->createSymbol($symbol);
            }
        }

        foreach ($image->needed as $library) {
            yield new ElfReflectionImport(
                name: $library,
                symbols: $symbols[$library] ?? [],
                versions: $image->getVersionsOf($library),
            );
        }
    }

    /**
     * @return iterable<array-key, ReflectionExportSymbol>
     * @throws ReflectionException in case of the image cannot be read
     */
    public function getSymbols(TypedStream $stream): iterable
    {
        foreach ($this->getMetadata($stream)->symbols as $symbol) {
            if ($symbol->isUndefined() || $symbol->name === '') {
                continue;
            }

            $visibility = $this->readVisibility($symbol->visibility);

            if ($symbol->binding === ElfSymbolInfo::STB_LOCAL || !$visibility->isExported()) {
                continue;
            }

            $nativeName = $this->readName($symbol);

            yield new ElfReflectionExportSymbol(
                nativeName: $nativeName,
                name: ReflectionSymbol::isResolvableName($nativeName) ? $nativeName : null,
                address: $symbol->address,
                binding: $this->readBinding($symbol->binding),
                visibility: $visibility,
                size: $symbol->size,
                type: $this->readType($symbol->type),
                index: $symbol->index,
            );
        }
    }

    private function readVisibility(int $visibility): ReflectionSymbolVisibility
    {
        return match ($visibility) {
            ElfSymbolInfo::STV_INTERNAL => ReflectionSymbolVisibility::Internal,
            ElfSymbolInfo::STV_HIDDEN => ReflectionSymbolVisibility::Private,
            ElfSymbolInfo::STV_PROTECTED => ReflectionSymbolVisibility::Protected,
            // The field is two bits wide, so nothing else can appear.
            default => ReflectionSymbolVisibility::Public,
        };
    }

    private function readType(int $type): ?SymbolType
    {
        return match ($type) {
            ElfSymbolInfo::STT_NOTYPE => SymbolType::NoType,
            ElfSymbolInfo::STT_OBJECT => SymbolType::Object,
            ElfSymbolInfo::STT_FUNC => SymbolType::Func,
            ElfSymbolInfo::STT_SECTION => SymbolType::Section,
            ElfSymbolInfo::STT_FILE => SymbolType::File,
            ElfSymbolInfo::STT_COMMON => SymbolType::Common,
            ElfSymbolInfo::STT_TLS => SymbolType::Tls,
            ElfSymbolInfo::STT_GNU_IFUNC => SymbolType::GnuIFunc,
            default => null,
        };
    }

    private function readBinding(int $binding): ?ReflectionSymbolResolution
    {
        return match ($binding) {
            ElfSymbolInfo::STB_GLOBAL => ReflectionSymbolResolution::Global,
            ElfSymbolInfo::STB_WEAK => ReflectionSymbolResolution::Weak,
            ElfSymbolInfo::STB_GNU_UNIQUE => ReflectionSymbolResolution::Unique,
            default => null,
        };
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    private function createSymbol(ElfSymbol $symbol): ElfReflectionImportSymbol
    {
        $nativeName = $this->readName($symbol);

        return new ElfReflectionImportSymbol(
            nativeName: $nativeName,
            name: ReflectionSymbol::isResolvableName($nativeName) ? $nativeName : null,
            // A weak reference is bound to zero instead of aborting the load.
            isOptional: $symbol->binding === ElfSymbolInfo::STB_WEAK,
            version: $symbol->version?->name,
            index: $symbol->index,
        );
    }

    /**
     * @return non-empty-string
     * @throws ReflectionException in case of the symbol carries no name
     */
    private function readName(ElfSymbol $symbol): string
    {
        if ($symbol->name === '') {
            throw CorruptedBinaryException::becauseImageIsMalformed(\sprintf(
                'The dynamic symbol #%d has no name',
                $symbol->index,
            ));
        }

        return $symbol->name;
    }

    private function readEndianness(bool $isLittleEndian): Endianness
    {
        return $isLittleEndian ? Endianness::Little : Endianness::Big;
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    private function getMetadata(TypedStream $stream): ElfImage
    {
        return $this->metadata[$stream] ??= $this->read($stream);
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    private function read(TypedStream $stream): ElfImage
    {
        $stream->offset = 0;
        $identity = $stream->read(ElfIdentity::SIZE);

        if (!\str_starts_with($identity, ElfIdentity::MAGIC)) {
            throw CorruptedBinaryException::becauseImageIsMalformed('Not an ELF image');
        }

        $header = $this->readHeader($stream, $identity);
        $stream = $stream->withByteOrder($this->readEndianness($header->littleEndian));

        $sections = $this->readSections($stream, $header);
        $versions = $this->readVersions($stream, $sections);

        return new ElfImage(
            header: $header,
            hasInterpreter: $this->hasInterpreter($stream, $header),
            sections: $sections,
            needed: $this->readNeeded($stream, $header, $sections),
            symbols: $this->readSymbols($stream, $header, $sections, $versions),
            versions: $versions,
        );
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readHeader(TypedStream $stream, string $identity): ElfHeader
    {
        $is64bit = \ord($identity[4]) === ElfIdentity::ELFCLASS64;
        $littleEndian = \ord($identity[5]) === ElfIdentity::ELFDATA2LSB;

        /** @var int<0, 255> $osAbi */
        $osAbi = \ord($identity[7]);

        $stream = $stream->withByteOrder($this->readEndianness($littleEndian));
        $stream->offset = 16;

        $type = $stream->uint16();
        $machine = $stream->uint16();
        $stream->offset += 4; // e_version
        $stream->offset += $is64bit ? 8 : 4; // e_entry
        $programOffset = $stream->address($is64bit);
        $sectionOffset = $stream->address($is64bit);
        $flags = $stream->uint32();
        $stream->offset += 2; // e_ehsize
        $programSize = $stream->uint16();
        $programCount = $stream->uint16();
        $sectionSize = $stream->uint16();
        $sectionCount = $stream->uint16();
        $sectionNamesIndex = $stream->uint16();

        return new ElfHeader(
            is64bit: $is64bit,
            littleEndian: $littleEndian,
            osAbi: $osAbi,
            type: $type,
            machine: $machine,
            flags: $flags,
            programOffset: $programOffset,
            programSize: $programSize,
            programCount: $programCount,
            sectionOffset: $sectionOffset,
            sectionSize: $sectionSize,
            sectionCount: $sectionCount,
            sectionNamesIndex: $sectionNamesIndex,
        );
    }

    /**
     * Looks the program header table up for a `PT_INTERP` entry, which only
     * a program carries.
     *
     * @throws ReflectionException in case of the image cannot be read
     */
    private function hasInterpreter(TypedStream $stream, ElfHeader $header): bool
    {
        if ($header->programOffset <= 0 || $header->programCount === 0 || $header->programSize < 4) {
            return false;
        }

        $stream->offset = $header->programOffset;
        $entries = $stream->slice($header->programCount * $header->programSize);

        for ($i = 0; $i < $header->programCount; ++$i) {
            $entries->offset = $i * $header->programSize;

            if ($entries->uint32() === ElfProgramType::PT_INTERP) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<ElfSection>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readSections(TypedStream $stream, ElfHeader $header): array
    {
        if ($header->sectionOffset <= 0 || $header->sectionCount === 0 || $header->sectionSize === 0) {
            throw SymbolTableNotFoundException::becauseTableIsAbsent(
                'The ELF image contains no section header table',
            );
        }

        $stream->offset = $header->sectionOffset;
        $entries = $stream->slice($header->sectionCount * $header->sectionSize);

        $is64bit = $header->is64bit;
        $result = [];

        for ($i = 0; $i < $header->sectionCount; ++$i) {
            $entries->offset = $i * $header->sectionSize;

            $result[] = new ElfSection(
                nameOffset: $entries->uint32(),
                type: $entries->uint32(),
                flags: $entries->address($is64bit),
                address: $entries->address($is64bit),
                offset: $entries->address($is64bit),
                size: $entries->address($is64bit),
                link: $entries->uint32(),
                info: $entries->uint32(),
                // sh_addralign precedes sh_entsize.
                entrySize: $entries->lookahead(static function (TypedStream $stream) use ($is64bit): int {
                    $stream->offset += $is64bit ? 8 : 4;

                    return $stream->address($is64bit);
                }),
            );
        }

        return $result;
    }

    /**
     * Reads the `DT_NEEDED` entries of the dynamic section.
     *
     * @param list<ElfSection> $sections
     * @return list<non-empty-string>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readNeeded(TypedStream $stream, ElfHeader $header, array $sections): array
    {
        $section = $this->findSection($sections, ElfSectionType::SHT_DYNAMIC);
        $strings = $this->openStrings($stream, $sections, $section);

        if ($section === null || $strings === null) {
            return [];
        }

        $stream->offset = $section->offset;
        $entries = $stream->slice($section->size);

        $is64bit = $header->is64bit;
        $size = $is64bit ? 16 : 8;
        $count = \intdiv($section->size, $size);

        $result = [];

        for ($i = 0; $i < $count; ++$i) {
            $entries->offset = $i * $size;

            $tag = $entries->address($is64bit);

            if ($tag === ElfDynamicTag::DT_NULL) {
                break;
            }

            $value = $entries->address($is64bit);

            if ($tag !== ElfDynamicTag::DT_NEEDED) {
                continue;
            }

            $strings->offset = $value;
            $name = $strings->string();

            if ($name !== '') {
                $result[] = $name;
            }
        }

        return $result;
    }

    /**
     * Reads the `.gnu.version_r` section, mapping a version index onto
     * the version it denotes.
     *
     * @param list<ElfSection> $sections
     * @return array<int, ElfVersion>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readVersions(TypedStream $stream, array $sections): array
    {
        $section = $this->findSection($sections, ElfSectionType::SHT_GNU_VERNEED);
        $strings = $this->openStrings($stream, $sections, $section);

        if ($section === null || $strings === null || $section->size === 0) {
            return [];
        }

        $stream->offset = $section->offset;
        $entries = $stream->slice($section->size);

        $result = [];
        $offset = 0;

        // The sh_info field holds the number of Elf_Verneed entries.
        for ($i = 0; $i < $section->info; ++$i) {
            $entries->offset = $offset + 2;

            $count = $entries->uint16();
            $strings->offset = $entries->uint32();
            $library = $strings->string();
            $auxiliary = $offset + $entries->uint32();
            $next = $entries->uint32();

            for ($j = 0; $j < $count; ++$j) {
                $entries->offset = $auxiliary + 6;

                $index = $entries->uint16();
                $strings->offset = $entries->uint32();
                $name = $strings->string();
                $auxiliaryNext = $entries->uint32();

                if ($name !== '' && $library !== '') {
                    $result[$index] = new ElfVersion(
                        name: $name,
                        library: $library,
                        index: $index,
                    );
                }

                if ($auxiliaryNext === 0) {
                    break;
                }

                $auxiliary += $auxiliaryNext;
            }

            if ($next === 0) {
                break;
            }

            $offset += $next;
        }

        return $result;
    }

    /**
     * Reads the dynamic symbol table together with the version index of
     * every entry.
     *
     * @param list<ElfSection> $sections
     * @param array<int, ElfVersion> $versions
     * @return list<ElfSymbol>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readSymbols(TypedStream $stream, ElfHeader $header, array $sections, array $versions): array
    {
        $section = $this->findSection($sections, ElfSectionType::SHT_DYNSYM);

        if ($section === null) {
            throw SymbolTableNotFoundException::becauseTableIsAbsent(
                'The ELF image contains no dynamic symbol table (.dynsym)',
            );
        }

        $strings = $this->openStrings($stream, $sections, $section);

        if ($strings === null) {
            throw CorruptedBinaryException::becauseImageIsMalformed(\sprintf(
                'The .dynsym section references a non-existent string table #%d',
                $section->link,
            ));
        }

        $is64bit = $header->is64bit;
        $size = $section->entrySize > 0 ? $section->entrySize : ($is64bit ? 24 : 16);

        $stream->offset = $section->offset;
        $entries = $stream->slice($section->size);

        $indices = null;
        $versionIndices = $this->findSection($sections, ElfSectionType::SHT_GNU_VERSYM);

        if ($versionIndices !== null && $versions !== []) {
            $stream->offset = $versionIndices->offset;
            $indices = $stream->slice($versionIndices->size);
        }

        $count = \intdiv($section->size, $size);
        $result = [];

        for ($index = 0; $index < $count; ++$index) {
            $entries->offset = $index * $size;

            $nameOffset = $entries->uint32();

            // Elf64_Sym moves st_value and st_size behind the three fields
            // that follow the name.
            if ($is64bit) {
                $information = $entries->uint8();
                $other = $entries->uint8();
                $sectionIndex = $entries->uint16();
                $address = $entries->uint64();
                $symbolSize = $entries->uint64();
            } else {
                $address = $entries->uint32();
                $symbolSize = $entries->uint32();
                $information = $entries->uint8();
                $other = $entries->uint8();
                $sectionIndex = $entries->uint16();
            }

            $strings->offset = $nameOffset;

            /** @var int<0, 15> $type */
            $type = $information & 0x0F;
            /** @var int<0, 15> $binding */
            $binding = $information >> 4;
            /** @var int<0, 3> $visibility */
            $visibility = $other & 0x03;

            $result[] = new ElfSymbol(
                name: $strings->string(),
                address: $address,
                size: $symbolSize,
                type: $type,
                binding: $binding,
                visibility: $visibility,
                section: $sectionIndex,
                index: $index,
                version: $this->findVersion($versions, $indices, $index),
            );
        }

        return $result;
    }

    /**
     * @param array<int, ElfVersion> $versions
     * @throws ReflectionException in case of the image cannot be read
     */
    private function findVersion(array $versions, ?TypedStream $indices, int $symbol): ?ElfVersion
    {
        if ($indices === null) {
            return null;
        }

        $indices->offset = $symbol * 2;
        $index = $indices->uint16() & ElfSymbolVersion::MASK_INDEX;

        if ($index <= ElfSymbolVersion::VER_NDX_GLOBAL) {
            return null;
        }

        return $versions[$index] ?? null;
    }

    /**
     * Opens the string table a section refers to through its
     * `sh_link` field.
     *
     * @param list<ElfSection> $sections
     * @throws ReflectionException in case of the image cannot be read
     */
    private function openStrings(TypedStream $stream, array $sections, ?ElfSection $section): ?TypedStream
    {
        $strings = $section === null ? null : ($sections[$section->link] ?? null);

        if ($strings === null || $strings->size === 0) {
            return null;
        }

        $stream->offset = $strings->offset;

        return $stream->slice($strings->size);
    }

    /**
     * @param list<ElfSection> $sections
     */
    private function findSection(array $sections, int $type): ?ElfSection
    {
        foreach ($sections as $section) {
            if ($section->type === $type) {
                return $section;
            }
        }

        return null;
    }
}
