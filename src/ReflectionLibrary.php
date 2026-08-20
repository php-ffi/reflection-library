<?php

declare(strict_types=1);

namespace FFI\Reflection;

use FFI\Location\Locator;
use FFI\Reflection\Exception\ImportNotFoundException;
use FFI\Reflection\Exception\LibraryNotFoundException;
use FFI\Reflection\Exception\ReflectionException;
use FFI\Reflection\Exception\SymbolNotFoundException;
use FFI\Reflection\ReflectionLibrary\ArchitectureInterface;
use FFI\Reflection\ReflectionLibrary\Endianness;
use FFI\Reflection\ReflectionLibrary\Printer\ReflectionPrinter;
use FFI\Reflection\ReflectionLibrary\Reader\CommonLibraryInfo;
use FFI\Reflection\ReflectionLibrary\Reader\LibraryReaderFactory;
use FFI\Reflection\ReflectionLibrary\Reader\LibraryReaderInterface;
use FFI\Reflection\ReflectionLibrary\ReflectionExportSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionImport;
use FFI\Reflection\ReflectionLibrary\ReflectionLibraryType;
use FFI\Reflection\ReflectionLibrary\Stream\Stream;
use FFI\Reflection\ReflectionLibrary\Stream\StreamInterface;
use FFI\Reflection\ReflectionLibrary\Stream\TypedStream;

/**
 * Reflection over a binary shared library, i.e. the entrypoint of the whole
 * package.
 *
 * An instance describes a single file: the machine it is built for, the
 * libraries it needs and the symbols it offers. Together that is everything a
 * consumer has to know before writing an {@see \FFI::cdef()} declaration
 * against the library.
 */
final class ReflectionLibrary implements \Reflector
{
    /**
     * Gets the pathname of the binray file being reflected.
     *
     * @var non-empty-string
     */
    private readonly string $filename;

    private readonly TypedStream $stream;

    /**
     * Driver reading the binary format of this library.
     */
    private readonly LibraryReaderInterface $reader;

    /**
     * Traits every supported format describes a file with, i.e. the ones
     * a loader needs before it can make sense of anything else in it.
     */
    private CommonLibraryInfo $metadata {
        /**
         * @throws ReflectionException
         */
        get => $this->metadata
            ??= $this->reader->getCommonInfo($this->stream);
    }

    /**
     * External libraries this one depends on, together with the symbols it
     * takes from each of them.
     *
     * A symbol the library does not define itself is only reachable once its
     * provider is loaded too, which is what makes this list worth reading.
     *
     * @var list<ReflectionImport>
     */
    private array $imports {
        /**
         * @throws ReflectionException
         */
        get => $this->imports ??= \iterator_to_array(
            iterator: $this->reader->getImports($this->stream),
            preserve_keys: false,
        );
    }

    /**
     * Imports indexed by the lowercased name of the library.
     *
     * @var array<non-empty-string, ReflectionImport>
     */
    private array $importByLowerNames {
        /**
         * @throws ReflectionException
         */
        get => $this->importByLowerNames
            ??= ReflectionImport::groupByName($this->imports, lowercase: true);
    }

    /**
     * Imports indexed by the name of the library exactly as the image
     * records it, so that `example` and `EXAMPLE` stay two different
     * entries. The case-insensitive lookup has a table of its own,
     * {@see $importByLowerNames}.
     *
     * @var array<non-empty-string, ReflectionImport>
     */
    private array $importByNames {
        /**
         * @throws ReflectionException
         */
        get => $this->importByNames
            ??= ReflectionImport::groupByName($this->imports, lowercase: false);
    }

    /**
     * Symbols the library offers to its consumers, i.e. its public
     * interface.
     *
     * These are the names a declaration is allowed to mention: anything else
     * the library holds is invisible to a loader.
     *
     * @var list<ReflectionExportSymbol>
     */
    private array $symbols {
        /**
         * @throws ReflectionException
         */
        get => $this->symbols ??= \iterator_to_array(
            iterator: $this->reader->getSymbols($this->stream),
            preserve_keys: false,
        );
    }

    /**
     * Exported symbols indexed by the name a caller spells out.
     *
     * @var array<non-empty-string, ReflectionExportSymbol>
     */
    private array $symbolByNames {
        /**
         * @throws ReflectionException
         */
        get => $this->symbolByNames
            ??= ReflectionExportSymbol::groupByName($this->symbols);
    }

    /**
     * @throws ReflectionException in case of library cannot be loaded
     */
    public function __construct(string $library)
    {
        $this->filename = self::getPathname($library);
        $this->stream = self::createStream($this->filename);
        $this->reader = self::createReader($this->stream, $this->filename);
    }

    /**
     * @param non-empty-string $pathname
     * @throws ReflectionException
     */
    private static function createReader(StreamInterface $stream, string $pathname): LibraryReaderInterface
    {
        $factory = LibraryReaderFactory::createDefault();

        return $factory->createFromStream($stream, $pathname);
    }

    /**
     * @param non-empty-string $pathname
     * @throws ReflectionException
     */
    private static function createStream(string $pathname): TypedStream
    {
        $stream = Stream::createFromPathname($pathname);

        return new TypedStream($stream);
    }

    /**
     * @return non-empty-string
     * @throws ReflectionException in case of library not found
     */
    private static function getPathname(string $library): string
    {
        $pathname = Locator::pathname($library);

        if ($pathname === null || $pathname === '') {
            throw LibraryNotFoundException::becauseNameIsNotResolvable($library);
        }

        return $pathname;
    }

