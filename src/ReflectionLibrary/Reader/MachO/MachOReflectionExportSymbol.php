<?php

declare(strict_types=1);

namespace FFI\Reflection\ReflectionLibrary\Reader\MachO;

use FFI\Reflection\ReflectionLibrary\ReflectionExportSymbol;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolAbi;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolResolution;
use FFI\Reflection\ReflectionLibrary\ReflectionSymbolVisibility;

/**
 * A terminal node of the dyld export trie, i.e. a symbol the image offers to
 * other objects.
 *
 * Darwin prefixes every C symbol with an underscore, so the
 * {@see ReflectionExportSymbol::$nativeName} of `foo` is `_foo` while its
 * {@see ReflectionExportSymbol::$name} is `foo` again: `dlsym()` puts the
 * underscore back on its own.
 */
final readonly class MachOReflectionExportSymbol extends ReflectionExportSymbol
{
    /**
     * @param non-empty-string $nativeName raw symbol name, including the
     *        leading underscore of the C name mangling
     * @param non-empty-string|null $name
     * @param non-empty-string|null $forwarder
     */
    public function __construct(
        string $nativeName,
        ?string $name,
        ?int $address,
        ?string $forwarder,
        bool $isWeak,
    ) {
        parent::__construct(
            nativeName: $nativeName,
            name: $name,
            address: $address,
            forwarder: $forwarder,
            binding: $isWeak ? ReflectionSymbolResolution::Weak : ReflectionSymbolResolution::Global,
            // Everything the trie holds is unconditionally public.
            visibility: ReflectionSymbolVisibility::Public,

            // The format records no calling convention, because the
            // platform offers a single one.
            abi: ReflectionSymbolAbi::Default,
        );
    }
}
