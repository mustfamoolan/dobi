<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'category_id',
        'currency',
        'cost',
        'price',
        'unit',
        'stock_alert',
        'is_active',
        'created_by',
        'updated_by'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function currentStock($warehouseId = null)
    {
        $queryIn = $this->stockMovements();
        $queryOut = $this->stockMovements();

        if ($warehouseId) {
            $queryIn->where('warehouse_id', $warehouseId);
            $queryOut->where('warehouse_id', $warehouseId);
        }

        return $queryIn->sum('qty_in') - $queryOut->sum('qty_out');
    }

    public function stockInWarehouse($warehouseId)
    {
        return $this->currentStock($warehouseId);
    }

    public function getStockAttribute()
    {
        return $this->currentStock();
    }

    public function checkStockAlert()
    {
        if ($this->stock_alert > 0 && $this->currentStock() <= $this->stock_alert) {
            $admins = User::where('role', 'admin')->get();
            try {
                \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\SystemNotification(
                    "Low Stock Alert",
                    "Stock for product '{$this->name}' is low: " . $this->currentStock() . " remaining.",
                    'ri-error-warning-line',
                    route('admin.products.index'),
                    'danger'
                ));
            } catch (\Exception $e) {
            }
        }
    }
}
