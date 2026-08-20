<?php

declare(strict_types=1);

namespace FFI\Reflection\Exception;

/**
 * Something went wrong with a particular library.
 *
 * Catch this to handle every way a library can let a caller down, from not
 * being there at all to not holding what was asked of it.
 */
abstract class LibraryException extends ReflectionException {}
