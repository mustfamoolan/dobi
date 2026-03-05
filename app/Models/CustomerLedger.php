<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLedger extends Model
{
    protected $fillable = [
        'customer_id',
        'date',
        'type',
        'description',
        'currency',
        'exchange_rate',
        'debit',
        'credit',
        'balance',
        'ref_type',
        'ref_id',
        'created_by',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transaction()
    {
        return $this->morphto('transaction', 'ref_type', 'ref_id');
    }
}
