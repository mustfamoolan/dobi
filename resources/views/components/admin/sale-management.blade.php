<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\CustomerLedger;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\AppSetting;
use App\Models\Warehouse;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SystemNotification;
use App\Models\User;

new class extends Component {
    use WithPagination;

    #[Url]
    public $search = '';
    public $type = 'invoice'; // invoice, quotation, proforma
    public $showCreateModal = false;

    // Sale Form Fields
    public $date;
    public $customer_id;
    public $warehouse_id;
    public $employee_id; // Salesperson
    public $currency = 'USD';
    public $exchange_rate;
    public $notes;
    public $payment_status = 'pending';
    public $discount = 0;
    public $financial_account_id;
    public $items = []; // [{product_id, name, qty, price, subtotal}]
    public $viewingSale = null;
    public $confirmingConvertId = null;
    public $viewingPreviousBalance = 0;
    public $previous_currency = 'USD';

    // Item Addition Fields
    public $selected_product_id;
    public $item_qty = 1;
    public $item_price = 0;

    protected $paginationTheme = 'bootstrap';

    public function mount($type = 'invoice')
    {
        $this->type = $type;
        $this->date = now()->format('Y-m-d');
        $setting = AppSetting::first();
        $this->exchange_rate = $setting->exchange_rate ?? 1500;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->reset(['customer_id', 'warehouse_id', 'employee_id', 'items', 'notes', 'selected_product_id', 'item_qty', 'item_price', 'payment_status', 'discount']);
        $this->date = now()->format('Y-m-d');
        // Default to first warehouse
        $this->warehouse_id = Warehouse::first()->id ?? null;
        $setting = AppSetting::first();
        $this->exchange_rate = $setting->exchange_rate ?? 1500;
        $this->financial_account_id = \App\Models\FinancialAccount::where('is_active', true)->first()?->id;
        $this->dispatch('open-sale-modal');
    }

    public function updatedSelectedProductId($id)
    {
        if ($id) {
            $product = Product::find($id);
            $price_iqd = $product->price;
            
            if ($this->currency === 'USD' && $this->exchange_rate > 0) {
                $this->item_price = round($price_iqd / $this->exchange_rate, 2);
            } else {
                $this->item_price = $price_iqd;
            }
        }
    }

    public function updatedCurrency()
    {
        if ($this->currency !== $this->previous_currency) {
            $this->convertPrices();
            $this->previous_currency = $this->currency;
        }
    }

    public function updatedExchangeRate()
    {
        // When exchange rate changes, we don't automatically convert existing prices
        // as they might have been manually set for those items.
        // But we update the 'default' addition price.
        if ($this->selected_product_id) {
            $product = Product::find($this->selected_product_id);
            if ($product) {
                $price_iqd = $product->price;
                if ($this->currency === 'USD' && $this->exchange_rate > 0) {
                    $this->item_price = round($price_iqd / $this->exchange_rate, 2);
                } else {
                    $this->item_price = $price_iqd;
                }
            }
        }
    }

    protected function convertPrices()
    {
        // Re-calculate item_price if product is selected
        if ($this->selected_product_id) {
            $product = Product::find($this->selected_product_id);
            if ($product) {
                $price_iqd = $product->price;
                if ($this->currency === 'USD' && $this->exchange_rate > 0) {
                    $this->item_price = round($price_iqd / $this->exchange_rate, 2);
                } else {
                    $this->item_price = $price_iqd;
                }
            }
        }

        // Convert existing items in the list based on the flip
        foreach ($this->items as $index => $item) {
            $current_price = $item['price'];
            
            if ($this->currency === 'USD') {
                // Switched IQD -> USD
                $new_price = round($current_price / $this->exchange_rate, 2);
            } else {
                // Switched USD -> IQD
                $new_price = round($current_price * $this->exchange_rate, 0);
            }

            $this->items[$index]['price'] = $new_price;
            $this->items[$index]['subtotal'] = $this->items[$index]['qty'] * $new_price;
        }
    }

    public function updatedItems($value, $key)
    {
        // $key looks like "0.qty" or "2.price"
        if (str_contains($key, '.qty') || str_contains($key, '.price')) {
            $parts = explode('.', $key);
            $index = $parts[0];
            
            $qty = (float)($this->items[$index]['qty'] ?: 0);
            $price = (float)($this->items[$index]['price'] ?: 0);
            
            $this->items[$index]['subtotal'] = $qty * $price;
        }
    }

    public function addItem()
    {
        $this->validate([
            'selected_product_id' => 'required|exists:products,id',
            'item_qty' => 'required|numeric|min:0.001',
            'item_price' => 'required|numeric|min:0',
        ]);

        $product = Product::find($this->selected_product_id);

        // Check stock availability (optional but recommended)
        if ($product->stock < $this->item_qty) {
            $this->addError('item_qty', 'Insufficient stock. Available: ' . $product->stock);
            return;
        }

        // Check if item already exists in the list
        foreach ($this->items as $index => $item) {
            if ($item['product_id'] == $product->id) {
                $this->items[$index]['qty'] += $this->item_qty;
                $this->items[$index]['subtotal'] = $this->items[$index]['qty'] * $this->items[$index]['price'];
                $this->reset(['selected_product_id', 'item_qty', 'item_price']);
                return;
            }
        }

        $this->items[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'qty' => $this->item_qty,
            'price' => $this->item_price,
            'subtotal' => $this->item_qty * $this->item_price,
        ];

        $this->reset(['selected_product_id', 'item_qty', 'item_price']);
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function getTotalProperty()
    {
        return collect($this->items)->sum('subtotal');
    }

    public function getGrandTotalProperty()
    {
        return $this->total - (float)$this->discount;
    }

    public function save()
    {
        $this->validate([
            'date' => 'required|date',
            'customer_id' => 'required|exists:customers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'employee_id' => 'nullable|exists:employees,id',
            'currency' => 'required|string',
            'exchange_rate' => 'required|numeric|min:1',
            'items' => 'required|array|min:1',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.price' => 'required|numeric|min:0',
            'discount' => 'required|numeric|min:0',
            'financial_account_id' => 'required_if:payment_status,paid',
        ], [
            'items.*.qty.required' => __('Quantity is required for all items.'),
            'items.*.price.required' => __('Price is required for all items.'),
        ]);

        $saleId = null; // will be set inside transaction

        DB::transaction(function () use (&$saleId) {
            $total = $this->total;

            $isInvoice = $this->type === 'invoice';

            // 1. Create Sale record
            $sale = Sale::create([
                'date' => $this->date,
                'customer_id' => $this->customer_id,
                'warehouse_id' => $this->warehouse_id,
                'employee_id' => $this->employee_id ?: null,
                'currency' => $this->currency,
                'exchange_rate' => $this->exchange_rate,
                'total' => $total,
                'discount' => $this->discount,
                'grand_total' => $this->grandTotal,
                'type' => $this->type,
                'payment_status' => $this->payment_status,
                'notes' => $this->notes,
                'created_by' => Auth::id(),
            ]);

            $saleId = $sale->id;

            // 2. Create Items & Stock Movements
            foreach ($this->items as $item) {
                $product = Product::find($item['product_id']);
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'cost_snapshot' => $product->cost ?? 0,
                    'subtotal' => $item['subtotal'],
                ]);

                if ($isInvoice) {
                    StockMovement::create([
                        'product_id' => $item['product_id'],
                        'warehouse_id' => $this->warehouse_id,
                        'qty_in' => 0,
                        'qty_out' => $item['qty'],
                        'ref_type' => 'sale',
                        'ref_id' => $sale->id,
                        'note' => 'Sale Invoice #' . $sale->id,
                        'created_by' => Auth::id(),
                    ]);
                    
                    if ($product) {
                        $product->checkStockAlert();
                    }
                }
            }

            if ($isInvoice) {
                // 3. Create Customer Ledger Entry (Debit = they owe us money)
                CustomerLedger::create([
                    'customer_id' => $this->customer_id,
                    'date' => $this->date,
                    'type' => 'sale',
                    'description' => 'Sale Invoice #' . $sale->id,
                    'currency' => $this->currency,
                    'exchange_rate' => $this->exchange_rate,
                    'debit' => $this->grandTotal,
                    'credit' => 0,
                    'balance' => $this->grandTotal,
                    'ref_type' => 'sale',
                    'ref_id' => $sale->id,
                    'created_by' => Auth::id(),
                ]);

                // 4. Create Employee Commission Entry (if applicable)
                if ($this->employee_id) {
                    $employee = Employee::find($this->employee_id);
                    if ($employee && $employee->commission_rate > 0) {
                        $commissionAmount = $this->grandTotal * ($employee->commission_rate / 100);

                        EmployeeLedger::create([
                            'employee_id' => $this->employee_id,
                            'date' => $this->date,
                            'type' => 'commission',
                            'description' => 'Commission from Sale Invoice #' . $sale->id . ' (' . $employee->commission_rate . '%)',
                            'currency' => $this->currency,
                            'exchange_rate' => $this->exchange_rate,
                            'debit' => 0,
                            'credit' => $commissionAmount,
                            'balance' => $commissionAmount,
                            'ref_type' => 'sale',
                            'ref_id' => $sale->id,
                            'created_by' => Auth::id(),
                        ]);
                    }
                }

                // 5. If Paid, record in Treasury and credit customer
                if ($this->payment_status === 'paid') {
                    $account = \App\Models\FinancialAccount::findOrFail($this->financial_account_id);
                    $treasuryAmount = $this->grandTotal;

                    // Record in Treasury
                    \App\Models\AccountLedger::create([
                        'account_id' => $this->financial_account_id,
                        'date' => $this->date,
                        'description' => __('Cash Sale') . ' #' . $sale->id . ' (' . $sale->customer->name . ')',
                        'debit' => $treasuryAmount,
                        'credit' => 0,
                        'balance' => $account->current_balance + $treasuryAmount,
                        'ref_type' => 'sale',
                        'ref_id' => $sale->id,
                        'created_by' => Auth::id(),
                    ]);
                    $account->increment('current_balance', $treasuryAmount);

                    // Credit Customer (to cancel the debit from the sale)
                    CustomerLedger::create([
                        'customer_id' => $this->customer_id,
                        'date' => $this->date,
                        'type' => 'payment',
                        'description' => __('Payment for Sale') . ' #' . $sale->id,
                        'currency' => $this->currency,
                        'exchange_rate' => $this->exchange_rate,
                        'debit' => 0,
                        'credit' => $this->grandTotal,
                        'balance' => 0,
                        'ref_type' => 'sale',
                        'ref_id' => $sale->id,
                        'created_by' => Auth::id(),
                    ]);
                }
            } elseif ($this->type === 'proforma' && $this->payment_status === 'paid') {
                // Handle Proforma payment (Credit customer, Debit Treasury)
                $account = \App\Models\FinancialAccount::findOrFail($this->financial_account_id);
                $treasuryAmount = $this->grandTotal;

                // 1. Record in Treasury
                \App\Models\AccountLedger::create([
                    'account_id' => $this->financial_account_id,
                    'date' => $this->date,
                    'description' => __('Payment for Proforma') . ' #' . $sale->id,
                    'debit' => $treasuryAmount,
                    'credit' => 0,
                    'balance' => $account->current_balance + $treasuryAmount,
                    'ref_type' => 'sale',
                    'ref_id' => $sale->id,
                    'created_by' => Auth::id(),
                ]);
                $account->increment('current_balance', $treasuryAmount);

                // 2. Record as Customer Credit (Liabiltiy - we owe them goods)
                CustomerLedger::create([
                    'customer_id' => $this->customer_id,
                    'date' => $this->date,
                    'type' => 'payment',
                    'description' => __('Prepayment for Proforma') . ' #' . $sale->id,
                    'currency' => $this->currency,
                    'exchange_rate' => $this->exchange_rate,
                    'debit' => 0,
                    'credit' => $this->grandTotal,
                    'balance' => -$this->grandTotal,
                    'ref_type' => 'sale',
                    'ref_id' => $sale->id,
                    'created_by' => Auth::id(),
                ]);
            }
        });

        session()->flash('success', ucfirst($this->type) . ' created successfully.');
        $this->dispatch('close-sale-modal');
        $this->dispatch('sale-saved', id: $saleId);

        // Notify Admins
        $admins = User::where('role', 'admin')->get();
        try {
            $typeLabel = $this->type === 'invoice' ? 'Invoice' : ($this->type === 'quotation' ? 'Quotation' : 'Proforma');
            Notification::send($admins, new SystemNotification(
                "New {$typeLabel}",
                "A new {$this->type} (#{$saleId}) has been created.",
                $this->type === 'invoice' ? 'ri-shopping-cart-2-line' : 'ri-file-list-3-line',
                route('admin.sales.index', ['type' => $this->type]),
                'primary'
            ));
            $this->dispatch('refreshNotifications')->to('admin.notification-dropdown');
        } catch (\Exception $e) { }
    }

    public function markAsPaid($id)
    {
        $sale = Sale::findOrFail($id);
        $sale->update(['payment_status' => 'paid']);
        session()->flash('success', 'Document marked as paid.');
    }

    public function confirmConversion($id)
    {
        $this->confirmingConvertId = $id;
        $this->dispatch('open-confirm-convert-modal');
    }

    public function convertToInvoice()
    {
        if (!$this->confirmingConvertId) return;

        $sale = Sale::findOrFail($this->confirmingConvertId);
        $this->viewingSale = $sale;

        DB::transaction(function () use ($sale) {
            $sale->update(['type' => 'invoice']);

            // Now apply the Ledger and Stock movements
            foreach ($sale->items as $item) {
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'warehouse_id' => $sale->warehouse_id,
                    'qty_in' => 0,
                    'qty_out' => $item->qty,
                    'ref_type' => 'sale',
                    'ref_id' => $sale->id,
                    'note' => 'Converted to Invoice #' . $sale->id,
                    'created_by' => Auth::id(),
                ]);
            }

            CustomerLedger::create([
                'customer_id' => $sale->customer_id,
                'date' => now()->format('Y-m-d'),
                'type' => 'sale',
                'description' => 'Invoice #' . $sale->id . ' (Converted)',
                'currency' => $sale->currency,
                'exchange_rate' => $sale->exchange_rate,
                'debit' => $sale->grand_total,
                'credit' => 0,
                'balance' => $sale->grand_total,
                'ref_type' => 'sale',
                'ref_id' => $sale->id,
                'created_by' => Auth::id(),
            ]);

            // record commission if applicable
            if ($sale->employee_id) {
                $employee = Employee::find($sale->employee_id);
                if ($employee && $employee->commission_rate > 0) {
                    $commissionAmount = $sale->grand_total * ($employee->commission_rate / 100);

                    EmployeeLedger::create([
                        'employee_id' => $sale->employee_id,
                        'date' => now()->format('Y-m-d'),
                        'type' => 'commission',
                        'description' => 'Commission from Converted Invoice #' . $sale->id . ' (' . $employee->commission_rate . '%)',
                        'currency' => $sale->currency,
                        'exchange_rate' => $sale->exchange_rate,
                        'debit' => 0,
                        'credit' => $commissionAmount,
                        'balance' => $commissionAmount,
                        'ref_type' => 'sale',
                        'ref_id' => $sale->id,
                        'created_by' => Auth::id(),
                    ]);
                }
            }
        });

        session()->flash('success', 'Converted to Invoice successfully.');
        $this->dispatch('close-confirm-convert-modal');
        $this->confirmingConvertId = null;

        // Notification for conversion
        $admins = User::where('role', 'admin')->get();
        try {
            Notification::send($admins, new SystemNotification(
                "Converted to Invoice",
                "Quotation/Proforma (#{$sale->id}) has been converted to a final Invoice.",
                'ri-arrow-up-circle-line',
                route('admin.sales.index'),
                'success'
            ));
            $this->dispatch('refreshNotifications')->to('admin.notification-dropdown');
        } catch (\Exception $e) { }
    }

    public function viewSale($id)
    {
        $this->viewingSale = Sale::with(['customer', 'items.product', 'creator'])->findOrFail($id);
        $this->viewingPreviousBalance = $this->viewingSale->customer->getBalanceBeforeSale($this->viewingSale->id, $this->viewingSale->currency);
        $this->dispatch('open-view-modal');
    }

    public function directPrint($id)
    {
        $this->dispatch('trigger-direct-print', ['url' => route('admin.sales.print', $id)]);
    }

    public function render(): mixed
    {
        $sales = Sale::with('customer')
            ->where('type', $this->type)
            ->whereHas('customer', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);

        $customers = Customer::all();
        $employees = Employee::where('is_active', true)->get();
        $products = Product::where('is_active', true)->get();

        $warehouses = Warehouse::all();

        return view('components.admin.sale-management', [
            'sales' => $sales,
            'customers' => $customers,
            'employees' => $employees,
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }
};
?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="card-title mb-0">
                @if($type === 'invoice') {{ __('Sales Invoices') }}
                @elseif($type === 'quotation') {{ __('Quotations') }}
                @elseif($type === 'proforma') {{ __('Proforma Invoices') }}
                @endif
            </h5>
            <div class="d-flex gap-2">
                <input type="search" wire:model.live="search" class="form-control form-control-sm"
                    placeholder="{{ __('Search by Customer...') }}">
                <button wire:click="openCreateModal" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i>
                    @if($type === 'invoice') {{ __('New Sale') }}
                    @elseif($type === 'quotation') {{ __('New Quotation') }}
                    @elseif($type === 'proforma') {{ __('New Proforma') }}
                    @endif
                </button>
            </div>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success mt-2">{{ session('success') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Customer') }}</th>
                            <th>{{ __('Total Amount') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sales as $sale)
                            <tr>
                                <td>#{{ $sale->id }}</td>
                                <td>{{ $sale->date }}</td>
                                <td>{{ $sale->customer->name }}</td>
                                <td>
                                    <strong>{{ number_format($sale->grand_total, $sale->currency === 'USD' ? 2 : 0) }} {{ $sale->currency }}</strong>
                                    @if($sale->currency !== 'IQD')
                                        <br><small
                                            class="text-muted">{{ number_format($sale->grand_total * $sale->exchange_rate, 0) }}
                                            IQD</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $sale->payment_status == 'paid' ? 'bg-success' : 'bg-warning' }}">
                                        {{ __($sale->payment_status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <button wire:click="directPrint({{ $sale->id }})"
                                            class="btn btn-sm btn-soft-primary" title="{{ __('Print') }}">
                                            <i class="ri-printer-line"></i>
                                        </button>
                                        <button onclick="directDownload({{ $sale->id }})"
                                            class="btn btn-sm btn-soft-success" title="{{ __('Download PDF') }}">
                                            <i class="ri-download-2-line"></i>
                                        </button>
                                        <button wire:click="viewSale({{ $sale->id }})" class="btn btn-sm btn-soft-info" title="{{ __('View Details') }}"><i
                                                class="ri-eye-line"></i></button>
                                        @if($sale->payment_status !== 'paid' && $sale->type === 'invoice')
                                            <button wire:click="markAsPaid({{ $sale->id }})" class="btn btn-sm btn-soft-success"
                                                title="{{ __('Mark as Paid') }}">
                                                <i class="ri-check-double-line"></i>
                                            </button>
                                        @endif
                                        @if($sale->type !== 'invoice')
                                            <button wire:click="confirmConversion({{ $sale->id }})" 
                                                class="btn btn-sm btn-soft-warning"
                                                title="{{ __('Convert to Invoice') }}">
                                                <i class="ri-arrow-up-circle-line"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $sales->links() }}
            </div>
        </div>
    </div>

    <!-- Sale Modal -->
    <div wire:ignore.self class="modal fade" id="saleModal" tabindex="-1" aria-labelledby="saleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="saleModalLabel">
                        @if($type === 'invoice') {{ __('New Sales Invoice') }}
                        @elseif($type === 'quotation') {{ __('New Quotation') }}
                        @elseif($type === 'proforma') {{ __('New Proforma') }}
                        @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row mb-4">
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Date') }}</label>
                                <input type="date" wire:model="date" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Customer') }}</label>
                                <select wire:model="customer_id"
                                    class="form-select @error('customer_id') is-invalid @enderror">
                                    <option value="">{{ __('Select Customer') }}</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                                @error('customer_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Warehouse') }}</label>
                                <select wire:model="warehouse_id"
                                    class="form-select @error('warehouse_id') is-invalid @enderror">
                                    <option value="">{{ __('Select Warehouse') }}</option>
                                    @foreach($warehouses as $warehouse)
                                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                    @endforeach
                                </select>
                                @error('warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Salesperson (Employee)') }}</label>
                                <select wire:model="employee_id"
                                    class="form-select @error('employee_id') is-invalid @enderror">
                                    <option value="">{{ __('None') }}</option>
                                    @foreach($employees as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                    @endforeach
                                </select>
                                @error('employee_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Currency') }}</label>
                                <select wire:model.live="currency" class="form-select">
                                    <option value="USD">USD</option>
                                    <option value="IQD">IQD</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Rate (Snapshot)') }}</label>
                                <input type="number" step="1" wire:model="exchange_rate" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Payment Status') }}</label>
                                <select wire:model.live="payment_status" class="form-select">
                                    <option value="pending">{{ __('pending') }}</option>
                                    <option value="paid">{{ __('paid') }}</option>
                                </select>
                            </div>
                            @if($payment_status === 'paid')
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Deposit to Treasury') }}</label>
                                <select wire:model="financial_account_id" class="form-select @error('financial_account_id') is-invalid @enderror">
                                    @foreach(\App\Models\FinancialAccount::where('is_active', true)->get() as $fa)
                                        <option value="{{ $fa->id }}">{{ $fa->name }} ({{ $fa->currency }})</option>
                                    @endforeach
                                </select>
                                @error('financial_account_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            @endif
                        </div>

                        <div class="card border-primary-subtle shadow-none mb-4">
                            <div class="card-body bg-light-subtle">
                                <h6 class="card-title fs-13 text-primary mb-3">{{ __('Add Items') }}</h6>
                                <div class="row align-items-end g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __('Product') }}</label>
                                        <select wire:model.live="selected_product_id" class="form-select">
                                            <option value="">{{ __('Choose Product...') }}</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}">{{ $product->name }} (SKU:
                                                    {{ $product->sku }}) - {{ __('Stock') }}: {{ $product->stock }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">{{ __('Qty') }}</label>
                                        <input type="number" step="1" wire:model="item_qty"
                                            class="form-control @error('item_qty') is-invalid @enderror">
                                        @error('item_qty') <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">{{ __('Price') }}</label>
                                        <input type="number" step="{{ $currency === 'USD' ? '0.01' : '1' }}" wire:model="item_price" class="form-control">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" wire:click="addItem" class="btn btn-info w-100">
                                            <i class="ri-add-line me-1"></i> {{ __('Add') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Product') }}</th>
                                        <th class="text-center">{{ __('Qty') }}</th>
                                        <th class="text-end">{{ __('Price') }}</th>
                                        <th class="text-end">{{ __('Subtotal') }}</th>
                                        <th class="text-center"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($items as $index => $item)
                                        <tr wire:key="sale-item-{{ $index }}">
                                            <td>{{ $item['name'] }}</td>
                                            <td class="text-center" style="width: 100px;">
                                                <input type="number" step="1" wire:model.live="items.{{ $index }}.qty" class="form-control form-control-sm text-center">
                                            </td>
                                            <td class="text-end" style="width: 150px;">
                                                <input type="number" step="{{ $currency === 'USD' ? '0.01' : '1' }}" wire:model.live="items.{{ $index }}.price" class="form-control form-control-sm text-end">
                                            </td>
                                            <td class="text-end">{{ number_format($item['subtotal'], $currency === 'USD' ? 2 : 0) }}</td>
                                            <td class="text-center">
                                                <button type="button" wire:click="removeItem({{ $index }})"
                                                    class="btn btn-link link-danger p-0 pt-1">
                                                    <i class="ri-delete-bin-line fs-16"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted italic">
                                                {{ __('No items added yet.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end">{{ __('Total Amount') }}</th>
                                        <th class="text-end text-muted">{{ number_format($this->total, $currency === 'USD' ? 2 : 0) }}</th>
                                        <th></th>
                                    </tr>
                                    <tr>
                                        <th colspan="3" class="text-end">{{ __('Discount') }}</th>
                                        <th class="text-end">
                                            <input type="number" step="1" wire:model.live="discount" class="form-control form-control-sm text-end d-inline-block w-auto" style="width: 120px !important;">
                                        </th>
                                        <th></th>
                                    </tr>
                                    <tr class="table-primary">
                                        <th colspan="3" class="text-end">{{ __('Grand Total') }}</th>
                                        <th class="text-end text-primary fs-16">{{ number_format($this->grandTotal, $currency === 'USD' ? 2 : 0) }}</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Notes') }}</label>
                            <textarea wire:model="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        @error('items') <div class="text-danger me-auto small">{{ $message }}</div> @enderror
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary" {{ empty($items) ? 'disabled' : '' }}>
                            {{ __('Save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Sale Modal -->
    <div wire:ignore.self class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header bg-primary py-3">
                    <h5 class="modal-title text-white" id="viewModalLabel">
                        <i class="ri-file-text-line align-bottom me-1"></i>
                        @if($viewingSale?->type === 'invoice') {{ __('Invoice Details') }}
                        @elseif($viewingSale?->type === 'quotation') {{ __('Quotation Details') }}
                        @elseif($viewingSale?->type === 'proforma') {{ __('Proforma Details') }}
                        @endif
                        #{{ $viewingSale->id ?? '' }}
                    </h5>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" wire:click="directPrint({{ $viewingSale->id ?? 0 }})" class="btn btn-sm btn-light">
                            <i class="ri-printer-line align-bottom me-1"></i> {{ __('Print') }}
                        </button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-0">
                    <style>
                        .invoice-preview-container {
                            --print-top: 90mm;
                            --print-left: 12mm;
                            --print-width: 186mm;
                            --print-height: 185mm;
                            --row-height: 8mm;
                            background: #f0f0f0;
                            padding: 20px;
                            display: flex;
                            justify-content: center;
                            max-height: 80vh;
                            overflow-y: auto;
                        }

                        .preview-page {
                            width: 210mm;
                            min-height: 297mm;
                            background-color: white;
                            position: relative;
                            overflow: hidden;
                            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                        }

                        .preview-background {
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            z-index: 1;
                            object-fit: fill;
                        }

                        .preview-print-area {
                            position: absolute;
                            top: var(--print-top);
                            left: var(--print-left);
                            width: var(--print-width);
                            height: var(--print-height);
                            display: flex;
                            flex-direction: column;
                            font-family: 'Tahoma', sans-serif;
                            z-index: 2;
                        }

                        .preview-info-grid {
                            display: grid;
                            grid-template-columns: 1fr 1fr 1fr 1fr;
                            gap: 3mm;
                            margin-bottom: 3mm;
                            font-weight: bold;
                            color: #32267d;
                            border: 1px solid #b0a8d8;
                            background: #f3f1fb;
                            padding: 2mm;
                            border-radius: 1mm;
                            font-size: 10pt;
                            direction: rtl;
                        }

                        .preview-info-item {
                            display: flex;
                            gap: 2mm;
                            align-items: baseline;
                        }

                        .preview-info-item label {
                            white-space: nowrap;
                            color: #7a6fb0;
                            font-size: 8pt;
                        }

                        .preview-info-item span {
                            color: #32267d;
                            font-weight: 900;
                            unicode-bidi: plaintext;
                            text-align: right;
                        }

                        .preview-table {
                            width: 100%;
                            border-collapse: collapse;
                            table-layout: fixed;
                            margin-top: 1mm;
                            direction: rtl;
                        }

                        .preview-table thead th {
                            border: 1px solid #32267d;
                            background-color: #32267d;
                            color: #ffffff;
                            padding: 1.5mm 1mm;
                            text-align: center;
                            font-size: 9pt;
                        }

                        .preview-table tbody td {
                            border-right: 1px solid #b0a8d8;
                            border-left: 1px solid #b0a8d8;
                            padding: 0.5mm 1.5mm;
                            height: var(--row-height);
                            vertical-align: middle;
                            font-size: 9pt;
                            color: #32267d;
                            overflow: hidden;
                            text-align: center;
                        }

                        .preview-table tbody tr:last-child td {
                            border-bottom: 1px solid #b0a8d8;
                        }

                        .preview-table tbody tr:nth-child(even) td {
                            background-color: #f3f1fb;
                        }

                        .preview-col-no { width: 12mm; }
                        .preview-col-item { width: auto; text-align: right !important; }
                        .preview-col-qty { width: 18mm; }
                        .preview-col-price { width: 28mm; }
                        .preview-col-total { width: 32mm; }

                        .preview-summary-grid {
                            margin-top: 5mm;
                            display: grid;
                            grid-template-columns: repeat(5, 1fr);
                            border: 1px solid #32267d;
                            font-size: 8.5pt;
                            direction: rtl;
                        }

                        .preview-summary-cell {
                            border-left: 1px solid #b0a8d8;
                            padding: 1.5mm 1mm;
                            text-align: center;
                            display: flex;
                            flex-direction: column;
                            gap: 1mm;
                        }

                        .preview-summary-cell:last-child {
                            border-left: none;
                        }

                        .preview-summary-label {
                            font-weight: bold;
                            color: #32267d;
                            border-bottom: 0.5px solid #b0a8d8;
                            padding-bottom: 1mm;
                        }

                        .preview-summary-value {
                            font-weight: 800;
                            color: #32267d;
                        }

                        .preview-total-in-words {
                            grid-column: span 5;
                            padding: 2mm;
                            text-align: center;
                            font-weight: bold;
                            background: #f3f1fb;
                            border-top: 1px solid #b0a8d8;
                            color: #32267d;
                        }

                        .preview-notes-container {
                            margin-top: 4mm;
                            border: 1px solid #32267d;
                            border-radius: 1mm;
                            padding: 2mm;
                            background: #f3f1fb;
                            font-size: 9pt;
                            color: #32267d;
                            direction: rtl;
                        }
                    </style>
                    <div class="invoice-preview-container">
                        @if($viewingSale)
                            @php
                                $typeLabel = $viewingSale->type == 'invoice' ? 'Invoice' : ($viewingSale->type == 'quotation' ? 'Quotation' : 'Proforma');
                                $currencySymbol = $viewingSale->currency === 'USD' ? '$' : 'د.ع';
                            @endphp
                            <div class="preview-page">
                                <img src="{{ asset('assets/images/invois.png') }}" class="preview-background" alt="Invoice Background">
                                <div class="preview-print-area">
                                    <div class="preview-info-grid">
                                        <div class="preview-info-item"><label>رقم الفاتورة:</label> <span>{{ $viewingSale->id }}</span></div>
                                        <div class="preview-info-item"><label>الاسم:</label> <span>{{ $viewingSale->customer->name }}</span></div>
                                        <div class="preview-info-item"><label>الهاتف:</label> <span>{{ $viewingSale->customer->phone }}</span></div>
                                        <div class="preview-info-item"><label>العنوان:</label> <span>{{ $viewingSale->customer->address }}</span></div>
                                        <div class="preview-info-item"><label>التاريخ:</label> <span>{{ $viewingSale->date }}</span></div>
                                        <div class="preview-info-item"><label>العملة:</label> <span>{{ $viewingSale->currency === 'USD' ? 'دولار امريكي' : 'دينار عراقي' }}</span></div>
                                        <div class="preview-info-item"><label>نوع الفاتورة:</label> <span>{{ $typeLabel }}</span></div>
                                    </div>

                                    <table class="preview-table">
                                        <thead>
                                            <tr>
                                                <th class="preview-col-no text-white">No</th>
                                                <th class="preview-col-item text-white">Item Description</th>
                                                <th class="preview-col-qty text-white">Qty</th>
                                                <th class="preview-col-price text-white">Price</th>
                                                <th class="preview-col-total text-white">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($viewingSale->items as $index => $item)
                                                <tr>
                                                    <td class="preview-col-no">{{ $index + 1 }}</td>
                                                    <td class="preview-col-item">{{ $item->product->name }}</td>
                                                    <td class="preview-col-qty">{{ number_format($item->qty, 0) }}</td>
                                                    <td class="preview-col-price">{{ number_format($item->price, $viewingSale->currency === 'USD' ? 2 : 0) }} {{ $currencySymbol }}</td>
                                                    <td class="preview-col-total">{{ number_format($item->subtotal, $viewingSale->currency === 'USD' ? 2 : 0) }} {{ $currencySymbol }}</td>
                                                </tr>
                                            @endforeach
                                            @for($i = count($viewingSale->items); $i < 12; $i++)
                                                <tr>
                                                    <td class="preview-col-no">&nbsp;</td>
                                                    <td class="preview-col-item"></td>
                                                    <td class="preview-col-qty"></td>
                                                    <td class="preview-col-price"></td>
                                                    <td class="preview-col-total"></td>
                                                </tr>
                                            @endfor
                                        </tbody>
                                    </table>

                                    @if($viewingSale->notes)
                                        <div class="preview-notes-container">
                                            <div style="font-weight: bold; margin-bottom: 1mm;">الملاحظات / Notes:</div>
                                            <div style="white-space: pre-wrap;">{{ $viewingSale->notes }}</div>
                                        </div>
                                    @endif

                                    <div class="preview-summary-grid">
                                        <div class="preview-summary-cell">
                                            <span class="preview-summary-label">المجموع</span>
                                            <span class="preview-summary-value">{{ number_format($viewingSale->total, $viewingSale->currency === 'USD' ? 2 : 0) }} {{ $currencySymbol }}</span>
                                        </div>
                                        <div class="preview-summary-cell">
                                            <span class="preview-summary-label">الخصم</span>
                                            <span class="preview-summary-value">{{ number_format($viewingSale->discount, $viewingSale->currency === 'USD' ? 2 : 0) }} {{ $currencySymbol }}</span>
                                        </div>
                                        <div class="preview-summary-cell">
                                            <span class="preview-summary-label">المبلغ الواصل</span>
                                            <span class="preview-summary-value">{{ $viewingSale->payment_status === 'paid' ? number_format($viewingSale->grand_total, $viewingSale->currency === 'USD' ? 2 : 0) . ' ' . $currencySymbol : '0' }}</span>
                                        </div>
                                        <div class="preview-summary-cell">
                                            <span class="preview-summary-label">الرصيد السابق</span>
                                            <span class="preview-summary-value">{{ number_format($viewingPreviousBalance, $viewingSale->currency === 'USD' ? 2 : 0) }} {{ $currencySymbol }}</span>
                                        </div>
                                        <div class="preview-summary-cell">
                                            <span class="preview-summary-label">الرصيد الكلي</span>
                                            <span class="preview-summary-value">{{ number_format($viewingPreviousBalance + $viewingSale->grand_total, $viewingSale->currency === 'USD' ? 2 : 0) }} {{ $currencySymbol }}</span>
                                        </div>
                                        <div class="preview-total-in-words">
                                            {{ \App\Services\ArabicAmountToWords::translate($viewingSale->grand_total, $viewingSale->currency) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="text-center p-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Convert Modal -->
    <div wire:ignore.self class="modal fade" id="confirmConvertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header bg-warning py-3">
                    <h5 class="modal-title text-white">
                        <i class="ri-alert-line align-bottom me-1"></i> {{ __('Confirmation Required') }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-4">
                    <div class="avatar-lg mx-auto mb-3">
                        <div class="avatar-title bg-warning-subtle text-warning display-5 rounded-circle">
                            <i class="ri-refresh-line"></i>
                        </div>
                    </div>
                    <h5>{{ __('Convert to Final Invoice?') }}</h5>
                    <p class="text-muted">{{ __('Are you sure you want to convert this document to a final invoice? This will update stock and accounts.') }}</p>
                    <div class="d-flex gap-2 justify-content-center mt-4">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="button" wire:click="convertToInvoice" class="btn btn-warning px-4">{{ __('Convert Now') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Iframe for Printing -->
    <iframe id="printFrame" style="display:none;"></iframe>

    <!-- Print/Download Modal (auto-opens after saving a new invoice) -->
    <div class="modal fade" id="printDownloadModal" tabindex="-1" aria-labelledby="printDownloadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
                <div class="modal-header" style="background: #32267d; color: white; border: none;">
                    <h5 class="modal-title" id="printDownloadModalLabel">
                        ✅ تم حفظ الفاتورة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" style="padding: 30px;">
                    <p style="font-size: 13pt; margin-bottom: 25px; color: #444;">
                        الفاتورة <strong id="modalSaleId" style="color: #32267d;"></strong> — ماذا تريد أن تفعل؟
                    </p>
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                        <a id="btnPrintInvoice" href="#" target="_blank"
                           style="background: #32267d; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 11pt;">
                            🖨️ طباعة (بدون خلفية)
                        </a>
                        <a id="btnDownloadInvoice" href="#" target="_blank"
                           style="background: #1a7d4e; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 11pt;">
                            ⬇️ تحميل PDF (مع الخلفية)
                        </a>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"
                                style="padding: 12px 25px; border-radius: 8px; font-size: 11pt;">
                            تخطي
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            // Sale Create Modal
            Livewire.on('open-sale-modal', () => {
                var myModalEl = document.getElementById('saleModal');
                var modal = bootstrap.Modal.getOrCreateInstance(myModalEl);
                modal.show();
            });
            Livewire.on('close-sale-modal', () => {
                var myModalEl = document.getElementById('saleModal');
                var modal = bootstrap.Modal.getInstance(myModalEl);
                if (modal) modal.hide();
            });

            // View Sale Modal
            Livewire.on('open-view-modal', () => {
                var myModalEl = document.getElementById('viewModal');
                var modal = bootstrap.Modal.getOrCreateInstance(myModalEl);
                modal.show();
            });

            Livewire.on('open-confirm-convert-modal', () => {
                var myModalEl = document.getElementById('confirmConvertModal');
                var modal = bootstrap.Modal.getOrCreateInstance(myModalEl);
                modal.show();
            });

            Livewire.on('close-confirm-convert-modal', () => {
                var myModalEl = document.getElementById('confirmConvertModal');
                var modal = bootstrap.Modal.getInstance(myModalEl);
                if (modal) modal.hide();
            });

            // Direct Printing Logic
            Livewire.on('trigger-direct-print', (dataArray) => {
                const data = dataArray[0]; // Livewire 3 pass data in an array
                const frame = document.getElementById('printFrame');
                
                // Set frame src to the print URL
                frame.src = data.url;

                // Wait for iframe to load, then print
                frame.onload = function() {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                };
            });

            // Auto-open Print/Download modal after saving a new invoice
            Livewire.on('sale-saved', (dataArray) => {
                const saleId = dataArray[0] ?? dataArray.id ?? dataArray;
                const baseUrl = '/admin/sales/' + saleId + '/print';

                document.getElementById('modalSaleId').textContent = '#' + saleId;

                // Print button: directly trigger print via iframe (no new tab)
                document.getElementById('btnPrintInvoice').onclick = function(e) {
                    e.preventDefault();
                    directPrintUrl(baseUrl + '?autoprint=1');
                    bootstrap.Modal.getInstance(document.getElementById('printDownloadModal')).hide();
                };

                // Download button: trigger download via iframe with bg (no new tab)
                document.getElementById('btnDownloadInvoice').onclick = function(e) {
                    e.preventDefault();
                    directPrintUrl(baseUrl + '?autodownload=1');
                    bootstrap.Modal.getInstance(document.getElementById('printDownloadModal')).hide();
                };

                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('printDownloadModal'));
                modal.show();
            });
        });

        // --- Shared iframe loader for print/download ---
        function directPrintUrl(url) {
            const frame = document.getElementById('printFrame');
            frame.src = url;
            frame.onload = function() {
                frame.contentWindow.focus();
                // The page will auto-trigger print or download via its own JS
            };
        }

        // Download button in sales list row
        function directDownload(saleId) {
            directPrintUrl('/admin/sales/' + saleId + '/print?autodownload=1');
        }
    </script>
</div>