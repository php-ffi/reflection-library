<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader;

use FFI\Reflection\Exception\ReflectionException;
use FFI\Reflection\ReflectionLibrary\ReflectionExportSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionImport;
use FFI\Reflection\ReflectionLibrary\Stream\StreamInterface;
use FFI\Reflection\ReflectionLibrary\Stream\TypedStream;

/**
 * A driver capable of reading a single binary format.
 *
 * The stream belongs to the caller: a driver neither closes it nor expects to
 * find the position where a previous call left it. Every method is
 * idempotent, so asking the same question twice yields the same answer.
 */
interface LibraryReaderInterface
{
    /**
     * Whether the stream contains an image of the format supported by this
     * driver.
     *
     * A positive answer means the image is one this driver reads, not that
     * it is well formed.
     */
    public function supports(StreamInterface $stream): bool;

    /**
     * Reads the traits every supported format describes an image with, i.e.
     * the ones a loader needs before it can make sense of anything else.
     *
     * @throws ReflectionException in case of the image cannot be read
     */
    public function getCommonInfo(TypedStream $stream): CommonLibraryInfo;

    /**
     * Reads the external libraries the image depends on, together with the
     * symbols it takes from each of them.
     *
     * @return iterable<array-key, ReflectionImport>
     * @throws ReflectionException in case of the image cannot be read
     */
    public function getImports(TypedStream $stream): iterable;

    /**
     * Reads the symbols the image offers to other objects, i.e. its public
     * interface.
     *
     * @return iterable<array-key, ReflectionExportSymbol>
     * @throws ReflectionException in case of the image cannot be read
     */
    public function getSymbols(TypedStream $stream): iterable;
}
