<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO;

use FFI\Reflection\Exception\CorruptedBinaryException;
use FFI\Reflection\Exception\ReflectionException;
use FFI\Reflection\Exception\SymbolTableNotFoundException;
use FFI\Reflection\Exception\UnsupportedFormatException;
use FFI\Reflection\ReflectionLibrary\Architecture;
use FFI\Reflection\ReflectionLibrary\Endianness;
use FFI\Reflection\ReflectionLibrary\Reader\CommonLibraryInfo;
use FFI\Reflection\ReflectionLibrary\Reader\LibraryReaderInterface;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata\CpuType;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata\Dylib;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata\ExportEntry;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata\LoadCommandType;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata\MachHeader;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata\MachOFileType;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata\MachOImage;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata\MachOMagic;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata\NameListEntry;
use FFI\Reflection\ReflectionLibrary\ReflectionExportSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionImport;
use FFI\Reflection\ReflectionLibrary\ReflectionLibraryType;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbol;
use FFI\Reflection\ReflectionLibrary\Stream\StreamInterface;
use FFI\Reflection\ReflectionLibrary\Stream\TypedStream;

/**
 * Reads Mach-O images (`*.dylib`, `*.bundle`).
 *
 * Imports are the undefined entries of the symbol table, each carrying the
 * ordinal of the dylib load command providing it, and exports are the
 * terminal nodes of the dyld export trie.
 *
 * Universal ("fat") archives are not supported yet: such a file is a
 * container of several images rather than an image itself.
 *
 * An implementation based on {@link https://github.com/ircmaxell/php-object-symbolresolver/tree/master/lib/MachO}
 */
