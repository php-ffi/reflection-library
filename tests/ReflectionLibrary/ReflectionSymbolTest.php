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
            self::assertNotSame('', $symbol->nativeName);
            self::assertSame($symbol->nativeName, $symbol->getNativeName());
            self::assertSame($symbol->name, $symbol->getName());
        }
    }

    #[DataProvider('librariesDataProvider')]
    public function testAccessorsMatchTheProperties(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertSame($symbol->address, $symbol->getAddress());
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

    /**
     * The public interface of a library is what the exported names spell out,
     * so the same name must not be offered twice.
     */
    #[DataProvider('librariesDataProvider')]
    public function testNamesAreUnique(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        $names = \array_map(
            static fn(ReflectionExportSymbol $symbol): ?string => $symbol->nativeName,
            $library->getSymbols(),
        );

        self::assertNotEmpty($names);
        self::assertSame($names, \array_values(\array_unique($names)));
    }

    /**
     * An exported symbol is defined by the image itself, so unlike an
     * imported one it has an address of its own.
     */
    #[DataProvider('elfLibrariesDataProvider')]
    public function testElfSymbolsAreDefinedAndVisible(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertInstanceOf(ElfReflectionExportSymbol::class, $symbol);

            self::assertNotSame(ReflectionSymbolResolution::Weak, $symbol->binding);
            self::assertTrue($symbol->visibility->isExported());
            self::assertGreaterThanOrEqual(0, $symbol->index);
        }
    }

    #[DataProvider('elfLibrariesDataProvider')]
    public function testElfAccessorsMatchTheProperties(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertInstanceOf(ElfReflectionExportSymbol::class, $symbol);

            self::assertSame($symbol->type, $symbol->getType());
            self::assertSame($symbol->binding, $symbol->getBinding());
            self::assertSame($symbol->visibility, $symbol->getVisibility());
            self::assertSame($symbol->address, $symbol->getAddress());
            self::assertSame($symbol->size, $symbol->getSize());
            self::assertSame($symbol->index, $symbol->getIndex());
        }
    }

    #[DataProvider('peLibrariesDataProvider')]
    public function testPeSymbolsCarryAnOrdinalAndABody(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertInstanceOf(PeReflectionExportSymbol::class, $symbol);

            // The slot holds either code or the name of the symbol taking
            // the call over, never both and never neither.
            self::assertSame($symbol->address === null, $symbol->forwarder !== null);
            self::assertSame($symbol->forwarder !== null, $symbol->isForwarded());

            self::assertSame($symbol->ordinal, $symbol->getOrdinal());
            self::assertSame($symbol->address, $symbol->getAddress());
            self::assertSame($symbol->forwarder, $symbol->getForwarder());
            // None of the fixtures exports anything without a name, so the
            // placeholder naming is covered by PEPeLibraryReaderTest alone.
            self::assertTrue($symbol->isResolvable());
        }
    }

    /**
     * Only a 32-bit PE image spells the calling convention out, and it does
     * so inside the decoration of the name. A 64-bit one offers a single
     * convention, which is the default one.
     */
    #[DataProvider('peLibrariesDataProvider')]
    public function testPeAbiIsOnlyKnownForThirtyTwoBitImages(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);
        $is64bit = \str_contains($pathname, 'aarch64') || \str_contains($pathname, 'x86_64');

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertSame($symbol->abi, $symbol->getAbi());

            if ($is64bit) {
                self::assertSame(ReflectionSymbolAbi::Default, $symbol->abi);

                continue;
            }

            // None of the fixtures decorates its exports, which is what a
            // cdecl function produces.
            self::assertSame(ReflectionSymbolAbi::CDecl, $symbol->abi);
        }
    }

    /**
     * ELF and Mach-O offer a single calling convention, which is the
     * default one.
     */
    #[DataProvider('elfLibrariesDataProvider')]
    #[DataProvider('machOLibrariesDataProvider')]
    public function testAbiIsUnknownOutsideOfPe(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertSame(ReflectionSymbolAbi::Default, $symbol->abi);
        }
    }

    #[DataProvider('machOLibrariesDataProvider')]
    public function testMachOSymbolsAreDefinedByTheImage(string $pathname): void
    {
        $library = new ReflectionLibrary($pathname);

        self::assertNotEmpty($library->getSymbols());

        foreach ($library->getSymbols() as $symbol) {
            self::assertInstanceOf(MachOReflectionExportSymbol::class, $symbol);

            self::assertSame($symbol->address, $symbol->getAddress());
            self::assertSame($symbol->forwarder, $symbol->getForwarder());
            self::assertSame($symbol->binding === ReflectionSymbolResolution::Weak, $symbol->isWeak());

            // A symbol the image defines itself holds a body of its own,
            // while a re-exported one only points at another library.
            self::assertSame($symbol->address === null, $symbol->isForwarded());

            // Mach-O always names what it offers.
            self::assertNotNull($symbol->nativeName);
            self::assertFalse($symbol->isAnonymous());

            // Stripping the mangling removes at most one leading underscore.
            // Note that not every symbol carries it, the dyld stub binder
            // for example is stored under its bare name.
            self::assertContains($symbol->name, [
                $symbol->nativeName,
                \substr($symbol->nativeName, 1),
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
                => \str_starts_with((string) $symbol->nativeName, '_'),
        );

        self::assertNotEmpty($mangled);

        foreach ($mangled as $symbol) {
            self::assertSame(\substr((string) $symbol->nativeName, 1), $symbol->name);
        }
    }
}
