<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function recordEntry(
        Product $product,
        int $quantity,
        ?string $reason = null,
        ?string $notes = null,
        ?User $user = null,
    ): StockMovement {
        if ($quantity < 1) {
            throw new InvalidArgumentException('La cantidad debe ser al menos 1.');
        }

        return $this->applyMovement(
            product: $product,
            type: StockMovementType::Entry,
            quantity: $quantity,
            stockAfter: $product->stock + $quantity,
            reason: $reason,
            notes: $notes,
            user: $user,
        );
    }

    public function recordExit(
        Product $product,
        int $quantity,
        ?string $reason = null,
        ?string $notes = null,
        ?User $user = null,
    ): StockMovement {
        if ($quantity < 1) {
            throw new InvalidArgumentException('La cantidad debe ser al menos 1.');
        }

        if ($quantity > $product->stock) {
            throw new InvalidArgumentException('No hay stock suficiente para esta salida.');
        }

        return $this->applyMovement(
            product: $product,
            type: StockMovementType::Exit,
            quantity: $quantity,
            stockAfter: $product->stock - $quantity,
            reason: $reason,
            notes: $notes,
            user: $user,
        );
    }

    public function adjustStock(
        Product $product,
        int $newStock,
        ?string $notes = null,
        ?User $user = null,
    ): StockMovement {
        if ($newStock < 0) {
            throw new InvalidArgumentException('El stock no puede ser negativo.');
        }

        if ($newStock === $product->stock) {
            throw new InvalidArgumentException('El stock indicado es igual al actual.');
        }

        return $this->applyMovement(
            product: $product,
            type: StockMovementType::Adjustment,
            quantity: abs($newStock - $product->stock),
            stockAfter: $newStock,
            reason: 'Ajuste de inventario',
            notes: $notes,
            user: $user,
        );
    }

    private function applyMovement(
        Product $product,
        StockMovementType $type,
        int $quantity,
        int $stockAfter,
        ?string $reason,
        ?string $notes,
        ?User $user,
    ): StockMovement {
        return DB::transaction(function () use ($product, $type, $quantity, $stockAfter, $reason, $notes, $user) {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $stockBefore = $locked->stock;

            if ($type === StockMovementType::Exit && $quantity > $stockBefore) {
                throw new InvalidArgumentException('No hay stock suficiente para esta salida.');
            }

            $locked->update(['stock' => $stockAfter]);

            return StockMovement::create([
                'product_id' => $locked->id,
                'user_id' => $user?->id,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reason' => $reason,
                'notes' => $notes,
            ]);
        });
    }
}
