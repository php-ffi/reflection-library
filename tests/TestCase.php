<?php

declare(strict_types=1);

namespace FFI\Reflection\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Directory containing the binary fixtures, laid out as
     * `<Platform>/<architecture>/<file>`.
     */
    protected const string STUBS_DIRECTORY = __DIR__ . '/stubs';

    /**
     * @return array<non-empty-string, array{non-empty-string}>
     */
    public static function librariesDataProvider(): array
    {
        return self::getLibraries('{dll,so,dylib}');
    }

    /**
     * @return array<non-empty-string, array{non-empty-string}>
     */
    public static function elfLibrariesDataProvider(): array
    {
        return self::getLibraries('so');
    }

    /**
     * @return array<non-empty-string, array{non-empty-string}>
     */
    public static function peLibrariesDataProvider(): array
    {
        return self::getLibraries('dll');
    }

    /**
     * @return array<non-empty-string, array{non-empty-string}>
     */
    public static function machOLibrariesDataProvider(): array
    {
        return self::getLibraries('dylib');
    }

    /**
     * Gets every binary fixture matching the given extension pattern, keyed
     * by its path relative to the stubs directory, like
     * `Linux/x86_64/libsqlitejdbc.so`.
     *
     * @param non-empty-string $extensions
     *
     * @return array<non-empty-string, array{non-empty-string}>
     */
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
