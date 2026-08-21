<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader;

use FFI\Reflection\Exception\ReflectionException;
use FFI\Reflection\Exception\UnsupportedFormatException;
use FFI\Reflection\ReflectionLibrary\Reader\ELF\ElfLibraryReader;
use FFI\Reflection\ReflectionLibrary\Reader\MachO\MachOLibraryReader;
use FFI\Reflection\ReflectionLibrary\Reader\PE\PeLibraryReader;
use FFI\Reflection\ReflectionLibrary\Stream\StreamInterface;

/**
 * Selects the driver able to read a given binary image.
 */
final readonly class LibraryReaderFactory
{
    /**
     * Drivers of the factory, in the order they were registered in.
     *
     * @var list<LibraryReaderInterface>
     */
    private array $drivers;

    /**
     * @param iterable<mixed, LibraryReaderInterface> $drivers
     */
    public function __construct(iterable $drivers = [])
    {
        $this->drivers = \iterator_to_array($drivers, false);
    }

    /**
     * Creates a factory containing every driver shipped with the library.
     */
    public static function createDefault(): self
    {
        return new self([
            new ElfLibraryReader(),
            new PeLibraryReader(),
            new MachOLibraryReader(),
        ]);
    }

    /**
     * Gets the first registered driver recognising the image the stream
     * holds.
     *
     * @param string|null $pathname name of the file behind the stream, used
     *        for error reporting only
     * @throws ReflectionException in case of no driver supports the image
     */
    public function createFromStream(StreamInterface $stream, ?string $pathname = null): LibraryReaderInterface
    {
        foreach ($this->drivers as $driver) {
            if ($driver->supports($stream)) {
                return $driver;
            }
        }

        throw UnsupportedFormatException::becauseNoDriverRecognisesIt(
            pathname: $pathname,
            drivers: \count($this->drivers),
        );
    }
}
