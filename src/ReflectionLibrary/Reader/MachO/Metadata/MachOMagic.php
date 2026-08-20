<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO\Metadata;

/**
 * Signatures opening a Mach-O file, telling both its width and its byte
 * order apart.
 */
final class MachOMagic
{
    /**
     * A little-endian 32-bit image, `MH_MAGIC`.
     */
    public const string MAGIC_32 = "\xCE\xFA\xED\xFE";

    /**
     * A big-endian 32-bit image, `MH_CIGAM`.
     */
    public const string MAGIC_32_BE = "\xFE\xED\xFA\xCE";

    /**
     * A little-endian 64-bit image, `MH_MAGIC_64`.
     */
    public const string MAGIC_64 = "\xCF\xFA\xED\xFE";

    /**
     * A big-endian 64-bit image, `MH_CIGAM_64`.
     */
    public const string MAGIC_64_BE = "\xFE\xED\xFA\xCF";

    /**
     * A universal archive, `FAT_MAGIC` and its variants. Such a file is a
     * container of several images rather than an image itself.
     *
     * @var non-empty-list<non-empty-string>
     */
    public const array MAGIC_FAT = [
        "\xCA\xFE\xBA\xBE",
        "\xCA\xFE\xBA\xBF",
        "\xBE\xBA\xFE\xCA",
        "\xBF\xBA\xFE\xCA",
    ];

    /**
     * Size of the `mach_header_64` structure, which the load commands
     * follow.
     */
    public const int HEADER_SIZE_64 = 32;

    /**
     * Size of the `mach_header` structure.
     */
    public const int HEADER_SIZE_32 = 28;

    private function __construct() {}
}
