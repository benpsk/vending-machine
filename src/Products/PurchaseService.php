<?php

declare(strict_types=1);

namespace App\Products;

use App\Database\Mysql\ProductRepository;
use App\Database\Mysql\TransactionRepository;
use App\Products\Exceptions\InvalidQuantityException;
use App\Products\Exceptions\OutOfStockException;
use App\Products\Exceptions\ProductNotFoundException;
use App\Transactions\Transaction;
use PDO;
use RuntimeException;
use Throwable;

// Not `final` so Mockery can stub it for the controller-unit-test layer (Req #15).
class PurchaseService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ProductRepository $products,
        private readonly TransactionRepository $transactions,
    ) {
    }

    /**
     * Atomically purchase $quantity units of $productId for $userId.
     *
     * Wraps the locked read + decrement + transaction insert in a single
     * SQL transaction so concurrent buyers cannot oversell stock.
     *
     * @throws InvalidQuantityException  if $quantity < 1
     * @throws ProductNotFoundException  if product doesn't exist
     * @throws OutOfStockException       if requested > available
     */
    public function purchase(int $userId, int $productId, int $quantity): Transaction
    {
        if ($quantity < 1) {
            throw new InvalidQuantityException($quantity);
        }

        $this->pdo->beginTransaction();

        try {
            $product = $this->products->findByIdForUpdate($productId);
            if ($product === null) {
                throw new ProductNotFoundException($productId);
            }

            if ($product->quantityAvailable < $quantity) {
                throw new OutOfStockException(
                    productId: $productId,
                    requested: $quantity,
                    available: $product->quantityAvailable,
                );
            }

            $unitPrice = $product->price;
            if (!is_numeric($unitPrice)) {
                throw new RuntimeException("Product {$productId} has non-numeric price: {$unitPrice}");
            }
            $totalAmount = bcmul($unitPrice, (string)$quantity, 3);

            $this->products->decrementStock($productId, $quantity);
            $txnId = $this->transactions->record(
                userId: $userId,
                productId: $productId,
                quantity: $quantity,
                unitPrice: $product->price,
                totalAmount: $totalAmount,
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $transaction = $this->transactions->findById($txnId);
        if ($transaction === null) {
            throw new RuntimeException("Transaction {$txnId} disappeared after commit.");
        }
        return $transaction;
    }
}
