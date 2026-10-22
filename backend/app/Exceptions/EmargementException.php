<?php

namespace App\Exceptions;

use RuntimeException;

/** Erreur métier d'émargement, renvoyée en 422 avec un code exploitable par le front. */
class EmargementException extends RuntimeException
{
    public function __construct(string $message, public readonly string $codeMetier)
    {
        parent::__construct($message);
    }

    public function render()
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->codeMetier], 422);
    }
}