    /**
     * Gets the pathname of the file being reflected.
     *
     * The constructor takes the name of a library rather than a path and
     * resolves it the way the platform would, so this is where that name
     * ended up.
     *
     * @return non-empty-string
     */
    public function getFileName(): string
    {
        return $this->filename;
    }

    /**
     * Gets the width of a machine word of the library, i.e. the number of
     * bits an address occupies.
     *
     * A library addressing memory more widely than the running process does
     * cannot be loaded into it at all, however well its symbols match.
     *
     * @return int<8, max>
     * @throws ReflectionException in case of the library cannot be read
     */
    public function getBits(): int
    {
        return $this->metadata->addressSize * 8;
    }

    /**
     * Order in which the library stores the bytes of a multi-byte value.
     *
     * It follows from the architecture the library is built for and decides
     * how every number kept in the file is to be read.
     *
     * @throws ReflectionException in case of the library cannot be read
     */
    public function getEndianness(): Endianness
    {
        return $this->metadata->endianness;
    }

    /**
     * Instruction set the library is compiled for.
     *
     * Machine code of one family means nothing to a processor of another, so
     * together with {@see $bits} this is what decides whether the file can
     * run on the current machine at all.
     *
     * Equals {@see null} in case of the file names an architecture this
     * package does not know.
     *
     * @throws ReflectionException in case of the library cannot be read
     */
    public function getArchitecture(): ?ArchitectureInterface
    {
        return $this->metadata->architecture;
    }

    /**
     * Kind of object the file holds.
     *
     * Every supported format describes more than shared libraries, and a
     * file is reflected whether or not it is one, so this is worth checking
     * before relying on the symbols being loadable.
     *
     * @throws ReflectionException in case of the library cannot be read
     */
    public function getType(): ReflectionLibraryType
    {
        return $this->metadata->type;
    }

    /**
     * Gets the external libraries this one depends on, together with the
     * symbols it takes from each of them.
     *
     * A symbol the library does not define itself is only reachable once
     * its provider is loaded too.
     *
     * @return list<ReflectionImport>
     * @throws ReflectionException in case of the library cannot be read
     */
    public function getImports(): array
    {
        return $this->imports;
    }

    /**
     * Gets the imported library with the given name.
     *
     * @param non-empty-string $name
     * @throws ReflectionException in case of no such library is imported
     */
    public function getImport(string $name, bool $caseInsensitive = false): ReflectionImport
    {
        return $this->findImport($name, $caseInsensitive)
            ?? throw ImportNotFoundException::becauseLibraryDoesNotDependOnIt($name, $this->filename);
    }

    /**
     * Gets the imported library with the given name or {@see null} in case of
     * this library does not depend on it.
     *
     * @param non-empty-string $name
     * @param bool $caseInsensitive some file systems are case-insensitive, so
     *        to make it easier to find a specific import, you should pass the
     *        {@see true} explicitly
     * @throws ReflectionException in case of the library cannot be read
     */
    public function findImport(string $name, bool $caseInsensitive = false): ?ReflectionImport
    {
        if ($caseInsensitive) {
            return $this->importByLowerNames[\strtolower($name)] ?? null;
        }

        return $this->importByNames[$name] ?? null;
    }

    /**
     * Gets whether the library depends on an external one with the given name.
     *
     * @param non-empty-string $name
     * @param bool $caseInsensitive some file systems are case-insensitive, so
     *        to make it easier to find a specific import, you should pass the
     *        {@see true} explicitly
     * @throws ReflectionException in case of the library cannot be read
     */
    public function hasImport(string $name, bool $caseInsensitive = false): bool
    {
        if ($caseInsensitive) {
            return isset($this->importByLowerNames[\strtolower($name)]);
        }

        return isset($this->importByNames[$name]);
    }

    /**
     * Gets the symbols the library offers to its consumers, i.e. its public
     * interface.
     *
     * These are the names a declaration is allowed to mention: anything
     * else the library holds is invisible to a loader.
     *
     * @return list<ReflectionExportSymbol>
     * @throws ReflectionException in case of the library cannot be read
     */
    public function getSymbols(): array
    {
        return $this->symbols;
    }

    /**
     * Gets the exported symbol with the given name.
     *
     * @param non-empty-string $name
     * @throws ReflectionException in case of no such symbol is exported
     */
    public function getSymbol(string $name): ReflectionExportSymbol
    {
        return $this->findSymbol($name)
            ?? throw SymbolNotFoundException::becauseNameIsNotOffered($name, $this->filename);
    }

    /**
     * Gets the exported symbol with the given name or {@see null} in case of
     * the library does not offer it.
     *
     * @param non-empty-string $name
     * @throws ReflectionException in case of the library cannot be read
     */
    public function findSymbol(string $name): ?ReflectionExportSymbol
    {
        return $this->symbolByNames[$name] ?? null;
    }

    /**
     * Gets whether the library offers a symbol with the given name.
     *
     * @param non-empty-string $name
     * @throws ReflectionException in case of the library cannot be read
     */
    public function hasSymbol(string $name): bool
    {
        return isset($this->symbolByNames[$name]);
    }

    /**
     * Gets the library as a block of text, the way {@see \ReflectionClass}
     * and {@see \ReflectionExtension} render theirs.
     *
     * @throws ReflectionException in case of the library cannot be read
     */
    public function __toString(): string
    {
        return new ReflectionPrinter()->print($this);
    }
}
