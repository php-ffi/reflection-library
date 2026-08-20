<?php

declare(strict_types=1);

namespace FFI\Reflection\Exception;

/**
 * Base of every failure this package reports, and a reflection exception of
 * PHP like any other.
 *
 * Every failure is a class of its own, so a caller catches the branch of the
 * hierarchy matching how much it is prepared to handle. Catch this one to
 * handle them all.
 */
abstract class ReflectionException extends \ReflectionException {}
