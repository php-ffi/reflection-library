<?php

declare(strict_types=1);

namespace FFI\Reflection\Tests;

use FFI\Reflection\Exception\ImportNotFoundException;
use FFI\Reflection\Exception\LibraryNotFoundException;
use FFI\Reflection\Exception\ReflectionException;
use FFI\Reflection\Exception\SymbolNotFoundException;
use FFI\Reflection\Exception\UnsupportedFormatException;
use FFI\Reflection\ReflectionLibrary;
use FFI\Reflection\ReflectionLibrary\Architecture;
use FFI\Reflection\ReflectionLibrary\Endianness;
use FFI\Reflection\ReflectionLibrary\Printer\ReflectionPrinter;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\ElfLibraryReader;
use FFI\Reflection\ReflectionLibrary\Reader\LibraryReaderFactory;
use FFI\Reflection\ReflectionLibrary\Reader\LibraryReaderInterface;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\MachOLibraryReader;
use FFI\Reflection\ReflectionLibrary\Reader\PE\PeLibraryReader;
use FFI\Reflection\ReflectionLibrary\ReflectionLibraryType;
use FFI\Reflection\ReflectionLibrary\Stream\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Behaviour of the facade itself: resolving the library, selecting a driver
 * and looking imported libraries and exported symbols up by name. What an
 * individual entry carries is covered by
 * {@see ReflectionLibrary\ReflectionImportTest} and
 * {@see ReflectionLibrary\ReflectionSymbolTest}.
 */
#[CoversClass(ReflectionLibrary::class)]
#[CoversClass(LibraryReaderFactory::class)]
#[CoversClass(ReflectionPrinter::class)]
final class ReflectionLibraryTest extends TestCase
{
    /**
     * A name that no fixture can possibly carry.
     */
    private const string UNKNOWN_NAME = 'FFI\Reflection\Tests::unknown';

    #[DataProvider('librariesDataProvider')]
    public function testFileNamePointsToTheLibrary(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertSame(\realpath($pathname), $library->getFileName());
    }

    /**
     * Every fixture is laid out as `<Platform>/<architecture>/<file>`, so the
     * directory it sits in is what the image has to report.
     *
     * @return iterable<non-empty-string, array{non-empty-string, int<8, max>, Architecture}>
     */
    public static function identifiedLibrariesDataProvider(): iterable
    {
        $architectures = [
            'x86' => [32, Architecture::X86],
            'x86_64' => [64, Architecture::Amd64],
            'arm' => [32, Architecture::Arm],
            'armv6' => [32, Architecture::Arm],
            'armv7' => [32, Architecture::Arm],
            'aarch64' => [64, Architecture::Arm64],
            'ppc64' => [64, Architecture::PowerPc64],
            'riscv64' => [64, Architecture::RiscV],
        ];

        foreach (self::librariesDataProvider() as $name => [$pathname]) {
            $directory = \explode('/', $name)[1] ?? '';

            [$bits, $architecture] = $architectures[$directory]
                ?? throw new \LogicException(\sprintf(
                    'The "%s" fixture sits in an architecture directory the test knows nothing about',
                    $name,
                ));

            yield $name => [$pathname, $bits, $architecture];
        }
    }

    /**
     * @param non-empty-string $pathname
     * @param int<8, max> $bits
     */
    #[DataProvider('identifiedLibrariesDataProvider')]
    public function testTheImageIsIdentifiedByTheArchitectureItSitsIn(
        string $pathname,
        int $bits,
        Architecture $architecture,
    ): void {
        $library = new ReflectionLibrary($pathname);

        self::assertSame($bits, $library->getBits());
        self::assertSame($architecture, $library->getArchitecture());
    }

    /**
     * Every architecture the fixtures are built for runs little endian these
     * days, including the PowerPC one.
     *
     * @param non-empty-string $pathname
     */
    #[DataProvider('librariesDataProvider')]
    public function testEveryFixtureIsLittleEndian(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertSame(Endianness::Little, $library->getEndianness());
    }

    /**
     * @param non-empty-string $pathname
     */
    #[DataProvider('librariesDataProvider')]
    public function testEveryFixtureIsALibraryRatherThanAProgram(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertSame(ReflectionLibraryType::Library, $library->getType());
    }

    /**
     * The width of the address is what the reader lays the whole image out
     * by, so it has to agree with the size of the addresses the symbols
     * report.
     *
     * @param non-empty-string $pathname
     */
    #[DataProvider('librariesDataProvider')]
    public function testTheWidthOfAWordBoundsTheAddressesOfTheSymbols(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        $limit = 2 ** $library->getBits();

        foreach ($library->getSymbols() as $symbol) {
            $address = $symbol->getAddress();

            if ($address !== null) {
                self::assertLessThan($limit, $address);
            }
        }
    }

    /**
     * The rendering repeats the shape the reflection API of PHP uses, so
     * every section it opens is closed and counted.
     *
     * @param non-empty-string $pathname
     */
    #[DataProvider('librariesDataProvider')]
    public function testTheRenderingListsEveryImportAndSymbol(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);
        $result = (string) $library;

        self::assertStringStartsWith('Library [ ', $result);
        self::assertStringEndsWith("\n}", $result);
        self::assertStringContainsString($library->getFileName(), $result);

        self::assertStringContainsString(
            \sprintf('- Imports [%d] {', \count($library->getImports())),
            $result,
        );
        self::assertStringContainsString(
            \sprintf('- Symbols [%d] {', \count($library->getSymbols())),
            $result,
        );

