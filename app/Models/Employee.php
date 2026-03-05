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

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
