<?php

namespace App\Exception;

/**
 * Levée quand une attribution ferait dépasser le tirage total (`quantity`) d'une carte.
 */
class StockEpuiseException extends \RuntimeException
{
    public function __construct(private readonly int $restant)
    {
        parent::__construct(sprintf('Stock insuffisant : %d exemplaire(s) restant(s).', $restant));
    }

    public function getRestant(): int
    {
        return $this->restant;
    }
}