        foreach ($library->getImports() as $import) {
            self::assertStringContainsString('Import [ ' . $import->getName(), $result);
        }
    }

    #[DataProvider('librariesDataProvider')]
    public function testImportsAreNotEmpty(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());
    }

    #[DataProvider('librariesDataProvider')]
    public function testSymbolsAreNotEmpty(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());
    }

    /**
     * Both lists are read through the same stream, so a driver that failed to
     * rewind between the two calls would corrupt the second one.
     */
    #[DataProvider('librariesDataProvider')]
    public function testImportsAndSymbolsDoNotDisturbEachOther(string $pathname): void
    {
        $expected = new ReflectionLibrary($pathname);
        $actual = new ReflectionLibrary($pathname);

        // The same data, read in the opposite order.
        $symbols = $actual->getSymbols();
        $imports = $actual->getImports();

        self::assertEquals($expected->getImports(), $imports);
        self::assertEquals($expected->getSymbols(), $symbols);
    }

    #[DataProvider('librariesDataProvider')]
    public function testImportsAreReadOnce(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertSame($library->getImports(), $library->getImports());
    }

    #[DataProvider('librariesDataProvider')]
    public function testSymbolsAreReadOnce(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertSame($library->getSymbols(), $library->getSymbols());
    }

    #[DataProvider('librariesDataProvider')]
    public function testEveryImportIsResolvable(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $import) {
            self::assertTrue($library->hasImport($import->name));
            self::assertSame($import, $library->findImport($import->name));
            self::assertSame($import, $library->getImport($import->name));
        }
    }

    #[DataProvider('librariesDataProvider')]
    public function testEverySymbolIsResolvable(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            if ($symbol->name === null) {
                continue;
            }

            self::assertTrue($library->hasSymbol($symbol->name));
            self::assertSame($symbol, $library->findSymbol($symbol->name));
            self::assertSame($symbol, $library->getSymbol($symbol->name));
        }
    }

    #[DataProvider('librariesDataProvider')]
    public function testLookupsReturnNothingOnUnknownName(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertFalse($library->hasImport(self::UNKNOWN_NAME));
        self::assertNull($library->findImport(self::UNKNOWN_NAME));
        self::assertFalse($library->hasSymbol(self::UNKNOWN_NAME));
        self::assertNull($library->findSymbol(self::UNKNOWN_NAME));
    }

    #[DataProvider('librariesDataProvider')]
    public function testGetImportThrowsOnUnknownName(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        $this->expectException(ImportNotFoundException::class);

        $library->getImport(self::UNKNOWN_NAME);
    }

    #[DataProvider('librariesDataProvider')]
    public function testGetSymbolThrowsOnUnknownName(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        $this->expectException(SymbolNotFoundException::class);

        $library->getSymbol(self::UNKNOWN_NAME);
    }

    /**
     * @param non-empty-string $pathname
     */
    #[DataProvider('elfLibrariesDataProvider')]
    public function testElfLibrariesAreReadByTheElfDriver(string $pathname): void
    {
        self::assertInstanceOf(ElfLibraryReader::class, self::createReader($pathname));
    }

    /**
     * @param non-empty-string $pathname
     */
    #[DataProvider('peLibrariesDataProvider')]
    public function testPeLibrariesAreReadByThePeDriver(string $pathname): void
    {
        self::assertInstanceOf(PeLibraryReader::class, self::createReader($pathname));
    }

    /**
     * @param non-empty-string $pathname
     */
    #[DataProvider('machOLibrariesDataProvider')]
    public function testMachOLibrariesAreReadByTheMachODriver(string $pathname): void
    {
        self::assertInstanceOf(MachOLibraryReader::class, self::createReader($pathname));
    }

    public function testConstructorThrowsOnMissingLibrary(): void
    {
        $this->expectException(LibraryNotFoundException::class);

        new ReflectionLibrary('this-library-does-not-exist.so');
    }

    public function testConstructorThrowsOnUnsupportedFormat(): void
    {
        $this->expectException(UnsupportedFormatException::class);

        new ReflectionLibrary(__DIR__ . '/../composer.json');
    }

    /**
     * @param non-empty-string $pathname
     */
    #[DataProvider('librariesDataProvider')]
    public function testDefaultFactoryResolvesADriverForEveryFixture(string $pathname): void
    {
        $reader = self::createReader($pathname);

        self::assertContains($reader::class, [
            ElfLibraryReader::class,
            PeLibraryReader::class,
            MachOLibraryReader::class,
        ]);
    }

    /**
     * @param non-empty-string $pathname
     */
    #[DataProvider('librariesDataProvider')]
    public function testEmptyFactorySupportsNothing(string $pathname): void
    {
        $this->expectException(UnsupportedFormatException::class);

        new LibraryReaderFactory()
            ->createFromStream(Stream::createFromPathname($pathname), $pathname);
    }

    /**
     * A driver has to probe the signature from the beginning of the stream
     * rather than from wherever the previous one left the position.
     *
     * @param non-empty-string $pathname
     */
    #[DataProvider('librariesDataProvider')]
    public function testDriversProbeIndependentlyOfTheStreamPosition(string $pathname): void
    {
        $stream = Stream::createFromPathname($pathname);
        $stream->offset = 512;

        $reader = LibraryReaderFactory::createDefault()->createFromStream($stream, $pathname);

        self::assertSame(self::createReader($pathname)::class, $reader::class);
    }

    /**
     * @param non-empty-string $pathname
     * @throws ReflectionException
     */
    private static function createReader(string $pathname): LibraryReaderInterface
    {
        return LibraryReaderFactory::createDefault()
            ->createFromStream(Stream::createFromPathname($pathname), $pathname);
    }
}
