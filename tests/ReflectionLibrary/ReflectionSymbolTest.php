<?php

declare(strict_types=1);

namespace FFI\Reflection\Tests\ReflectionLibrary;

use FFI\Reflection\ReflectionLibrary;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\ElfReflectionExportSymbol;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\MachOReflectionExportSymbol;
use FFI\Reflection\ReflectionLibrary\Reader\PE\PeReflectionExportSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionExportSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolAbi;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolResolution;
use FFI\Reflection\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ReflectionExportSymbol::class)]
#[CoversClass(ElfReflectionExportSymbol::class)]
#[CoversClass(PeReflectionExportSymbol::class)]
#[CoversClass(MachOReflectionExportSymbol::class)]
final class ReflectionSymbolTest extends TestCase
{
    #[DataProvider('librariesDataProvider')]
    public function testSymbolsBelongToAFormatSpecificClass(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertContains($symbol::class, [
                ElfReflectionExportSymbol::class,
                PeReflectionExportSymbol::class,
                MachOReflectionExportSymbol::class,
            ]);
        }
    }

    #[DataProvider('librariesDataProvider')]
    public function testNamesAreNotEmpty(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertNotSame('', $symbol->getNativeName());
            self::assertSame($symbol->getNativeName(), $symbol->getNativeName());
            self::assertSame($symbol->getName(), $symbol->getName());
        }
    }

    #[DataProvider('librariesDataProvider')]
    public function testSymbolsAreConvertibleToString(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertNotSame('', (string) $symbol);
        }
    }

    #[DataProvider('librariesDataProvider')]
    public function testNamesAreUnique(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        $names = \array_map(
            static fn(ReflectionExportSymbol $symbol): ?string => $symbol->getNativeName(),
            $library->getSymbols(),
        );

        self::assertNotEmpty($names);
        self::assertSame($names, \array_values(\array_unique($names)));
    }

    #[DataProvider('elfLibrariesDataProvider')]
    public function testElfSymbolsAreDefinedAndVisible(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertInstanceOf(ElfReflectionExportSymbol::class, $symbol);

            self::assertNotSame(ReflectionSymbolResolution::Weak, $symbol->getBinding());
            self::assertTrue($symbol->getVisibility()->isExported());
            self::assertGreaterThanOrEqual(0, $symbol->getIndex());
        }
    }

    #[DataProvider('peLibrariesDataProvider')]
    public function testPeSymbolsCarryAnOrdinalAndABody(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertInstanceOf(PeReflectionExportSymbol::class, $symbol);

            self::assertSame($symbol->getAddress() === null, $symbol->getForwarder() !== null);
            self::assertSame($symbol->getForwarder() !== null, $symbol->isForwarded());

            self::assertSame($symbol->getOrdinal(), $symbol->getOrdinal());
            self::assertSame($symbol->getAddress(), $symbol->getAddress());
            self::assertSame($symbol->getForwarder(), $symbol->getForwarder());
            self::assertTrue($symbol->isResolvable());
        }
    }

    #[DataProvider('peLibrariesDataProvider')]
    public function testPeAbiIsOnlyKnownForThirtyTwoBitImages(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);
        $is64bit = \str_contains($pathname, 'aarch64') || \str_contains($pathname, 'x86_64');

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertSame($symbol->getAbi(), $symbol->getAbi());

            if ($is64bit) {
                self::assertSame(ReflectionSymbolAbi::Default, $symbol->getAbi());

                continue;
            }

            self::assertSame(ReflectionSymbolAbi::CDecl, $symbol->getAbi());
        }
    }

    #[DataProvider('elfLibrariesDataProvider')]
    #[DataProvider('machOLibrariesDataProvider')]
    public function testAbiIsUnknownOutsideOfPe(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertSame(ReflectionSymbolAbi::Default, $symbol->getAbi());
        }
    }

    #[DataProvider('machOLibrariesDataProvider')]
    public function testMachOSymbolsAreDefinedByTheImage(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertInstanceOf(MachOReflectionExportSymbol::class, $symbol);

            self::assertSame($symbol->getAddress(), $symbol->getAddress());
            self::assertSame($symbol->getForwarder(), $symbol->getForwarder());
            self::assertSame($symbol->getBinding() === ReflectionSymbolResolution::Weak, $symbol->isWeak());

            self::assertSame($symbol->getAddress() === null, $symbol->isForwarded());

            self::assertNotNull($symbol->getNativeName());
            self::assertFalse($symbol->isAnonymous());

            self::assertContains($symbol->getName(), [
                $symbol->getNativeName(),
                \substr($symbol->getNativeName(), 1),
            ]);
        }
    }

    #[DataProvider('machOLibrariesDataProvider')]
    public function testMachOPublicNameStripsTheLeadingUnderscore(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        $mangled = \array_filter(
            $library->getSymbols(),
            static fn(ReflectionExportSymbol $symbol): bool
                => \str_starts_with((string) $symbol->getNativeName(), '_'),
        );

        self::assertNotEmpty($mangled);

        foreach ($mangled as $symbol) {
            self::assertSame(\substr((string) $symbol->getNativeName(), 1), $symbol->getName());
        }
    }
}
