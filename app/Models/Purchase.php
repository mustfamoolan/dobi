<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = [
        'date',
        'supplier_id',
        'warehouse_id',
        'currency',
        'exchange_rate',
        'total',
        'discount',
        'tax',
        'grand_total',
        'payment_status',
        'notes',
        'created_by',
        'updated_by'
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paidAmount()
    {
        return SupplierLedger::where('ref_type', 'purchase')
            ->where('ref_id', $this->id)
            ->where('type', 'payment')
            ->where('supplier_id', $this->supplier_id)
            ->sum('debit');
    }

    public function remainingAmount()
    {
        return max(0, $this->grand_total - $this->paidAmount());
    }

    public function updatePaymentStatus()
    {
        $remaining = $this->remainingAmount();
        if ($remaining <= 0) {
            $this->payment_status = 'paid';
        } elseif ($remaining < $this->grand_total) {
            $this->payment_status = 'partial';
        } else {
            $this->payment_status = 'pending';
        }
        $this->save();
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
