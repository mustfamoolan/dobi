<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'position',
        'salary',
        'commission_rate',
        'is_active',
        'created_by',
        'updated_by',
    ];

    public function ledger()
    {
        return $this->hasMany(EmployeeLedger::class);
    }

    public function getCurrentBalance($currency = 'IQD')
    {
        return EmployeeLedger::where('employee_id', $this->id)
            ->where('currency', $currency)
            ->selectRaw('SUM(credit) - SUM(debit) as balance')
            ->first()->balance ?? 0;
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
