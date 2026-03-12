<?php

use App\Models\Supplier;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\SupplierLedger;
use App\Models\AppSetting;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SystemNotification;
use App\Models\User;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $showCreateModal = false;

    // Purchase Form Fields
    public $date;
    public $supplier_id;
    public $warehouse_id;
    public $currency = 'USD';
    public $exchange_rate;
    public $notes;
    public $payment_status = 'pending';
    public $financial_account_id;
    public $items = []; // [{product_id, name, qty, cost, subtotal}]
    public $viewingPurchase = null;

    // Payment Modal Fields
    public $selectedPurchaseId;
    public $paymentTotal = 0;
    public $paymentAmount = 0;
    public $remainingAmount = 0;
    public $paymentAccountId;
    public $paymentCurrency;
    public $paymentExchangeRate;

    // Item Addition Fields
    public $selected_product_id;
    public $item_qty = 1;
    public $item_cost = 0;
    public $item_price = 0;

    protected $paginationTheme = 'bootstrap';

    public function mount()
    {
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
        $this->reset(['supplier_id', 'warehouse_id', 'items', 'notes', 'selected_product_id', 'item_qty', 'item_cost', 'payment_status']);
        $this->date = now()->format('Y-m-d');
        // Default to first warehouse
        $this->warehouse_id = \App\Models\Warehouse::first()->id ?? null;
        $setting = AppSetting::first();
        $this->exchange_rate = $setting->exchange_rate ?? 1500;
        $this->financial_account_id = \App\Models\FinancialAccount::where('is_active', true)->first()?->id;
        $this->showCreateModal = true;
        $this->dispatch('open-purchase-modal');
    }

    public function updatedSelectedProductId($id)
    {
        if ($id) {
            $product = Product::find($id);
            if (!$product) return;

            $product_cost = $product->cost;
            $product_price = $product->price;
            $product_currency = $product->currency ?? 'IQD';

            // Convert product cost (in its base currency) to the purchase currency
            if ($this->currency === $product_currency) {
                $this->item_cost = $product_cost;
                $this->item_price = $product_price;
            } elseif ($this->currency === 'USD' && $product_currency === 'IQD') {
                $this->item_cost = $this->exchange_rate > 0 ? round($product_cost / $this->exchange_rate, 2) : 0;
                $this->item_price = $this->exchange_rate > 0 ? round($product_price / $this->exchange_rate, 2) : 0;
            } elseif ($this->currency === 'IQD' && $product_currency === 'USD') {
                $this->item_cost = round($product_cost * $this->exchange_rate, 0);
                $this->item_price = round($product_price * $this->exchange_rate, 0);
            }
        }
    }

    public function addItem()
    {
        $this->validate([
            'selected_product_id' => 'required|exists:products,id',
            'item_qty' => 'required|numeric|min:0.001',
            'item_cost' => 'required|numeric|min:0',
            'item_price' => 'required|numeric|min:0',
        ]);

        $product = Product::find($this->selected_product_id);

        $this->items[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'qty' => $this->item_qty,
            'cost' => $this->item_cost,
            'price' => $this->item_price,
            'subtotal' => $this->item_qty * $this->item_cost,
        ];

        $this->reset(['selected_product_id', 'item_qty', 'item_cost']);
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
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'currency' => 'required|string',
            'exchange_rate' => 'required|numeric|min:1',
            'items' => 'required|array|min:1',
            'financial_account_id' => 'required_if:payment_status,paid',
        ]);

        try {
            DB::beginTransaction();
            $total = $this->total;

            // 1. Create Purchase record
            $purchase = Purchase::create([
                'date' => $this->date,
                'supplier_id' => $this->supplier_id,
                'warehouse_id' => $this->warehouse_id,
                'currency' => $this->currency,
                'exchange_rate' => $this->exchange_rate,
                'total' => $total,
                'grand_total' => $total,
                'payment_status' => $this->payment_status,
                'notes' => $this->notes,
                'created_by' => Auth::id(),
            ]);

            // 2. Create Items & Stock Movements
            foreach ($this->items as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'cost' => $item['cost'],
                    'subtotal' => $item['subtotal'],
                ]);

                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $this->warehouse_id,
                    'qty_in' => $item['qty'],
                    'qty_out' => 0,
                    'ref_type' => 'purchase',
                    'ref_id' => $purchase->id,
                    'note' => 'Purchase Invoice #' . $purchase->id,
                    'created_by' => Auth::id(),
                ]);

                // Update Product Cost and Price (Convert to product's base currency)
                $product = Product::find($item['product_id']);
                $product_currency = $product->currency ?? 'IQD';

                $finalCost = $item['cost'];
                $finalPrice = $item['price'];

                if ($this->currency !== $product_currency) {
                    if ($this->currency === 'USD' && $product_currency === 'IQD') {
                        // Purchase in USD, Product in IQD -> Convert to IQD
                        $finalCost = $item['cost'] * $this->exchange_rate;
                        $finalPrice = $item['price'] * $this->exchange_rate;
                    } elseif ($this->currency === 'IQD' && $product_currency === 'USD') {
                        // Purchase in IQD, Product in USD -> Convert to USD
                        $finalCost = $this->exchange_rate > 0 ? ($item['cost'] / $this->exchange_rate) : 0;
                        $finalPrice = $this->exchange_rate > 0 ? ($item['price'] / $this->exchange_rate) : 0;
                    }
                }

                $product->update([
                    'cost' => $finalCost,
                    'price' => $finalPrice,
                ]);
            }

            // 3. Create Supplier Ledger Entry (Credit = we owe them money)
            SupplierLedger::create([
                'supplier_id' => $this->supplier_id,
                'date' => $this->date,
                'type' => 'purchase',
                'description' => 'Purchase Invoice #' . $purchase->id,
                'currency' => $this->currency,
                'exchange_rate' => $this->exchange_rate,
                'debit' => 0,
                'credit' => $total,
                'balance' => $total,
                'ref_type' => 'purchase',
                'ref_id' => $purchase->id,
                'created_by' => Auth::id(),
            ]);

            // 4. If Paid, record in Treasury and debit supplier
            if ($this->payment_status === 'paid') {
                $account = \App\Models\FinancialAccount::findOrFail($this->financial_account_id);
                $treasuryAmount = $total;

                // Record in Treasury
                \App\Models\AccountLedger::create([
                    'account_id' => $this->financial_account_id,
                    'date' => $this->date,
                    'description' => __('Cash Purchase') . ' #' . $purchase->id . ' (' . $purchase->supplier->name . ')',
                    'debit' => 0,
                    'credit' => $treasuryAmount,
                    'balance' => $account->current_balance - $treasuryAmount,
                    'ref_type' => 'purchase',
                    'ref_id' => $purchase->id,
                    'created_by' => Auth::id(),
                ]);
                $account->decrement('current_balance', $treasuryAmount);

                // Debit Supplier (to cancel the credit from the purchase)
                SupplierLedger::create([
                    'supplier_id' => $this->supplier_id,
                    'date' => $this->date,
                    'type' => 'payment',
                    'description' => __('Payment for Purchase') . ' #' . $purchase->id,
                    'currency' => $this->currency,
                    'exchange_rate' => $this->exchange_rate,
                    'debit' => $total,
                    'credit' => 0,
                    'balance' => 0,
                    'ref_type' => 'purchase',
                    'ref_id' => $purchase->id,
                    'created_by' => Auth::id(),
                ]);
            }

            DB::commit();
            session()->flash('success', 'Purchase invoice created successfully.');
            $this->dispatch('close-purchase-modal');
            $this->dispatch('purchase-saved', id: $purchase->id);
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error saving purchase: ' . $e->getMessage());
            return;
        }

        // Notify Admins
        $admins = User::where('role', 'admin')->get();
        try {
            Notification::send($admins, new SystemNotification(
                "New Purchase",
                "A new purchase (#{$purchase->id}) has been created from " . ($purchase->supplier->name ?? 'Unknown Supplier'),
                'ri-bill-line',
                route('admin.purchases.index'),
                'warning'
            ));
            $this->dispatch('refreshNotifications')->to('admin.notification-dropdown');
        } catch (\Exception $e) { }
    }

    public function openPaymentModal($id)
    {
        $purchase = Purchase::findOrFail($id);
        $this->selectedPurchaseId = $id;
        $this->paymentTotal = $purchase->grand_total;
        $this->paymentAmount = $purchase->grand_total;
        $this->remainingAmount = 0;
        $this->paymentCurrency = $purchase->currency;
        $this->paymentExchangeRate = $purchase->exchange_rate;
        $this->paymentAccountId = \App\Models\FinancialAccount::where('is_active', true)->first()?->id;
        $this->dispatch('open-payment-modal');
    }

    public function updatedPaymentAmount()
    {
        $this->remainingAmount = max(0, $this->paymentTotal - floatval($this->paymentAmount));
    }

    public function submitPayment()
    {
        $this->validate([
            'paymentAccountId' => 'required|exists:financial_accounts,id',
            'paymentAmount' => 'required|numeric|min:0',
        ]);

        $purchase = Purchase::findOrFail($this->selectedPurchaseId);

        try {
            DB::beginTransaction();

            $account = \App\Models\FinancialAccount::findOrFail($this->paymentAccountId);
            
            // 1. Create Supplier Ledger Entry (Debit)
            SupplierLedger::create([
                'supplier_id' => $purchase->supplier_id,
                'date' => now()->format('Y-m-d'),
                'type' => 'payment',
                'description' => __('Payment for Purchase') . ' #' . $purchase->id,
                'currency' => $this->paymentCurrency,
                'exchange_rate' => $this->paymentExchangeRate,
                'debit' => $this->paymentAmount,
                'credit' => 0,
                'balance' => 0, // This model doesn't seem to use running balance
                'ref_type' => 'purchase',
                'ref_id' => $purchase->id,
                'created_by' => Auth::id(),
            ]);

            // 2. Record in Treasury (Credit)
            \App\Models\AccountLedger::create([
                'account_id' => $this->paymentAccountId,
                'date' => now()->format('Y-m-d'),
                'description' => __('Payment for Purchase') . ' #' . $purchase->id,
                'debit' => 0,
                'credit' => $this->paymentAmount,
                'balance' => $account->current_balance - $this->paymentAmount,
                'ref_type' => 'purchase',
                'ref_id' => $purchase->id,
                'created_by' => Auth::id(),
            ]);
            
            $account->decrement('current_balance', $this->paymentAmount);

            // 3. Update Purchase status if fully paid
            if ($this->remainingAmount <= 0) {
                $purchase->update(['payment_status' => 'paid']);
            }

            DB::commit();
            session()->flash('success', __('Payment recorded successfully.'));
            $this->dispatch('close-payment-modal');
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', __('Error recording payment: ') . $e->getMessage());
        }
    }

    public function markAsPaid($id)
    {
        $this->openPaymentModal($id);
    }

    public function viewPurchase($id)
    {
        $this->viewingPurchase = Purchase::with(['supplier', 'items.product', 'creator'])->findOrFail($id);
        $this->dispatch('open-view-modal');
    }

    public function directPrint($id)
    {
        $this->dispatch('trigger-direct-print', ['url' => route('admin.purchases.print', $id)]);
    }

    public function render(): mixed
    {
        $purchases = Purchase::with('supplier')
            ->whereHas('supplier', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->paginate(10);

        $suppliers = Supplier::all();
        $products = Product::where('is_active', true)->get();
        $warehouses = \App\Models\Warehouse::where('is_active', true)->get();

        return view('components.admin.purchase-management', [
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }
};
?>

<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h5 class="card-title mb-0">{{ __('Purchase Invoices') }}</h5>
            <div class="d-flex gap-2">
                <input type="search" wire:model.live="search" class="form-control form-control-sm"
                    placeholder="{{ __('Search by Supplier...') }}">
                <button wire:click="openCreateModal" class="btn btn-primary btn-sm">
                    <i class="ri-add-line align-bottom me-1"></i> {{ __('New Purchase') }}
                </button>
            </div>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success mt-2">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger mt-2">{{ session('error') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-hover align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Supplier') }}</th>
                            <th>{{ __('Total Amount') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchases as $purchase)
                            <tr>
                                <td>{{ $purchase->id }}</td>
                                <td>{{ $purchase->date }}</td>
                                <td>{{ $purchase->supplier->name }}</td>
                                <td>
                                    <strong>{{ number_format($purchase->grand_total, $purchase->currency === 'USD' ? 2 : 0) }}
                                        {{ $purchase->currency }}</strong>
                                    @if($purchase->currency !== 'IQD')
                                        <br><small
                                            class="text-muted">{{ number_format($purchase->grand_total * $purchase->exchange_rate, 0) }}
                                            IQD</small>
                                    @endif
                                </td>
                                <td>
                                    <span
                                        class="badge {{ $purchase->payment_status == 'paid' ? 'bg-success' : 'bg-warning' }}">
                                        {{ __($purchase->payment_status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <button wire:click="directPrint({{ $purchase->id }})"
                                            class="btn btn-sm btn-soft-primary" title="{{ __('Print') }}">
                                            <i class="ri-printer-line"></i>
                                        </button>
                                        <button onclick="directDownload({{ $purchase->id }})"
                                            class="btn btn-sm btn-soft-success" title="{{ __('Download PDF') }}">
                                            <i class="ri-download-2-line"></i>
                                        </button>
                                        <button wire:click="viewPurchase({{ $purchase->id }})"
                                            class="btn btn-sm btn-soft-info" title="{{ __('View Details') }}"><i
                                                class="ri-eye-line"></i></button>
                                        @if($purchase->payment_status !== 'paid')
                                            <button wire:click="markAsPaid({{ $purchase->id }})"
                                                class="btn btn-sm btn-soft-success" title="{{ __('Mark as Paid') }}">
                                                <i class="ri-check-double-line"></i>
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
                {{ $purchases->links() }}
            </div>
        </div>
    </div>

    <!-- Purchase Modal -->
    <div wire:ignore.self class="modal fade" id="purchaseModal" tabindex="-1" aria-labelledby="purchaseModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="purchaseModalLabel">{{ __('New Purchase Invoice') }}</h5>
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
                                <label class="form-label">{{ __('Supplier') }}</label>
                                <select wire:model="supplier_id"
                                    class="form-select @error('supplier_id') is-invalid @enderror">
                                    <option value="">{{ __('Select Supplier') }}</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                                @error('supplier_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                                <label class="form-label">{{ __('Pay from Treasury') }}</label>
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
                                                    {{ $product->sku }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">{{ __('Qty') }}</label>
                                        <input type="number" step="1" wire:model="item_qty" class="form-control">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">{{ __('Cost') }}</label>
                                        <input type="number" step="1" wire:model="item_cost" class="form-control">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">{{ __('Price') }}</label>
                                        <input type="number" step="1" wire:model="item_price" class="form-control">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" wire:click="addItem" class="btn btn-info w-100 p-2">
                                            <i class="ri-add-line"></i>
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
                                        <th class="text-end">{{ __('Cost') }}</th>
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
                                            <td class="text-end">{{ number_format($item['cost'], $currency === 'USD' ? 2 : 0) }}</td>
                                            <td class="text-end">{{ number_format($item['price'], $currency === 'USD' ? 2 : 0) }}</td>
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
                                        <th colspan="4" class="text-end">{{ __('Total Amount') }}</th>
                                        <th class="text-end text-primary fs-16">{{ number_format($this->total, $currency === 'USD' ? 2 : 0) }}
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
                        <button type="submit" class="btn btn-primary" {{ empty($items) ? 'disabled' : '' }}>{{ __('Save Purchase') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div wire:ignore.self class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success">
                    <h5 class="modal-title text-white" id="paymentModalLabel">{{ __('Record Payment') }} - #{{ $selectedPurchaseId }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="submitPayment">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">{{ __('Total Amount') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control bg-light" value="{{ number_format($paymentTotal, $paymentCurrency === 'USD' ? 2 : 0) }}" readonly>
                                    <span class="input-group-text">{{ $paymentCurrency }}</span>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">{{ __('Pay from Treasury') }}</label>
                                <select wire:model="paymentAccountId" class="form-select @error('paymentAccountId') is-invalid @enderror">
                                    <option value="">{{ __('Select Treasury') }}</option>
                                    @foreach(\App\Models\FinancialAccount::where('is_active', true)->get() as $fa)
                                        <option value="{{ $fa->id }}">{{ $fa->name }} ({{ $fa->currency }})</option>
                                    @endforeach
                                </select>
                                @error('paymentAccountId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">{{ __('Paid Amount') }}</label>
                                <div class="input-group">
                                    <input type="number" step="{{ $paymentCurrency === 'USD' ? '0.01' : '1' }}" wire:model.live="paymentAmount" class="form-control @error('paymentAmount') is-invalid @enderror">
                                    <span class="input-group-text">{{ $paymentCurrency }}</span>
                                </div>
                                @error('paymentAmount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">{{ __('Remaining') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control bg-light {{ $remainingAmount > 0 ? 'text-danger fw-bold' : 'text-success fw-bold' }}" value="{{ number_format($remainingAmount, $paymentCurrency === 'USD' ? 2 : 0) }}" readonly>
                                    <span class="input-group-text">{{ $paymentCurrency }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-success">{{ __('Confirm Payment') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Purchase Modal -->
    <div wire:ignore.self class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header bg-primary py-3">
                    <h5 class="modal-title text-white" id="viewModalLabel">
                        <i class="ri-file-text-line align-bottom me-1"></i> {{ __('Purchase Details') }}
                        #{{ $viewingPurchase->id ?? '' }}
                    </h5>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" wire:click="directPrint({{ $viewingPurchase->id ?? 0 }})"
                            class="btn btn-sm btn-light">
                            <i class="ri-printer-line align-bottom me-1"></i> {{ __('Print') }}
                        </button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-0">
                    <style>
                        .invoice-preview-container {
                            background: #f0f2f5;
                            padding: 2mm;
                            display: flex;
                            justify-content: center;
                            min-height: 80vh;
                        }

                        .preview-page {
                            width: 210mm;
                            height: 297mm;
                            background: white;
                            position: relative;
                            box-shadow: 0 0 20px rgba(0,0,0,0.1);
                            overflow: hidden;
                        }

                        .preview-background {
                            position: absolute;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            object-fit: fill;
                            z-index: 1;
                        }

                        .preview-print-area {
                            position: absolute;
                            top: 90mm;
                            left: 12mm;
                            width: 186mm;
                            height: 185mm;
                            z-index: 2;
                        }

                        .preview-info-grid {
                            display: grid;
                            grid-template-columns: 1.2fr 1fr 1fr;
                            grid-template-rows: auto auto;
                            gap: 2mm 3mm;
                            margin-bottom: 3mm;
                            font-weight: bold;
                            color: #32267d;
                            border: 1px solid #b0a8d8;
                            background: #f3f1fb;
                            padding: 2.5mm;
                            border-radius: 1mm;
                            align-items: center;
                            font-size: 10pt;
                            direction: rtl;
                        }

                        .preview-info-item {
                            display: flex;
                            gap: 1.5mm;
                            align-items: center;
                        }

                        .preview-info-item.id-cell {
                            font-size: 16pt;
                            font-weight: 400;
                        }

                        .preview-info-item.type-cell {
                            justify-content: center;
                            font-size: 11pt;
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
                            height: 9mm;
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
                        @if($viewingPurchase)
                            @php
                                $currencySymbol = $viewingPurchase->currency === 'USD' ? '$' : 'د.ع';
                            @endphp
                            <div class="preview-page">
                                <img src="{{ asset('assets/images/invois.png') }}" class="preview-background" alt="Invoice Background">
                                <div class="preview-print-area">
                                    <div class="preview-info-grid">
                                        <!-- Row 1 -->
                                        <div class="preview-info-item id-cell"><span>{{ $viewingPurchase->id }}</span></div>
                                        <div class="preview-info-item" style="justify-content: center;"><label>العنوان:</label> <span>{{ $viewingPurchase->supplier->address }}</span></div>
                                        <div class="preview-info-item" style="justify-content: flex-end;"><label>الاسم:</label> <span>{{ $viewingPurchase->supplier->name }}</span></div>
                                        
                                        <!-- Row 2 -->
                                        <div class="preview-info-item"><label>التاريخ:</label> <span>{{ $viewingPurchase->date }}</span></div>
                                        <div class="preview-info-item type-cell"><span>{{ __('Purchase Invoice') }}</span></div>
                                        <div class="preview-info-item" style="justify-content: flex-end;"><label>الهاتف:</label> <span>{{ $viewingPurchase->supplier->phone }}</span></div>
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
                                            @foreach($viewingPurchase->items as $index => $item)
                                                <tr>
                                                    <td class="preview-col-no">{{ $index + 1 }}</td>
                                                    <td class="preview-col-item">{{ $item->product->name }}</td>
                                                    <td class="preview-col-qty">{{ number_format($item->qty, 0) }}</td>
                                                    <td class="preview-col-price">{{ number_format($item->cost, $viewingPurchase->currency === 'USD' ? 2 : 0) }} {{ $currencySymbol }}</td>
                                                    <td class="preview-col-total">{{ number_format($item->subtotal, $viewingPurchase->currency === 'USD' ? 2 : 0) }} {{ $currencySymbol }}</td>
                                                </tr>
                                            @endforeach
                                            @for($i = count($viewingPurchase->items); $i < 12; $i++)
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

                                    @if($viewingPurchase->notes)
                                        <div class="preview-notes-container">
                                            <div style="font-weight: bold; margin-bottom: 1mm;">الملاحظات / Notes:</div>
                                            <div style="white-space: pre-wrap;">{{ $viewingPurchase->notes }}</div>
                                        </div>
                                    @endif

                                    <div class="preview-summary-grid">
                                        <div class="preview-summary-cell">
                                            <span class="preview-summary-label">المجموع</span>
                                            <span class="preview-summary-value">{{ number_format($viewingPurchase->total, $viewingPurchase->currency === 'USD' ? 2 : 0) }} {{ $currencySymbol }}</span>
                                        </div>
                                        <div class="preview-summary-cell">
                                            <span class="preview-summary-label">الخصم</span>
                                            <span class="preview-summary-value">{{ number_format($viewingPurchase->discount, $viewingPurchase->currency === 'USD' ? 2 : 0) }} {{ $currencySymbol }}</span>
                                        </div>
                                        <div class="preview-summary-cell">
                                            <span class="preview-summary-label">المبلغ الواصل</span>
                                            <span class="preview-summary-value">{{ $viewingPurchase->payment_status === 'paid' ? number_format($viewingPurchase->grand_total, $viewingPurchase->currency === 'USD' ? 2 : 0) . ' ' . $currencySymbol : '0' }}</span>
                                        </div>
                                        <div class="preview-summary-cell">
                                            <span class="preview-summary-label">الضريبة</span>
                                            <span class="preview-summary-value">{{ number_format($viewingPurchase->tax ?? 0, $viewingPurchase->currency === 'USD' ? 2 : 0) }} {{ $currencySymbol }}</span>
                                        </div>
                                        <div class="preview-summary-cell">
                                            <span class="preview-summary-label">الرصيد الكلي</span>
                                            <span class="preview-summary-value">{{ number_format($viewingPurchase->grand_total, $viewingPurchase->currency === 'USD' ? 2 : 0) }} {{ $currencySymbol }}</span>
                                        </div>
                                        <div class="preview-total-in-words">
                                            {{ \App\Services\ArabicAmountToWords::translate($viewingPurchase->grand_total, $viewingPurchase->currency) }}
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

    <!-- Hidden Iframe for Printing -->
    <iframe id="printFrame" style="display:none;"></iframe>

    <!-- Print/Download Modal -->
    <div class="modal fade" id="printDownloadModal" tabindex="-1" aria-labelledby="printDownloadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 12px; overflow: hidden;">
                <div class="modal-header" style="background: #32267d; color: white; border: none;">
                    <h5 class="modal-title" id="printDownloadModalLabel">
                        ✅ تم حفظ فاتورة الشراء
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" style="padding: 30px;">
                    <p style="font-size: 13pt; margin-bottom: 25px; color: #444;">
                        فاتورة شراء <strong id="modalPurchaseId" style="color: #32267d;"></strong> — ماذا تريد أن تفعل؟
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

    @script
    <script>
        $wire.on('open-purchase-modal', () => {
            var myModalEl = document.getElementById('purchaseModal');
            var modal = bootstrap.Modal.getOrCreateInstance(myModalEl);
            modal.show();
        });
        $wire.on('close-purchase-modal', () => {
            var myModalEl = document.getElementById('purchaseModal');
            var modal = bootstrap.Modal.getInstance(myModalEl);
            if (modal) modal.hide();
        });

        // View Purchase Modal
        $wire.on('open-view-modal', () => {
            var myModalEl = document.getElementById('viewModal');
            var modal = bootstrap.Modal.getOrCreateInstance(myModalEl);
            modal.show();
        });

        // Direct Printing Logic
        $wire.on('trigger-direct-print', (dataArray) => {
            const data = dataArray[0];
            const frame = document.getElementById('printFrame');
            frame.src = data.url;
            frame.onload = function () {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            };
        });

        // Auto-open Print/Download modal
        $wire.on('purchase-saved', (dataArray) => {
            const purchaseId = dataArray[0] ?? dataArray.id ?? dataArray;
            const baseUrl = '/admin/purchases/' + purchaseId + '/print';

            document.getElementById('modalPurchaseId').textContent = '#' + purchaseId;

            document.getElementById('btnPrintInvoice').onclick = function(e) {
                e.preventDefault();
                window.directPrintUrl(baseUrl + '?autoprint=1');
                bootstrap.Modal.getInstance(document.getElementById('printDownloadModal')).hide();
            };

            document.getElementById('btnDownloadInvoice').onclick = function(e) {
                e.preventDefault();
                window.directPrintUrl(baseUrl + '?autodownload=1');
                bootstrap.Modal.getInstance(document.getElementById('printDownloadModal')).hide();
            };

            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('printDownloadModal'));
            modal.show();
        });

        // Payment Modal
        $wire.on('open-payment-modal', () => {
            let modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentModal'));
            modal.show();
        });
        $wire.on('close-payment-modal', () => {
            let modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
            if (modal) modal.hide();
        });

        window.directPrintUrl = function(url) {
            const frame = document.getElementById('printFrame');
            frame.src = url;
            frame.onload = function() {
                frame.contentWindow.focus();
            };
        }

        window.directDownload = function(purchaseId) {
            window.directPrintUrl('/admin/purchases/' + purchaseId + '/print?autodownload=1');
        }
    </script>
    @endscript
</div>