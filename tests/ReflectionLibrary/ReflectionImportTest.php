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

#[CoversClass(ReflectionImport::class)]
#[CoversClass(ElfReflectionImport::class)]
#[CoversClass(PeReflectionImport::class)]
#[CoversClass(MachOReflectionImport::class)]
final class ReflectionImportTest extends TestCase
{
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
            self::assertNotSame('', $import->getName());
            self::assertSame($import->getName(), (string) $import);
        }
    }

    #[DataProvider('librariesDataProvider')]
    public function testEverySymbolOfAnImportIsResolvable(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $import) {
            foreach ($import->getSymbols() as $symbol) {
                if ($symbol->getName() === null) {
                    continue;
                }

                self::assertTrue($import->hasSymbol($symbol->getName()));
                self::assertSame($symbol, $import->findSymbol($symbol->getName()));
                self::assertSame($symbol, $import->getSymbol($symbol->getName()));
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

            self::assertFalse($import->isOptional());
        }
    }

    #[DataProvider('elfLibrariesDataProvider')]
    public function testElfSymbolsMatchTheDeclaredVersions(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $import) {
            self::assertInstanceOf(ElfReflectionImport::class, $import);
            self::assertSame($import->getVersions(), $import->getVersions());

            if ($import->getSymbols() === []) {
                continue;
            }

            self::assertNotEmpty($import->getVersions());

            foreach ($import->getSymbols() as $symbol) {
                self::assertInstanceOf(ElfReflectionImportSymbol::class, $symbol);
                self::assertContains($symbol->getVersion(), $import->getVersions());
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

            self::assertStringEndsWith('.dll', \strtolower($import->getName()));

            self::assertSame($import->isOptional(), $import->isDelayLoaded());

            self::assertFalse($import->isDelayLoaded());
        }
    }

    #[DataProvider('peLibrariesDataProvider')]
    public function testPeImportedSymbolsCarryNoOrdinal(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getImports());

        foreach ($library->getImports() as $import) {
            foreach ($import->getSymbols() as $symbol) {
                self::assertInstanceOf(PeReflectionImportSymbol::class, $symbol);
                self::assertNull($symbol->getOrdinal());
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

            self::assertSame($index + 1, $import->getOrdinal());
            self::assertSame($import->getOrdinal(), $import->getOrdinal());

            self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $import->getCurrentVersion());
            self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $import->getCompatibilityVersion());

            self::assertSame($import->getKind(), $import->getKind());
            self::assertSame($import->getKind()->isOptional(), $import->isOptional());

            foreach ($import->getSymbols() as $symbol) {
                self::assertInstanceOf(MachOReflectionImportSymbol::class, $symbol);
                self::assertSame($import->getOrdinal(), $symbol->getLibraryOrdinal());
            }
        }
    }
}
