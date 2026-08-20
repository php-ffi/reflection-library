<?php

declare(strict_types=1);

namespace FFI\Reflection\Tests\ReflectionLibrary;

use FFI\Reflection\Exception\SymbolNotFoundException;
use FFI\Reflection\ReflectionLibrary;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\ElfReflectionImport;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\ElfReflectionImportSymbol;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\MachOReflectionImport;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\MachOReflectionImportSymbol;
use FFI\Reflection\ReflectionLibrary\Reader\PE\PeReflectionImport;
use FFI\Reflection\ReflectionLibrary\Reader\PE\PeReflectionImportSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionImport;
use FFI\Reflection\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * An import is an external library the image depends on, together with the
 * symbols it takes from that library. The symbols the image offers in return
 * are covered by {@see ReflectionSymbolTest}.
 */
#[CoversClass(ReflectionImport::class)]
#[CoversClass(ElfReflectionImport::class)]
#[CoversClass(PeReflectionImport::class)]
#[CoversClass(MachOReflectionImport::class)]
final class ReflectionImportTest extends TestCase
{
    /**
     * A name that no fixture can possibly carry.
     */
    private const string UNKNOWN_NAME = 'FFI\Reflection\Tests::unknown';

    #[DataProvider('librariesDataProvider')]
    public function testImportsBelongToAFormatSpecificClass(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $import) {
            self::assertContains($import::class, [
                ElfReflectionImport::class,
                PeReflectionImport::class,
                MachOReflectionImport::class,
            ]);
        }
    }

    #[DataProvider('librariesDataProvider')]
    public function testNamesAreNotEmpty(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $import) {
            self::assertNotSame('', $import->name);
            self::assertSame($import->name, (string) $import);
        }
    }

    #[DataProvider('librariesDataProvider')]
    public function testAccessorsMatchTheProperties(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $import) {
            self::assertSame($import->name, $import->getName());
            self::assertSame($import->isOptional, $import->isOptional());
            self::assertSame($import->symbols, $import->getSymbols());
        }
    }

    #[DataProvider('librariesDataProvider')]
    public function testEverySymbolOfAnImportIsResolvable(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $import) {
            foreach ($import->getSymbols() as $symbol) {
                if ($symbol->name === null) {
                    continue;
                }

                self::assertTrue($import->hasSymbol($symbol->name));
                self::assertSame($symbol, $import->findSymbol($symbol->name));
                self::assertSame($symbol, $import->getSymbol($symbol->name));
            }
        }
    }

    #[DataProvider('librariesDataProvider')]
    public function testLookupsReturnNothingOnUnknownSymbol(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $import) {
            self::assertFalse($import->hasSymbol(self::UNKNOWN_NAME));
            self::assertNull($import->findSymbol(self::UNKNOWN_NAME));
        }
    }

    #[DataProvider('librariesDataProvider')]
    public function testGetSymbolThrowsOnUnknownName(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        $this->expectException(SymbolNotFoundException::class);

        $library->getImports()[0]->getSymbol(self::UNKNOWN_NAME);
    }

    #[DataProvider('elfLibrariesDataProvider')]
    public function testElfImportsAreNeverOptional(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $import) {
            self::assertInstanceOf(ElfReflectionImport::class, $import);

            // ELF has no per-entry flag making a DT_NEEDED library optional.
            self::assertFalse($import->isOptional);
        }
    }

    /**
     * ELF attributes a symbol to a library through its version requirement,
     * so an import holding symbols has to declare the versions they need.
     */
    #[DataProvider('elfLibrariesDataProvider')]
    public function testElfSymbolsMatchTheDeclaredVersions(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $import) {
            self::assertInstanceOf(ElfReflectionImport::class, $import);
            self::assertSame($import->versions, $import->getVersions());

            if ($import->getSymbols() === []) {
                continue;
            }

            self::assertNotEmpty($import->versions);

            foreach ($import->getSymbols() as $symbol) {
                self::assertInstanceOf(ElfReflectionImportSymbol::class, $symbol);
                self::assertContains($symbol->version, $import->versions);
            }
        }
    }

    #[DataProvider('peLibrariesDataProvider')]
    public function testPeImportsAreNamedAfterADynamicLibrary(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $import) {
            self::assertInstanceOf(PeReflectionImport::class, $import);

            self::assertStringEndsWith('.dll', \strtolower($import->name));

            // A delay-loaded library is the only optional import of a PE
            // image, so the two say the same thing.
            self::assertSame($import->isOptional, $import->isDelayLoaded());

            // None of the fixtures delays anything.
            self::assertFalse($import->isDelayLoaded());
        }
    }

    /**
     * A PE image records no ordinal for a symbol it takes by name, and one
     * taken by ordinal alone never reaches the reflection API.
     */
    #[DataProvider('peLibrariesDataProvider')]
    public function testPeImportedSymbolsCarryNoOrdinal(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $import) {
            foreach ($import->getSymbols() as $symbol) {
                self::assertInstanceOf(PeReflectionImportSymbol::class, $symbol);
                self::assertNull($symbol->ordinal);
            }
        }
    }

    #[DataProvider('machOLibrariesDataProvider')]
    public function testMachOImportsCarryVersionsAndAnOrdinal(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $index => $import) {
            self::assertInstanceOf(MachOReflectionImport::class, $import);

            // Ordinals are one-based and follow the order of the load commands.
            self::assertSame($index + 1, $import->ordinal);
            self::assertSame($import->ordinal, $import->getOrdinal());

            self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $import->currentVersion);
            self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $import->compatibilityVersion);

            self::assertSame($import->kind, $import->getKind());
            self::assertSame($import->kind->isOptional(), $import->isOptional);

            foreach ($import->getSymbols() as $symbol) {
                self::assertInstanceOf(MachOReflectionImportSymbol::class, $symbol);
                self::assertSame($import->ordinal, $symbol->libraryOrdinal);
            }
        }
    }
}