final readonly class MachOLibraryReader implements LibraryReaderInterface
{
    /**
     * Upper bound on the depth of the export trie, protecting against a
     * cycle in a malformed image.
     */
    private const int MAX_TRIE_DEPTH = 128;

    /**
     * @var \WeakMap<TypedStream, MachOImage>
     */
    private \WeakMap $metadata;

    public function __construct()
    {
        $this->metadata = new \WeakMap();
    }

    public function supports(StreamInterface $stream): bool
    {
        $stream->offset = 0;

        // A universal ("fat") archive opens with a magic of its own and holds
        // several images instead of being one, so this driver does not
        // recognise it.
        return \in_array($stream->read(4), [
            MachOMagic::MAGIC_32,
            MachOMagic::MAGIC_32_BE,
            MachOMagic::MAGIC_64,
            MachOMagic::MAGIC_64_BE,
        ], true);
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    public function getCommonInfo(TypedStream $stream): CommonLibraryInfo
    {
        $header = $this->getMetadata($stream)->header;

        return new CommonLibraryInfo(
            addressSize: $header->is64bit ? 8 : 4,
            endianness: Endianness::fromBool($header->littleEndian),
            architecture: $this->readArchitecture($header->cpuType),
            type: $this->readObjectType($header->fileType),
        );
    }

    private function readArchitecture(int $cpuType): ?Architecture
    {
        return match ($cpuType) {
            CpuType::CPU_TYPE_X86 => Architecture::X86,
            CpuType::CPU_TYPE_X86_64 => Architecture::Amd64,
            CpuType::CPU_TYPE_ARM => Architecture::Arm,
            CpuType::CPU_TYPE_ARM64 => Architecture::Arm64,
            CpuType::CPU_TYPE_POWERPC => Architecture::PowerPc,
            CpuType::CPU_TYPE_POWERPC64 => Architecture::PowerPc64,
            default => null,
        };
    }

    private function readObjectType(int $fileType): ReflectionLibraryType
    {
        return match ($fileType) {
            MachOFileType::MH_EXECUTE => ReflectionLibraryType::Executable,
            // A bundle is loaded the way a library is, it only cannot be
            // linked against beforehand.
            MachOFileType::MH_DYLIB, MachOFileType::MH_BUNDLE => ReflectionLibraryType::Library,
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

        foreach ($image->symbols as $entry) {
            if ($entry->isDebug() || !$entry->isUndefined() || !$entry->isExternal() || $entry->name === '') {
                continue;
            }

            // A flat namespace image records no ordinals at all, so every
            // symbol is reported as a dynamic lookup.
            $ordinal = $image->header->isTwoLevel()
                ? $entry->getLibraryOrdinal()
                : MachOReflectionImportSymbol::DYNAMIC_LOOKUP_ORDINAL;

            $symbols[$ordinal][] = new MachOReflectionImportSymbol(
                nativeName: $entry->name,
                name: $this->createPublicName($entry->name),
                isOptional: $entry->isWeakReference(),
                libraryOrdinal: $ordinal,
            );
        }

        foreach ($image->dylibs as $dylib) {
            yield new MachOReflectionImport(
                name: $dylib->name,
                symbols: $symbols[$dylib->ordinal] ?? [],
                kind: $dylib->kind,
                currentVersion: $dylib->currentVersion,
                compatibilityVersion: $dylib->compatibilityVersion,
                ordinal: $dylib->ordinal,
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

        foreach ($image->exports as $entry) {
            $forwarder = null;

            if ($entry->reexportOrdinal !== null) {
                $library = $image->findDylibByOrdinal($entry->reexportOrdinal);
                $forwarder = ($library === null ? '?' : $library->name)
                    . '.' . ($entry->reexportName ?? $entry->name);
            }

            yield new MachOReflectionExportSymbol(
                nativeName: $entry->name,
                name: $this->createPublicName($entry->name),
                address: $entry->address,
                forwarder: $forwarder,
                isWeak: $entry->isWeak(),
            );
        }
    }

    /**
     * Builds the name a caller spells out to reach the symbol, i.e. the one
     * without the underscore Darwin prefixes every C symbol with. Not every
     * symbol carries the prefix.
     *
     * @param non-empty-string $nativeName
     * @return non-empty-string|null
     */
    private function createPublicName(string $nativeName): ?string
    {
        $result = \str_starts_with($nativeName, '_')
            ? \substr($nativeName, 1)
            : $nativeName;

        return $result !== '' && ReflectionSymbol::isResolvableName($result)
            ? $result
            : null;
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    private function getMetadata(TypedStream $stream): MachOImage
    {
        return $this->metadata[$stream] ??= $this->read($stream);
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    private function read(TypedStream $stream): MachOImage
    {
        $stream->offset = 0;
        $magic = $stream->read(4);

        if (\in_array($magic, MachOMagic::MAGIC_FAT, true)) {
            throw UnsupportedFormatException::becauseShapeOfFileIsNotSupported(
                'Universal (fat) Mach-O archives are not supported: '
                . 'the file is a container of several images',
            );
        }

        $is64bit = $magic === MachOMagic::MAGIC_64 || $magic === MachOMagic::MAGIC_64_BE;
        $littleEndian = $magic === MachOMagic::MAGIC_64 || $magic === MachOMagic::MAGIC_32;

        if (!$is64bit && $magic !== MachOMagic::MAGIC_32 && $magic !== MachOMagic::MAGIC_32_BE) {
            throw CorruptedBinaryException::becauseImageIsMalformed('Not a Mach-O image');
        }

        $stream = $stream->withByteOrder(Endianness::fromBool($littleEndian));
        $header = $this->readHeader($stream, $is64bit, $littleEndian);

        $dylibs = [];
        $symbols = [];
        $exports = [];

        $offset = $is64bit ? MachOMagic::HEADER_SIZE_64 : MachOMagic::HEADER_SIZE_32;

        for ($i = 0; $i < $header->commandCount; ++$i) {
            $stream->offset = $offset;

            $type = $stream->uint32();
            $size = $stream->uint32();

            if ($size < 8) {
                throw CorruptedBinaryException::becauseImageIsMalformed(\sprintf(
                    'Load command #%d has an invalid size of %d bytes',
                    $i,
                    $size,
                ));
            }

            $kind = $this->readDylibKind($type);

            if ($kind !== null) {
                $stream->offset = $offset;
                $dylibs[] = $this->readDylib($stream->slice($size), $kind, \count($dylibs) + 1);
            } elseif ($type === LoadCommandType::LC_SYMTAB) {
                $stream->offset = $offset + LoadCommandType::PAYLOAD_OFFSET;
                $symbols = $this->readSymbols($stream, $is64bit);
            } elseif ($type === LoadCommandType::LC_DYLD_INFO || $type === LoadCommandType::LC_DYLD_INFO_ONLY) {
                // The export trie is the last pair of the command.
                $stream->offset = $offset + LoadCommandType::DYLD_INFO_EXPORT_OFFSET;
                $exports = $this->readExports($stream);
            } elseif ($type === LoadCommandType::LC_DYLD_EXPORTS_TRIE) {
                $stream->offset = $offset + LoadCommandType::PAYLOAD_OFFSET;
                $exports = $this->readExports($stream);
            }

            $offset += $size;
        }

        if ($symbols === [] && $exports === []) {
            throw SymbolTableNotFoundException::becauseTableIsAbsent(
                'The Mach-O image contains neither a symbol table nor an export trie',
            );
        }

        // A stripped image carries no trie, leaving the symbol table as the
        // only source of exports.
        if ($exports === []) {
            $exports = $this->readExportsFromSymbols($symbols);
        }

        return new MachOImage(
            header: $header,
            dylibs: \array_values(\array_filter($dylibs)),
            symbols: $symbols,
            exports: $exports,
        );
    }

    /**
     * Maps the `cmd` field of a load command onto the kind of dependency it
     * declares, or gets {@see null} in case of the command declares none.
     */
    private function readDylibKind(int $command): ?DylibKind
    {
        return match ($command) {
            LoadCommandType::LC_LOAD_DYLIB => DylibKind::Load,
            LoadCommandType::LC_LAZY_LOAD_DYLIB => DylibKind::LazyLoad,
            LoadCommandType::LC_LOAD_WEAK_DYLIB => DylibKind::LoadWeak,
            LoadCommandType::LC_REEXPORT_DYLIB => DylibKind::Reexport,
            LoadCommandType::LC_LOAD_UPWARD_DYLIB => DylibKind::LoadUpward,
            default => null,
        };
    }

    /**
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readHeader(TypedStream $stream, bool $is64bit, bool $littleEndian): MachHeader
    {
        $stream->offset = 4;

        return new MachHeader(
            is64bit: $is64bit,
            littleEndian: $littleEndian,
            cpuType: $stream->uint32(),
            cpuSubType: $stream->uint32(),
            fileType: $stream->uint32(),
            commandCount: $stream->uint32(),
            // sizeofcmds precedes the flags.
            flags: $stream->lookahead(static function (TypedStream $stream): int {
                $stream->offset += 4;

                return $stream->uint32();
            }),
        );
    }

    /**
     * Dependency declared by a single dylib load command.
     *
     * An install name is what the loader looks a library up by, so a command
     * carrying none declares no usable dependency and gets {@see null}.
     *
     * @param int<1, max> $ordinal
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readDylib(TypedStream $command, DylibKind $kind, int $ordinal): ?Dylib
    {
        $command->offset = 8;

        // An lc_str is an offset relative to the load command.
        $nameOffset = $command->uint32();
        $command->offset += 4; // timestamp
        $current = $this->readVersion($command->uint32());
        $compatibility = $this->readVersion($command->uint32());

        // An offset landing inside the fields of the command carries no
        // string at all: reading it would spell the raw bytes of the header
        // out as a name.
        if ($nameOffset < LoadCommandType::DYLIB_NAME_OFFSET) {
            return null;
        }

        $command->offset = $nameOffset;
        $name = $command->string();

        if ($name === '') {
            return null;
        }

        return new Dylib(
            name: $name,
            kind: $kind,
            currentVersion: $current,
            compatibilityVersion: $compatibility,
            ordinal: $ordinal,
        );
    }

    /**
     * Reads the symbol table declared by an `LC_SYMTAB` load command, whose
     * fields the stream is expected to be positioned at.
     *
     * @return list<NameListEntry>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readSymbols(TypedStream $stream, bool $is64bit): array
    {
        $offset = $stream->uint32();
        $count = $stream->uint32();
        $stringOffset = $stream->uint32();
        $stringSize = $stream->uint32();

        if ($count === 0) {
            return [];
        }

        $size = $is64bit ? 16 : 12;

        $stream->offset = $stringOffset;
        $names = $stream->slice($stringSize);

        $stream->offset = $offset;
        $entries = $stream->slice($count * $size);

        $result = [];

        for ($i = 0; $i < $count; ++$i) {
            $entries->offset = $i * $size;

            $nameOffset = $entries->uint32();
            $type = $entries->uint8();
            $section = $entries->uint8();
            $description = $entries->uint16();
            $address = $entries->address($is64bit);

            $names->offset = $nameOffset;

            $result[] = new NameListEntry(
                name: $names->string(),
                type: $type,
                section: $section,
                description: $description,
                address: $address,
            );
        }

        return $result;
    }

    /**
     * Reads the export trie declared by a load command, whose offset and
     * size the stream is expected to be positioned at.
     *
     * @return list<ExportEntry>
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readExports(TypedStream $stream): array
    {
        $offset = $stream->uint32();
        $size = $stream->uint32();

        if ($offset === 0 || $size === 0) {
            return [];
        }

        $stream->offset = $offset;
        $trie = $stream->slice($size);

        $result = [];
        $this->walkTrie($trie, $size, 0, '', $result, 0);

        return $result;
    }

    /**
     * Terminals of the dyld export trie below the given node, i.e. the
     * symbols whose name the path down to them spells out.
     *
     * @param list<ExportEntry> $result
     * @throws ReflectionException in case of the image cannot be read
     */
    private function walkTrie(
        TypedStream $trie,
        int $size,
        int $node,
        string $prefix,
        array &$result,
        int $depth,
    ): void {
        if ($depth > self::MAX_TRIE_DEPTH || $node < 0 || $node >= $size) {
            return;
        }

        $trie->offset = $node;
        $terminal = $trie->uleb128();

        if ($terminal > 0 && $prefix !== '') {
            $result[] = $this->readTrieTerminal($trie, $prefix);
        }

        $trie->offset = $node + $this->getUleb128Size($terminal) + $terminal;
        $children = $trie->uint8();

        for ($i = 0; $i < $children; ++$i) {
            $label = $trie->string();
            $child = $trie->uleb128();
            $next = $trie->offset;

            if ($child !== $node) {
                $this->walkTrie($trie, $size, $child, $prefix . $label, $result, $depth + 1);
            }

            $trie->offset = $next;
        }
    }

    /**
     * @param non-empty-string $name
     * @throws ReflectionException in case of the image cannot be read
     */
    private function readTrieTerminal(TypedStream $trie, string $name): ExportEntry
    {
        $flags = $trie->uleb128();

        if (($flags & ExportEntry::FLAG_REEXPORT) !== 0) {
            // A library ordinal never exceeds a byte, so a wider value marks
            // a malformed trie rather than a real dependency.
            /** @var int<0, 255> $ordinal */
            $ordinal = \min(0xFF, $trie->uleb128());
            $target = $trie->string();

            return new ExportEntry(
                name: $name,
                address: null,
                flags: $flags,
                reexportOrdinal: $ordinal,
                reexportName: $target === '' ? null : $target,
            );
        }

        // A symbol resolved at load time carries the stub address first and
        // the resolver one second.
        $address = $trie->uleb128();

        if (($flags & ExportEntry::FLAG_STUB_AND_RESOLVER) !== 0) {
            $trie->uleb128();
        }

        return new ExportEntry(
            name: $name,
            address: $address,
            flags: $flags,
            reexportOrdinal: null,
            reexportName: null,
        );
    }

    /**
     * Gets the number of bytes the given value occupies when encoded as
     * ULEB128.
     */
    private function getUleb128Size(int $value): int
    {
        $size = 1;

        while ($value >= 0x80) {
            $value >>= 7;
            ++$size;
        }

        return $size;
    }

    /**
     * @param list<NameListEntry> $symbols
     * @return list<ExportEntry>
     */
    private function readExportsFromSymbols(array $symbols): array
    {
        $result = [];

        foreach ($symbols as $entry) {
            if ($entry->isDebug() || $entry->isUndefined() || !$entry->isExternal() || $entry->name === '') {
                continue;
            }

            $result[] = new ExportEntry(
                name: $entry->name,
                address: $entry->address,
                flags: 0,
                reexportOrdinal: null,
                reexportName: null,
            );
        }

        return $result;
    }

    /**
     * Formats a packed `dylib.current_version` value.
     *
     * @return non-empty-string
     */
    private function readVersion(int $version): string
    {
        return \sprintf('%d.%d.%d', $version >> 16, $version >> 8 & 0xFF, $version & 0xFF);
    }
}
