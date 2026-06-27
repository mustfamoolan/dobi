<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find all purchases that are marked as paid but missing the payment ledger entries
        $purchases = DB::table('purchases')->where('payment_status', 'paid')->get();
        foreach ($purchases as $p) {
            $exists = DB::table('supplier_ledgers')
                ->where('supplier_id', $p->supplier_id)
                ->where('ref_type', 'purchase')
                ->where('ref_id', $p->id)
                ->where('type', 'payment')
                ->exists();

            if (!$exists) {
                DB::table('supplier_ledgers')->insert([
                    'supplier_id' => $p->supplier_id,
                    'date' => $p->date,
                    'type' => 'payment',
                    'description' => 'Payment for Purchase #' . $p->id,
                    'currency' => $p->currency,
                    'exchange_rate' => $p->exchange_rate,
                    'debit' => $p->grand_total,
                    'credit' => 0,
                    'balance' => 0,
                    'ref_type' => 'purchase',
                    'ref_id' => $p->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed as this only repairs missing historical data
    }
};
