<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * ContainerException
 *
 * Thrown by the Container when it cannot resolve a dependency.
 *   - A requested class does not exist
 */
class ContainerException extends \RuntimeException
{
    public function __construct(
        string     $message,
        int        $code     = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}