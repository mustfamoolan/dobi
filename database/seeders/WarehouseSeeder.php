<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouse = \App\Models\Warehouse::firstOrCreate(
            ['name' => 'محل السنك'],
            ['location' => 'الموقع المحل السنك', 'notes' => 'المخزن الافتراضي للنظام', 'is_active' => true]
        );

        // Update existing stock movements
        \App\Models\StockMovement::whereNull('warehouse_id')->update(['warehouse_id' => $warehouse->id]);

        // Update existing sales
        \App\Models\Sale::whereNull('warehouse_id')->update(['warehouse_id' => $warehouse->id]);

        // Update existing purchases
        \App\Models\Purchase::whereNull('warehouse_id')->update(['warehouse_id' => $warehouse->id]);
    }
}
