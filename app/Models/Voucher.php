<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = [
        'date',
        'type',
        'account_type',
        'account_id',
        'amount',
        'currency',
        'exchange_rate',
        'notes',
        'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Morphic relationship for the account could be added here, but simple logic is often easier for this app's scale.
    public function getAccountAttribute()
    {
        if ($this->account_type === 'customer')
            return Customer::find($this->account_id);
        if ($this->account_type === 'supplier')
            return Supplier::find($this->account_id);
        if ($this->account_type === 'employee')
            return Employee::find($this->account_id);
        return null;
    }
}
