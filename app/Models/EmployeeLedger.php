<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLedger extends Model
{
    protected $fillable = [
        'employee_id',
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

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
