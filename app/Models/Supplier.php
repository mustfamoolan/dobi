<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'address', 'opening_balance', 'currency', 'created_by', 'updated_by'];

    protected static function booted()
    {
        static::deleting(function ($supplier) {
            // 1. Delete all ledger entries
            $supplier->ledgerEntries()->delete();

            // 2. Delete all related purchases, including their items and stock movements
            $purchases = Purchase::where('supplier_id', $supplier->id)->get();
            foreach ($purchases as $purchase) {
                // Delete purchase items
                $purchase->items()->delete();
                // Delete stock movements related to this purchase
                StockMovement::where('ref_type', 'purchase')->where('ref_id', (string) $purchase->id)->delete();
                // Delete the purchase itself
                $purchase->delete();
            }

            // 3. Delete related vouchers (where account_type is 'supplier' and account_id matches)
            Voucher::where('account_type', 'supplier')->where('account_id', $supplier->id)->delete();
        });
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function ledgerEntries()
    {
        return $this->hasMany(SupplierLedger::class);
    }

    public function getCurrentBalance($currency = 'IQD')
    {
        return SupplierLedger::where('supplier_id', $this->id)
            ->where('currency', $currency)
            ->selectRaw('SUM(credit) - SUM(debit) as balance')
            ->first()->balance ?? 0;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
