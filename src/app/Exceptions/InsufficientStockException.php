<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public static function forProduct(
        string $productName,
        string $sku,
        float $available,
        float $requested,
    ): self {
        return new self(
            sprintf(
                "Estoque insuficiente para o produto '%s' (SKU: %s). Disponível: %s, Solicitado: %s",
                $productName,
                $sku,
                $available,
                $requested,
            )
        );
    }
}
