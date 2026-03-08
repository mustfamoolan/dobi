<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialAccount extends Model
{
    protected $fillable = [
        'name',
        'type',
        'currency',
        'opening_balance',
        'current_balance',
        'is_active',
        'created_by'
    ];

    public function ledgerEntries()
    {
        return $this->hasMany(AccountLedger::class, 'account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
