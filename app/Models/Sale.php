<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    public const TYPE_INVOICE = 'invoice';
    public const TYPE_QUOTATION = 'quotation';
    public const TYPE_PROFORMA = 'proforma';

    protected $fillable = [
        'date',
        'customer_id',
        'employee_id',
        'warehouse_id',
        'currency',
        'exchange_rate',
        'total',
        'discount',
        'tax',
        'grand_total',
        'type',
        'payment_status',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesperson()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paidAmount()
    {
        return CustomerLedger::where('ref_type', 'sale')
            ->where('ref_id', $this->id)
            ->where('type', 'payment')
            ->sum('credit');
    }

    public function remainingAmount()
    {
        return max(0, $this->grand_total - $this->paidAmount());
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
