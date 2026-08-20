<?php

declare(strict_types=1);

namespace FFI\Reflection\Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class ReflectionLibraryArchitectureTest
{
    private const string ROOT = 'FFI\Reflection';

    private const string STREAM = 'FFI\Reflection\ReflectionLibrary\Stream';

    private const string READER = 'FFI\Reflection\ReflectionLibrary\Reader';

    private const string FACADE = 'FFI\Reflection\ReflectionLibrary';

    private const string READER_ROOT_FILE = '#[/\\\\]Reader[/\\\\][^/\\\\]+\.php$#';

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
