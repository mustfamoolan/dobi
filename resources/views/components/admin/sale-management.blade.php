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
    public $financial_account_id;
    public $items = []; // [{product_id, name, qty, price, subtotal}]
    public $viewingSale = null;
    public $confirmingConvertId = null;

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
        $this->reset(['customer_id', 'warehouse_id', 'employee_id', 'items', 'notes', 'selected_product_id', 'item_qty', 'item_price', 'payment_status']);
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
            $this->item_price = $product->price;
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
            'financial_account_id' => 'required_if:payment_status,paid',
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
                'grand_total' => $total,
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
                    'debit' => $total,
                    'credit' => 0,
                    'balance' => $total,
                    'ref_type' => 'sale',
                    'ref_id' => $sale->id,
                    'created_by' => Auth::id(),
                ]);

                // 4. Create Employee Commission Entry (if applicable)
                if ($this->employee_id) {
                    $employee = Employee::find($this->employee_id);
                    if ($employee && $employee->commission_rate > 0) {
                        $commissionAmount = $total * ($employee->commission_rate / 100);

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
                    $treasuryAmount = $total;

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
                        'credit' => $total,
                        'balance' => 0,
                        'ref_type' => 'sale',
                        'ref_id' => $sale->id,
                        'created_by' => Auth::id(),
                    ]);
                }
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
                                    <strong>{{ number_format($sale->grand_total, 0) }} {{ $sale->currency }}</strong>
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
                                        <input type="number" step="1" wire:model="item_price" class="form-control">
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
                                        <tr>
                                            <td>{{ $item['name'] }}</td>
                                            <td class="text-center">{{ $item['qty'] }}</td>
                                            <td class="text-end">{{ number_format($item['price'], 0) }}</td>
                                            <td class="text-end">{{ number_format($item['subtotal'], 0) }}</td>
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
                                        <th class="text-end text-primary fs-16">{{ number_format($this->total, 0) }}
                                        </th>
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
                    <div class="invoice-viewer-wrapper" style="background: #e9ecef; padding: 20px; overflow-y: auto; max-height: 80vh;">
                        @if($viewingSale)
                            <!-- Embed the same structure as print but responsive -->
                            <div class="invoice-card" style="background: white; width: 210mm; margin: 0 auto; padding: 10mm; border: 2px solid #3491d1; box-shadow: 0 5px 25px rgba(0,0,0,0.1); position: relative;">
                               
                                <!-- Header -->
                                <div style="display: grid; grid-template-columns: 25mm 1fr 50mm; gap: 5mm; align-items: center; margin-bottom: 5mm;">
                                    <div style="background-color: #3491d1; color: white; writing-mode: vertical-rl; text-orientation: mixed; display: flex; align-items: center; justify-content: center; font-size: 24pt; font-weight: 900; padding: 5mm 0; border-radius: 2mm; height: 45mm; text-align: center; transform: rotate(180deg);">INVOICE</div>
                                    <div style="text-align: center;">
                                        <h1 style="color: #0056b3; margin: 0; font-size: 22pt; font-weight: 900;">شركة علي هادي للتجارة</h1>
                                        <h2 style="color: #0056b3; margin: 0; font-size: 18pt; letter-spacing: 1px; font-weight: 900;">ALI HADI TRADING CO.</h2>
                                        <div style="color: #0056b3; font-size: 13pt; font-weight: 700; margin-top: 2mm; border-top: 1px solid #3491d1; border-bottom: 1px solid #3491d1; padding: 1mm 0;">قطع غيار الثلاجات والـمـكـيـفـات</div>
                                        <div style="color: #0056b3; font-size: 10pt; font-weight: 800; margin-bottom: 2mm;">AIR CONDITIONER & REFRIGERATION SPARE PARTS</div>
                                        <div style="font-size: 8.5pt; color: #0056b3; font-weight: 600; line-height: 1.4;">
                                            بغداد - السنك - مقابل القصر الأبيض<br>
                                            Tel: +964 1 8868996, Fax: +964 1 8868996, Mob: +964 7 902430768<br>
                                            Email: ali_hadi_trading@yahoo.com
                                        </div>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 10mm; align-items: flex-end;">
                                        <div style="border: 2px solid #3491d1; border-radius: 2mm; width: 45mm; text-align: center; overflow: hidden;">
                                            <div style="border-bottom: 1px solid #3491d1; padding: 1mm; font-weight: 700; font-size: 10pt;">نقداً / على الحساب</div>
                                            <div style="padding: 1mm; font-weight: 700; font-size: 9pt;">CASH / CREDIT</div>
                                        </div>
                                        <div style="font-size: 12pt; font-weight: 700; color: #0b2b4a;">No.{{ $viewingSale->id }}</div>
                                    </div>
                                </div>

                                <!-- Brand Logos -->
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5mm; padding: 0 10mm;">
                                    <div style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">RECO<br><small>MADE IN ITALY</small></div>
                                    <div style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">interdryers<br><small>MADE IN ITALY</small></div>
                                    <div style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">HARRIS<br><small>MADE IN USA</small></div>
                                    <div style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">P&M<br><small>MADE IN TAIWAN</small></div>
                                    <div style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">Arkema<br><small>MADE IN FRANCE</small></div>
                                    <div style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">DuPont<br><small>MADE IN USA</small></div>
                                    <div style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">RANCO<br><small>MADE IN EU</small></div>
                                    <div style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">Maksal<br><small>MADE IN SOUTH AFRICA</small></div>
                                </div>

                                <!-- Info Box -->
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10mm; margin-bottom: 5mm; font-size: 11pt;">
                                    <div style="display: flex; align-items: baseline; gap: 2mm;">
                                        <label>السيد / Mr. / M/s.:</label>
                                        <span style="flex-grow: 1; border-bottom: 1px dotted #3491d1; padding: 0 2mm; font-weight: 700;">{{ $viewingSale->customer->name }}</span>
                                    </div>
                                    <div style="display: flex; align-items: baseline; gap: 2mm; direction: ltr;">
                                        <label>Date / التاريخ:</label>
                                        <span style="flex-grow: 1; border-bottom: 1px dotted #3491d1; padding: 0 2mm; font-weight: 700; direction: rtl;">{{ $viewingSale->date }}</span>
                                    </div>
                                </div>

                                <!-- Watermark -->
                                <div style="position: absolute; top: 70%; left: 50%; transform: translate(-50%, -50%) rotate(-15deg); font-size: 40pt; font-weight: 900; color: #3491d1; white-space: nowrap; opacity: 0.05; z-index: 1; pointer-events: none;">TRUST OF GENUINE PARTS</div>

                                <!-- Table -->
                                <table style="width: 100%; border-collapse: collapse; border: 2px solid #3491d1; position: relative; z-index: 2;">
                                    <thead>
                                        <tr style="background-color: #3491d1; color: white;">
                                            <th style="border: 1px solid white; padding: 1.5mm; text-align: center; width: 10mm; font-size: 9pt;">الرقم<br><small>S.No.</small></th>
                                            <th style="border: 1px solid white; padding: 1.5mm; text-align: center; width: 22mm; font-size: 9pt;">رقم النوع<br><small>Item Code</small></th>
                                            <th style="border: 1px solid white; padding: 1.5mm; text-align: right; font-size: 9pt;">التفاصيل<br><small>Description</small></th>
                                            <th style="border: 1px solid white; padding: 1.5mm; text-align: center; width: 15mm; font-size: 9pt;">العدد<br><small>Qty.</small></th>
                                            <th style="border: 1px solid white; padding: 1.5mm; text-align: center; width: 28mm; font-size: 9pt;">السعر<br><small>Rate</small></th>
                                            <th style="border: 1px solid white; padding: 1.5mm; text-align: center; width: 32mm; font-size: 9pt;">المبلغ<br><small>Amount</small></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($viewingSale->items as $index => $item)
                                            <tr>
                                                <td style="border: 1px solid #3491d1; padding: 1.5mm; text-align: center; font-size: 9.5pt;">{{ $index + 1 }}</td>
                                                <td style="border: 1px solid #3491d1; padding: 1.5mm; text-align: center; font-size: 9.5pt;">{{ $item->product->sku ?? '' }}</td>
                                                <td style="border: 1px solid #3491d1; padding: 1.5mm; font-size: 9.5pt;">{{ $item->product->name }}</td>
                                                <td style="border: 1px solid #3491d1; padding: 1.5mm; text-align: center; font-size: 9.5pt;">{{ number_format($item->qty, 0) }}</td>
                                                <td style="border: 1px solid #3491d1; padding: 1.5mm; text-align: center; font-size: 9.5pt;">{{ number_format($item->price, 0) }}</td>
                                                <td style="border: 1px solid #3491d1; padding: 1.5mm; text-align: center; font-size: 9.5pt;">{{ number_format($item->subtotal, 0) }}</td>
                                            </tr>
                                        @endforeach
                                        @for($i = count($viewingSale->items); $i < 6; $i++)
                                            <tr>
                                                <td style="border: 1px solid #3491d1; height: 9mm;"></td>
                                                <td style="border: 1px solid #3491d1;"></td>
                                                <td style="border: 1px solid #3491d1;"></td>
                                                <td style="border: 1px solid #3491d1;"></td>
                                                <td style="border: 1px solid #3491d1;"></td>
                                                <td style="border: 1px solid #3491d1;"></td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                </table>

                                <!-- Final Words -->
                                <div style="font-size: 10pt; font-weight: 700; text-align: center; margin-top: 3mm; margin-bottom: 2mm;">
                                    {{ \App\Services\ArabicAmountToWords::translate($viewingSale->grand_total) }}
                                </div>

                                <!-- Summary Row -->
                                <div style="display: grid; grid-template-columns: 1fr 40mm 32mm; border: 2px solid #3491d1; align-items: center;">
                                    <div style="padding: 1.5mm 3mm; font-size: 9pt; font-weight: 700; text-align: center;">
                                    </div>
                                    <div style="background-color: #deeaf6; padding: 1.5mm; text-align: center; font-weight: 900; border-right: 2px solid #3491d1; border-left: 2px solid #3491d1;">
                                        Total Dinar / المجموع
                                    </div>
                                    <div style="padding: 1.5mm; text-align: center; font-weight: 900; font-size: 11pt;">
                                        {{ number_format($viewingSale->grand_total, 0) }}
                                    </div>
                                </div>

                                <!-- Terms -->
                                <div style="display: flex; justify-content: space-between; font-size: 9pt; font-weight: 700; color: #0b2b4a; margin-top: 5mm; border-top: 2px solid #3491d1; padding-top: 2mm;">
                                    <div>Goods once sold will not be taken back.<br>All Electrical items carry no guarantee.</div>
                                    <div style="text-align: center; color: #0056b3; font-size: 10pt;">TRUST OF<br>GENUINE PARTS</div>
                                    <div style="text-align: left;">البضاعة المباعة لا ترد ولا تستبدل<br>كافة الأدوات الكهربائية غير مضمونة</div>
                                </div>

                                <!-- Signatures -->
                                <div style="display: flex; justify-content: space-between; padding: 5mm 10mm 0; font-size: 10pt; font-weight: 700;">
                                    <div>Receiver's Signature / توقيع المستلم</div>
                                    <div>For ALI HADI TRADING CO.</div>
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