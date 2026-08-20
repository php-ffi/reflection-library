<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Printer;

use FFI\Reflection\Exception\ReflectionException;
use FFI\Reflection\ReflectionLibrary;
use FFI\Reflection\ReflectionLibrary\Endianness;
use FFI\Reflection\ReflectionLibrary\ReflectionExportSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionImport;
use FFI\Reflection\ReflectionLibrary\ReflectionImportSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolResolution;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolVisibility;

/**
 * Formats a library the way the reflection API of PHP formats a class or an
 * extension, i.e. as a block of text meant to be read rather than parsed.
 */
final readonly class ReflectionPrinter
{
    /**
     * Width of a single level of nesting, matching the one the reflection
     * API of PHP uses.
     */
    private const string INDENT = '  ';

    /**
     * @throws ReflectionException in case of the library cannot be read
     */
    public function print(ReflectionLibrary $library): string
    {
        $header = \sprintf(
            'Library [ %s%dbit %s ] {',
            $this->printModifiers($this->getLibraryModifiers($library)),
            $library->bits,
            \basename($library->getFileName()),
        );

        return $header . "\n"
            . $this->indent(1) . '@@ ' . $library->getFileName() . "\n"
            . "\n"
            . $this->printImports($library) . "\n"
            . $this->printSymbols($library)
            . '}';
    }

    /**
     * @throws ReflectionException in case of the library cannot be read
     */
    private function printImports(ReflectionLibrary $library): string
    {
        $result = [];

        foreach ($library->imports as $import) {
            $result[] = $this->printImport($import);
        }

        // A block spanning several lines is separated from its siblings,
        // the way the reflection API of PHP separates methods.
        return $this->printSection('Imports', 1, $result, "\n\n");
    }

    /**
     * Wraps the rendered entries into a counted block.
     *
     * @param non-empty-string $title
     * @param int<0, max> $depth
     * @param list<string> $entries
     */
    private function printSection(
        string $title,
        int $depth,
        array $entries,
        string $separator = "\n",
    ): string {
        $result = \sprintf('%s- %s [%d] {', $this->indent($depth), $title, \count($entries));

        if ($entries !== []) {
            $result .= "\n" . \implode($separator, $entries);
        }

        return $result . "\n" . $this->indent($depth) . "}\n";
    }

    private function printImport(ReflectionImport $import): string
    {
        $modifiers = $import->isOptional ? ['optional'] : [];

        $result = \sprintf(
            '%sImport [ %s%s ] {',
            $this->indent(2),
            $this->printModifiers($modifiers),
            $import->name,
        );

        $symbols = [];

        foreach ($import->symbols as $symbol) {
            $symbols[] = $this->printImportSymbol($symbol);
        }

        return $result . "\n\n" . $this->printSection('Symbols', 3, $symbols)
            . $this->indent(2) . '}';
    }

    private function printImportSymbol(ReflectionImportSymbol $symbol): string
    {
        $modifiers = $symbol->isOptional ? ['optional'] : [];

        return \sprintf(
            '%sSymbol [ %s%s ]',
            $this->indent(4),
            $this->printModifiers($modifiers),
            $symbol,
        );
    }

    /**
     * @throws ReflectionException in case of the library cannot be read
     */
    private function printSymbols(ReflectionLibrary $library): string
    {
        $result = [];

        foreach ($library->symbols as $symbol) {
            $result[] = $this->printExportSymbol($symbol);
        }

        return $this->printSection('Symbols', 1, $result);
    }

    private function printExportSymbol(ReflectionExportSymbol $symbol): string
    {
        $result = \sprintf(
            '%sSymbol [ %s%s ]',
            $this->indent(2),
            $this->printModifiers($this->getSymbolModifiers($symbol)),
            $symbol,
        );

        if ($symbol->forwarder !== null) {
            return $result . ' { ' . $symbol->forwarder . ' }';
        }

        if ($symbol->address !== null) {
            return $result . \sprintf(' { 0x%08X }', $symbol->address);
        }

        return $result;
    }

    /**
     * @return list<non-empty-string>
     * @throws ReflectionException in case of the library cannot be read
     */
    private function getLibraryModifiers(ReflectionLibrary $library): array
    {
        $result = [\strtolower($library->type->name)];

        if ($library->architecture !== null) {
            $result[] = \strtolower($library->architecture->name);
        }

        $result[] = $library->endianness === Endianness::Little
            ? 'little-endian'
            : 'big-endian';

        return $result;
    }

    /**
     * @return list<non-empty-string>
     */
    private function getSymbolModifiers(ReflectionExportSymbol $symbol): array
    {
        $result = [];

        if ($symbol->forwarder !== null) {
            $result[] = 'forwarded';
        }

        if ($symbol->binding === ReflectionSymbolResolution::Weak) {
            $result[] = 'weak';
        }

        if ($symbol->visibility !== ReflectionSymbolVisibility::Public) {
            $result[] = \strtolower($symbol->visibility->name);
        }

        return $result;
    }

    /**
     * @param list<non-empty-string> $modifiers
     */
    private function printModifiers(array $modifiers): string
    {
        if ($modifiers === []) {
            return '';
        }

        return '<' . \implode('> <', $modifiers) . '> ';
    }

    /**
     * @param int<0, max> $depth
     */
    private function indent(int $depth): string
    {
        return \str_repeat(self::INDENT, $depth);
    }
}
