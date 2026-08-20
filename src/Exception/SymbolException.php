<?php

declare(strict_types=1);

namespace FFI\Reflection\Exception;

/**
 * Something went wrong while asking a library about its symbols.
 *
 * Catch this to treat "the library keeps no symbols" and "the library
 * keeps no such symbol" alike, which is what a caller looking for one name
 * usually wants.
 */
abstract class SymbolException extends LibraryException {}
