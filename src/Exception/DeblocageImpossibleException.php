<?php

namespace App\Exception;

/**
 * Levée quand une carte mythique ne peut pas être débloquée ; le message est destiné au joueur.
 */
class DeblocageImpossibleException extends \RuntimeException
{
}
