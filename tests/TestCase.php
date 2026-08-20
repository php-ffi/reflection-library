<?php

declare(strict_types=1);

namespace FFI\Reflection\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected const string STUBS_DIRECTORY = __DIR__ . '/stubs';

    public static function librariesDataProvider(): array
    {
        return self::getLibraries('{dll,so,dylib}');
    }

    public static function elfLibrariesDataProvider(): array
    {
        return self::getLibraries('so');
    }

    public static function peLibrariesDataProvider(): array
    {
        return self::getLibraries('dll');
    }

    public static function machOLibrariesDataProvider(): array
    {
        return self::getLibraries('dylib');
    }

    protected static function getLibraries(string $extensions): array
    {
        $pathnames = \glob(self::STUBS_DIRECTORY . '/*/*/*.' . $extensions, \GLOB_BRACE);

        if ($pathnames === false) {
            return [];
        }

        $result = [];

        foreach ($pathnames as $pathname) {
            $name = \str_replace(
                \DIRECTORY_SEPARATOR,
                '/',
                \substr($pathname, \strlen(self::STUBS_DIRECTORY) + 1),
            );

            \assert($name !== '' && $pathname !== '');

            $result[$name] = [$pathname];
        }

        return $result;
    }
}
