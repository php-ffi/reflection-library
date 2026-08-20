# FFI Reflection Library

<p align="center">
    <a href="https://packagist.org/packages/ffi/reflection-library"><img src="https://poser.pugx.org/ffi/reflection-library/require/php?style=for-the-badge" alt="PHP 8.4+"></a>
    <a href="https://packagist.org/packages/ffi/reflection-library"><img src="https://poser.pugx.org/ffi/reflection-library/version?style=for-the-badge" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/ffi/reflection-library"><img src="https://poser.pugx.org/ffi/reflection-library/v/unstable?style=for-the-badge" alt="Latest Unstable Version"></a>
    <a href="https://packagist.org/packages/ffi/reflection-library"><img src="https://poser.pugx.org/ffi/reflection-library/downloads?style=for-the-badge" alt="Total Downloads"></a>
    <a href="https://raw.githubusercontent.com/php-ffi/reflection-library/master/LICENSE.md"><img src="https://poser.pugx.org/ffi/reflection-library/license?style=for-the-badge" alt="License MIT"></a>
</p>
<p align="center">
    <a href="https://github.com/php-ffi/reflection-library/actions"><img src="https://github.com/php-ffi/reflection-library/workflows/build/badge.svg"></a>
</p>

## Requirements

- PHP ^8.4

## Installation

Library is available as Сomposer repository and can be installed using the
following command in the root of your project as a dev-dependency.

```sh
$ composer require ffi/reflection-library --dev
```

## Usage

A `ReflectionLibrary` is created from the name of a library, which is resolved
the way the platform would resolve it, or from a pathname.

```php
use FFI\Reflection\ReflectionLibrary;

$library = new ReflectionLibrary('sqlite3');

$library->getFileName(); // "/usr/lib/x86_64-linux-gnu/libsqlite3.so.0"
```

### Common Library Info

```php
$library->getBits();         // 64
$library->getEndianness();   // Endianness::Little
$library->getArchitecture(); // Architecture::Amd64
$library->getType();         // ReflectionLibraryType::Library
```

The same values are available as properties: `$library->bits`, `$library->endianness`
and so on, throughout the whole API.

### Symbols

`getSymbols()` returns the public interface of the library, i.e. the names an
`FFI::cdef()` declaration is allowed to mention.

```php
foreach ($library->getSymbols() as $symbol) {
    $symbol->getName();    // "sqlite3_open"
    $symbol->getAddress(); // 0x1F3E
}

$library->hasSymbol('sqlite3_open');  // true
$library->getSymbol('sqlite3_open');  // ReflectionExportSymbol
$library->findSymbol('does_not_exist'); // null
```

### Imports

Every import carries the symbols taken from that particular library.

```php
foreach ($library->getImports() as $import) {
    $import->getName();       // "libc.so.6"
    $import->isOptional();    // false

    foreach ($import->getSymbols() as $symbol) {
        $symbol->getName();    // "printf"
        $symbol->getVersion(); // "GLIBC_2.2.5" or null
    }
}

$library->hasImport('KERNEL32.dll', caseInsensitive: true); // true
```

### Format Specifics

Both symbols and imports are format-specific subclasses carrying whatever the
format records beyond the common set, so an ELF export also tells its type,
binding and size, while a PE one tells its ordinal and calling convention.

```php
use FFI\Reflection\ReflectionLibrary\Reader\ELF\ElfReflectionExportSymbol;

$symbol = $library->getSymbol('sqlite3_open');

if ($symbol instanceof ElfReflectionExportSymbol) {
    $symbol->getType(); // SymbolType::Func
    $symbol->getSize(); // 132
}
```

### Dumping

Casting a library to a string renders it the way `ReflectionClass` and
`ReflectionExtension` render theirs.

```php
echo $library;
```

```
Library [ <library> <amd64> <little-endian> 64bit libsqlite3.so.0 ] {
  @@ /usr/lib/x86_64-linux-gnu/libsqlite3.so.0

  - Imports [3] {
    Import [ libm.so.6 ] {

      - Symbols [23] {
        Symbol [ log10@GLIBC_2.2.5 ]
      }
    }
  }

  - Symbols [68] {
    Symbol [ sqlite3_open ] { 0x0000F7F1 }
  }
}
```

### Errors

Every failure is a subclass of `FFI\Reflection\Exception\ReflectionException`,
which in turn extends the `ReflectionException` of PHP.

```php
use FFI\Reflection\Exception\LibraryNotFoundException;

try {
    $library = new ReflectionLibrary('does-not-exist.so');
} catch (LibraryNotFoundException $e) {
    // ...
}
```
