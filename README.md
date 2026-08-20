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

Library is available as Composer repository and can be installed using the
following command in the root of your project as a dev-dependency.

```sh
$ composer require ffi/reflection-library --dev
```

## Usage

A `ReflectionLibrary` is created from a pathname or from the file name of a
library, spelled the way an `FFI::cdef()` argument is.

```php
use FFI\Reflection\ReflectionLibrary;

$library = new ReflectionLibrary('libsqlite3.so.0');

$library->getFileName(); // "/usr/lib/x86_64-linux-gnu/libsqlite3.so.0"
```

### Common Library Info

```php
$library->getBits();         // 64
$library->getEndianness();   // Endianness::Little
$library->getArchitecture(); // Architecture::Amd64
$library->getType();         // ReflectionLibraryType::Library
```

### Export Symbols

Export symbols are the functions, variables, structures, and so on that a 
library exports externally. These are (almost) public elements of a specific library.

The `getSymbols()` returns the public interface of the library, i.e. the names
an `FFI::cdef()` declaration is allowed to mention.

```php
foreach ($library->getSymbols() as $symbol) {
    $symbol->getName();       // "sqlite3_open"
    $symbol->getNativeName(); // "sqlite3_open", decorations and all
    $symbol->getAddress();    // 63473
}

$library->hasSymbol('sqlite3_open');    // true
$library->getSymbol('sqlite3_open');    // ReflectionExportSymbol
$library->findSymbol('does_not_exist'); // null
```

### Library Imports

An imports are a list of a library's dependencies. Each dependency, in addition 
to its name (imported library name), has its own list of symbols that the 
library loads.

```php
foreach ($library->getImports() as $import) {
    $import->getName();    // "libc.so.6"
    $import->isOptional(); // false

    foreach ($import->getSymbols() as $symbol) {
        $symbol->getName();    // "printf"
        $symbol->getVersion(); // "GLIBC_2.2.5" or null
    }
}

$library->hasImport('KERNEL32.dll', caseInsensitive: true); // true
$library->getImport('libc.so.6');                           // ReflectionImport
$library->findImport('does-not-exist.so');                  // null
```

### Format Specifics

Please note that depending on the library type (`*.so`/`*.dll`/`*.dylib`/etc.), 
it may contain additional type-specific data.

Using this data is not recommended (it may be removed later), but just be aware 
that this information is available.

For example:

```php
use FFI\Reflection\ReflectionLibrary\Reader\ELF\ElfReflectionExportSymbol;

$symbol = $library->getSymbol('sqlite3_open');

if ($symbol instanceof ElfReflectionExportSymbol) {
    $symbol->getType();  // SymbolType::Func
    $symbol->getSize();  // 132
    $symbol->getIndex(); // 47
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

  - Imports [2] {
    Import [ libm.so.6 ] {

      - Symbols [2] {
        Symbol [ log10@GLIBC_2.2.5 ]
        Symbol [ pow@GLIBC_2.2.5 ]
      }
    }

    Import [ libc.so.6 ] {

      - Symbols [1] {
        Symbol [ printf@GLIBC_2.2.5 ]
      }
    }
  }

  - Symbols [2] {
    Symbol [ sqlite3_open ] { 0x0000F7F1 }
    Symbol [ sqlite3_close ] { 0x0000F8A3 }
  }
}
```
