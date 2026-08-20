<?php

declare(strict_types=1);

namespace FFI\Reflection\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Boundaries of the packages the library is built of.
 *
 * Every rule below is stated the other way round from how it reads: PHPat
 * describes what a class may depend on, while the boundaries here are about
 * who may depend on a package. So each one takes everything outside the
 * package allowed to use it and forbids the dependency there.
 *
 * These are not PHPUnit cases: PHPat runs them as PHPStan rules over the
 * analysed sources, so `composer linter:check` is what reports a breach.
 */
final class ReflectionLibraryArchitectureTest
{
    /**
     * Namespace holding everything the library ships.
     */
    private const string ROOT = 'FFI\Reflection';

    /**
     * Namespace of the byte level readers.
     */
    private const string STREAM = 'FFI\Reflection\ReflectionLibrary\Stream';

    /**
     * Namespace of the binary format drivers.
     */
    private const string READER = 'FFI\Reflection\ReflectionLibrary\Reader';

    /**
     * The facade, i.e. the only class allowed to reach for a driver.
     */
    private const string FACADE = 'FFI\Reflection\ReflectionLibrary';

    /**
     * Matches a file sitting directly in the reader directory rather than in
     * the sub-directory of one of the formats. Both separators are accepted
     * because the pattern is applied to a pathname of the host.
     */
    private const string READER_ROOT_FILE = '#[/\\\\]Reader[/\\\\][^/\\\\]+\.php$#';

    /**
     * Reading bytes is what a driver does, so nothing above the drivers has
     * any business knowing that a stream exists at all.
     *
     * The facade is excluded because it is the one opening the file and
     * handing the stream over to the driver it picked.
     */
    public function testStreamsAreOnlyUsedByReaders(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::ROOT))
            ->excluding(
                Selector::classname(self::FACADE),
                Selector::inNamespace(self::READER),
                Selector::inNamespace(self::STREAM),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace(self::STREAM));
    }

    /**
     * A format driver is an implementation detail of the reader package:
     * everything it produces reaches the outside as the format-agnostic
     * reflection objects, never as a type of its own.
     */
    public function testFormatDriversDoNotLeakOutOfTheReaders(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::ROOT))
            ->excluding(Selector::inNamespace(self::READER))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace(self::READER . '\ELF'),
                Selector::inNamespace(self::READER . '\PE'),
                Selector::inNamespace(self::READER . '\MachO'),
            );
    }

    /**
     * The driver contract, the factory selecting one and the snapshot they
     * answer with are the seam between the facade and the readers, so the
     * facade is the only thing outside the package that may touch them.
     */
    public function testTheReaderSeamIsOnlyUsedByTheFacade(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::ROOT))
            ->excluding(
                Selector::classname(self::FACADE),
                Selector::inNamespace(self::READER),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::AllOf(
                Selector::inNamespace(self::READER),
                Selector::withFilepath(self::READER_ROOT_FILE, true),
            ));
    }
}
