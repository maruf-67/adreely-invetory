<?php

namespace App\Services;

use App\Models\Product;
use App\Models\InventoryHistory;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Update product stock with validation and history logging
     */
    public function updateStock(
        Product $product,
        int $quantityChange,
        string $type,
        string $reason,
        int $userId,
        $reference = null
    ): bool {
        return DB::transaction(function () use ($product, $quantityChange, $type, $reason, $userId, $reference) {
            $quantityBefore = $product->quantity;
            $quantityAfter = $quantityBefore + $quantityChange;

            // Prevent negative stock
            if ($quantityAfter < 0) {
                throw new \Exception("Insufficient stock. Available: {$quantityBefore}, Required: " . abs($quantityChange));
            }

            // Update product quantity
            $product->update(['quantity' => $quantityAfter]);

            // Create inventory history
            InventoryHistory::createRecord(
                $product->business_id,
                $product->id,
                $userId,
                $type,
                $quantityChange,
                $quantityBefore,
                $reason,
                $reference
            );

            return true;
        });
    }

    /**
     * Adjust stock with manual reason validation
     */
    public function adjustStock(Product $product, int $newQuantity, string $reason, string $adjustmentType, int $userId): bool
    {
        $quantityBefore = $product->quantity;
        $quantityChange = $newQuantity - $quantityBefore;

        if ($quantityChange == 0) {
            throw new \Exception('No quantity change detected');
        }

        // Validate reason length and content
        if (strlen($reason) < 10) {
            throw new \Exception('Adjustment reason must be at least 10 characters long');
        }

        $fullReason = "Manual Adjustment ({$adjustmentType}): {$reason}";

        return $this->updateStock($product, $quantityChange, 'adjustment', $fullReason, $userId);
    }

    /**
     * Get low stock products for a business
     */
    public function getLowStockProducts(int $businessId): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('business_id', $businessId)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->with(['category', 'brand', 'unit'])
            ->get();
    }

    /**
     * Get stock movement summary for a product
     */
    public function getStockMovementSummary(Product $product, ?\Carbon\Carbon $from = null, ?\Carbon\Carbon $to = null): array
    {
        $query = $product->inventoryHistories();

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $movements = $query->get();

        return [
            'total_stock_in' => $movements->where('type', 'stock-in')->sum('quantity_change'),
            'total_stock_out' => abs($movements->where('type', 'stock-out')->sum('quantity_change')),
            'total_adjustments' => $movements->where('type', 'adjustment')->sum('quantity_change'),
            'movement_count' => $movements->count(),
            'current_stock' => $product->quantity
        ];
    }
}
