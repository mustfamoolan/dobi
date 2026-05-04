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
        $directPayments = CustomerLedger::where('ref_type', 'sale')
            ->where('ref_id', $this->id)
            ->where('type', 'payment')
            ->sum('credit');

        $vouchers = Voucher::where('sale_id', $this->id)->get();
        $voucherTotal = 0;
        foreach ($vouchers as $v) {
            if ($v->currency === $this->currency) {
                $voucherTotal += $v->amount;
            } else {
                if ($this->currency === 'IQD' && $v->currency === 'USD') {
                    $voucherTotal += $v->amount * $v->exchange_rate;
                } elseif ($this->currency === 'USD' && $v->currency === 'IQD') {
                    $voucherTotal += $v->amount / ($v->exchange_rate ?: 1);
                }
            }
        }

        return $directPayments + $voucherTotal;
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
            $this->payment_status = 'unpaid';
        }
        $this->save();
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
