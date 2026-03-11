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

    public function getBalanceBeforeSale($saleId, $currency = 'IQD')
    {
        $saleEntry = CustomerLedger::where('customer_id', $this->id)
            ->where('currency', $currency)
            ->where('ref_type', 'sale')
            ->where('ref_id', $saleId)
            ->first();

        if (!$saleEntry) {
            return CustomerLedger::where('customer_id', $this->id)
                ->where('currency', $currency)
                ->selectRaw('SUM(debit) - SUM(credit) as balance')
                ->first()->balance ?? 0;
        }

        return CustomerLedger::where('customer_id', $this->id)
            ->where('currency', $currency)
            ->where(function ($q) use ($saleEntry) {
                $q->where('date', '<', $saleEntry->date)
                    ->orWhere(function ($q2) use ($saleEntry) {
                        $q2->where('date', '=', $saleEntry->date)
                            ->where('id', '<', $saleEntry->id);
                    });
            })
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->first()->balance ?? 0;
    }
}
