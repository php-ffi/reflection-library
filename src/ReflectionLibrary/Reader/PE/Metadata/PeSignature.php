<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\PE\Metadata;

/**
 * Signatures and fixed offsets of the headers opening a PE file.
 */
final class PeSignature
{
    /**
     * Contents of the `IMAGE_DOS_HEADER.e_magic` field.
     */
    public const string DOS_MAGIC = 'MZ';

    /**
     * Contents of the `IMAGE_NT_HEADERS.Signature` field.
     */
    public const string NT_MAGIC = "PE\0\0";

    /**
     * Offset of the `IMAGE_DOS_HEADER.e_lfanew` field, which points at the
     * NT headers.
     */
    public const int E_LFANEW_OFFSET = 0x3C;

    /**
     * Size of the `IMAGE_FILE_HEADER` structure, which the optional header
     * follows.
     */
    public const int FILE_HEADER_SIZE = 20;

    /**
     * Offset of the optional header relative to the NT headers, i.e. behind
     * the signature and the file header.
     */
    public const int OPTIONAL_HEADER_OFFSET = 24;

    /**
     * Size of a single `IMAGE_SECTION_HEADER` entry.
     */
    public const int SECTION_HEADER_SIZE = 40;

    /**
     * Size of a single `IMAGE_IMPORT_DESCRIPTOR` entry.
     */
    public const int IMPORT_DESCRIPTOR_SIZE = 20;

    /**
     * Size of a single `IMAGE_DELAYLOAD_DESCRIPTOR` entry.
     */
    public const int DELAY_IMPORT_DESCRIPTOR_SIZE = 32;

    /**
     * Bit of the `IMAGE_IMPORT_BY_NAME` thunk marking a symbol addressed by
     * ordinal, on a 32-bit image.
     */
    public const int ORDINAL_FLAG_32 = 0x8000_0000;

    /**
     * Mask selecting the address out of a thunk holding one.
     */
    public const int THUNK_ADDRESS_MASK = 0x7FFF_FFFF;

    private function __construct() {}
}
