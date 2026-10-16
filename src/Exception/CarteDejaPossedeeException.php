<?php

namespace App\Exception;

/**
 * Levée quand une carte limitée à un exemplaire par joueur (mythique) est déjà possédée.
 */
class CarteDejaPossedeeException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Vous possédez déjà cette carte.');
    }
}
