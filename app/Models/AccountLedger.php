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
}
