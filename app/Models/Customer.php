<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'address',
        'opening_balance_iqd',
        'opening_balance_usd',
        'created_by',
        'updated_by',
    ];

    protected static function booted()
    {
        static::deleting(function ($customer) {
            // 1. Delete all ledger entries
            $customer->ledgerEntries()->delete();

            // 2. Delete all related sales, including their items and stock movements
            $sales = Sale::where('customer_id', $customer->id)->get();
            foreach ($sales as $sale) {
                // Delete sale items
                $sale->items()->delete();
                // Delete stock movements related to this sale
                StockMovement::where('ref_type', 'sale')->where('ref_id', (string) $sale->id)->delete();
                // Delete the sale itself
                $sale->delete();
            }

            // 3. Delete related vouchers (where account_type is 'customer' and account_id matches)
            Voucher::where('account_type', 'customer')->where('account_id', $customer->id)->delete();
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(CustomerLedger::class);
    }

    public function getCurrentBalance($currency = 'IQD')
    {
        return CustomerLedger::where('customer_id', $this->id)
            ->where('currency', $currency)
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->first()->balance ?? 0;
    }

    public function getBalanceBeforeSale($saleId, $currency = 'IQD')
    {
        $saleEntry = CustomerLedger::where('customer_id', $this->id)
            ->where('ref_type', 'sale')
            ->where('ref_id', $saleId)
            ->first();

        if (!$saleEntry) {
            return CustomerLedger::where('customer_id', $this->id)
                ->where('currency', $currency)
                ->where(function ($q) use ($saleId) {
                    $q->where('ref_type', '!=', 'sale')
                      ->orWhere('ref_id', '!=', $saleId)
                      ->orWhereNull('ref_id');
                })
                ->selectRaw('SUM(debit) - SUM(credit) as balance')
                ->first()->balance ?? 0;
        }

        $saleDate = \Carbon\Carbon::parse($saleEntry->date)->format('Y-m-d');

        return CustomerLedger::where('customer_id', $this->id)
            ->where('currency', $currency)
            ->where(function ($q) use ($saleDate, $saleEntry) {
                $q->where('date', '<', $saleDate)
                    ->orWhere(function ($q2) use ($saleDate, $saleEntry) {
                        $q2->where('date', '=', $saleDate)
                            ->where('id', '<', $saleEntry->id);
                    });
            })
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->first()->balance ?? 0;
    }
}
