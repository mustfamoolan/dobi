<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountLedger extends Model
{
    protected $fillable = [
        'account_id',
        'date',
        'description',
        'debit',
        'credit',
        'balance',
        'ref_type',
        'ref_id',
        'created_by'
    ];

    public function account()
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getReferenceAttribute()
    {
        if ($this->ref_type === 'voucher') {
            return Voucher::find($this->ref_id);
        } elseif ($this->ref_type === 'sale') {
            return Sale::find($this->ref_id);
        } elseif ($this->ref_type === 'purchase') {
            return Purchase::find($this->ref_id);
        }
        return null;
    }
}
