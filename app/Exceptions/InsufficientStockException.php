<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    protected float|int $availableStock;
    protected float|int $requestedQuantity;
    protected ?string $productName;

    public function __construct(
        string $message = 'Stock insuficiente para realizar la operación.',
        float|int $availableStock = 0,
        float|int $requestedQuantity = 0,
        int $code = 0,
        ?Exception $previous = null,
        ?string $productName = null
    ) {
        $this->availableStock = $availableStock;
        $this->requestedQuantity = $requestedQuantity;
        $this->productName = $productName;

        if ($message === 'Stock insuficiente para realizar la operación.' || ($availableStock > 0 || $requestedQuantity > 0 || $productName !== null)) {
            $formattedAvail = (float)$availableStock == (int)$availableStock ? (int)$availableStock : $availableStock;
            $formattedReq = (float)$requestedQuantity == (int)$requestedQuantity ? (int)$requestedQuantity : $requestedQuantity;

            if ($productName) {
                $message = "Stock insuficiente para '{$productName}'. Existencias disponibles: {$formattedAvail}, Cantidad solicitada: {$formattedReq}.";
            } elseif ($availableStock > 0 || $requestedQuantity > 0) {
                $message = "Stock insuficiente. Existencias disponibles: {$formattedAvail}, Cantidad solicitada: {$formattedReq}.";
            }
        }

        parent::__construct($message, $code, $previous);
    }

    public function getAvailableStock(): float|int
    {
        return $this->availableStock;
    }

    public function getRequestedQuantity(): float|int
    {
        return $this->requestedQuantity;
    }

    public function getProductName(): ?string
    {
        return $this->productName;
    }
}
