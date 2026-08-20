<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary;

/**
 * An instruction set a library can be compiled for.
 *
 * The families the shipped drivers can name are listed in {@see Architecture}.
 * A driver of your own reporting a family that is not among them implements
 * this interface instead, so that a consumer can still tell architectures
 * apart by name.
 */
interface ArchitectureInterface
{
    /**
     * Name of the architecture.
     *
     * @var non-empty-string
     */
    public string $name {
        get;
    }
}
