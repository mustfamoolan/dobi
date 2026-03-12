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

    public function markAsPaid($id)
    {
        $purchase = Purchase::findOrFail($id);
        $purchase->update(['payment_status' => 'paid']);
        session()->flash('success', 'Purchase marked as paid.');
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
                    <div class="invoice-viewer-wrapper"
                        style="background: #e9ecef; padding: 20px; overflow-y: auto; max-height: 80vh;">
                        @if($viewingPurchase)
                            <div class="invoice-card"
                                style="background: white; width: 210mm; margin: 0 auto; padding: 10mm; border: 2px solid #3491d1; box-shadow: 0 5px 25px rgba(0,0,0,0.1); position: relative;">

                                <!-- Header -->
                                <div
                                    style="display: grid; grid-template-columns: 25mm 1fr 50mm; gap: 5mm; align-items: center; margin-bottom: 5mm;">
                                    <div
                                        style="background-color: #3491d1; color: white; writing-mode: vertical-rl; text-orientation: mixed; display: flex; align-items: center; justify-content: center; font-size: 20pt; font-weight: 900; padding: 5mm 0; border-radius: 2mm; height: 45mm; text-align: center; transform: rotate(180deg);">
                                        PURCHASE</div>
                                    <div style="text-align: center;">
                                        <h1 style="color: #0056b3; margin: 0; font-size: 22pt; font-weight: 900;">شركة علي
                                            هادي للتجارة</h1>
                                        <h2
                                            style="color: #0056b3; margin: 0; font-size: 18pt; letter-spacing: 1px; font-weight: 900;">
                                            ALI HADI TRADING CO.</h2>
                                        <div
                                            style="color: #0056b3; font-size: 13pt; font-weight: 700; margin-top: 2mm; border-top: 1px solid #3491d1; border-bottom: 1px solid #3491d1; padding: 1mm 0;">
                                            قطع غيار الثلاجات والـمـكـيـفـات</div>
                                        <div style="color: #0056b3; font-size: 10pt; font-weight: 800; margin-bottom: 2mm;">
                                            AIR CONDITIONER & REFRIGERATION SPARE PARTS</div>
                                        <div style="font-size: 8.5pt; color: #0056b3; font-weight: 600; line-height: 1.4;">
                                            بغداد - السنك - مقابل القصر الأبيض<br>
                                            Tel: +964 1 8868996, Fax: +964 1 8868996, Mob: +964 7 902430768<br>
                                            Email: ali_hadi_trading@yahoo.com
                                        </div>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 10mm; align-items: flex-end;">
                                        <div
                                            style="border: 2px solid #3491d1; border-radius: 2mm; width: 45mm; text-align: center; overflow: hidden;">
                                            <div
                                                style="border-bottom: 1px solid #3491d1; padding: 1mm; font-weight: 700; font-size: 10pt;">
                                                نقداً / على الحساب</div>
                                            <div style="padding: 1mm; font-weight: 700; font-size: 9pt;">CASH / CREDIT</div>
                                        </div>
                                        <div style="font-size: 12pt; font-weight: 700; color: #0b2b4a;">
                                            No.{{ $viewingPurchase->id }}</div>
                                    </div>
                                </div>

                                <!-- Brand Logos -->
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5mm; padding: 0 10mm;">
                                    <div
                                        style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">
                                        RECO<br><small>MADE IN ITALY</small></div>
                                    <div
                                        style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">
                                        interdryers<br><small>MADE IN ITALY</small></div>
                                    <div
                                        style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">
                                        HARRIS<br><small>MADE IN USA</small></div>
                                    <div
                                        style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">
                                        P&M<br><small>MADE IN TAIWAN</small></div>
                                    <div
                                        style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">
                                        Arkema<br><small>MADE IN FRANCE</small></div>
                                    <div
                                        style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">
                                        DuPont<br><small>MADE IN USA</small></div>
                                    <div
                                        style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">
                                        RANCO<br><small>MADE IN EU</small></div>
                                    <div
                                        style="font-size: 8pt; font-weight: 900; color: #0056b3; text-align: center; opacity: 0.8;">
                                        Maksal<br><small>MADE IN SOUTH AFRICA</small></div>
                                </div>

                                <!-- Info Box -->
                                <div
                                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 10mm; margin-bottom: 5mm; font-size: 11pt;">
                                    <div style="display: flex; align-items: baseline; gap: 2mm;">
                                        <label>السيد / Supplier / السيد:</label>
                                        <span
                                            style="flex-grow: 1; border-bottom: 1px dotted #3491d1; padding: 0 2mm; font-weight: 700;">{{ $viewingPurchase->supplier->name }}</span>
                                    </div>
                                    <div style="display: flex; align-items: baseline; gap: 2mm; direction: ltr;">
                                        <label>Date / التاريخ:</label>
                                        <span
                                            style="flex-grow: 1; border-bottom: 1px dotted #3491d1; padding: 0 2mm; font-weight: 700; direction: rtl;">{{ $viewingPurchase->date }}</span>
                                    </div>
                                </div>

                                <!-- Watermark -->
                                <div
                                    style="position: absolute; top: 70%; left: 50%; transform: translate(-50%, -50%) rotate(-15deg); font-size: 40pt; font-weight: 900; color: #3491d1; white-space: nowrap; opacity: 0.05; z-index: 1; pointer-events: none;">
                                    TRUST OF GENUINE PARTS</div>

                                <!-- Table -->
                                <table
                                    style="width: 100%; border-collapse: collapse; border: 2px solid #3491d1; position: relative; z-index: 2;">
                                    <thead>
                                        <tr style="background-color: #3491d1; color: white;">
                                            <th
                                                style="border: 1px solid white; padding: 1.5mm; text-align: center; width: 10mm; font-size: 9pt;">
                                                الرقم<br><small>S.No.</small></th>
                                            <th
                                                style="border: 1px solid white; padding: 1.5mm; text-align: center; width: 22mm; font-size: 9pt;">
                                                رقم النوع<br><small>Item Code</small></th>
                                            <th
                                                style="border: 1px solid white; padding: 1.5mm; text-align: right; font-size: 9pt;">
                                                التفاصيل<br><small>Description</small></th>
                                            <th
                                                style="border: 1px solid white; padding: 1.5mm; text-align: center; width: 14mm; font-size: 9pt;">
                                                العدد<br><small>Qty.</small></th>
                                            <th
                                                style="border: 1px solid white; padding: 1.5mm; text-align: center; width: 28mm; font-size: 9pt;">
                                                السعر<br><small>Cost</small></th>
                                            <th
                                                style="border: 1px solid white; padding: 1.5mm; text-align: center; width: 32mm; font-size: 9pt;">
                                                المبلغ<br><small>Amount</small></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($viewingPurchase->items as $index => $item)
                                            <tr>
                                                <td
                                                    style="border: 1px solid #3491d1; padding: 1.5mm; text-align: center; font-size: 9.5pt;">
                                                    {{ $index + 1 }}</td>
                                                <td
                                                    style="border: 1px solid #3491d1; padding: 1.5mm; text-align: center; font-size: 9.5pt;">
                                                    {{ $item->product->sku ?? '' }}</td>
                                                <td style="border: 1px solid #3491d1; padding: 1.5mm; font-size: 9.5pt;">
                                                    {{ $item->product->name }}</td>
                                                <td
                                                    style="border: 1px solid #3491d1; padding: 1.5mm; text-align: center; font-size: 9.5pt;">
                                                    {{ number_format($item->qty, 0) }}</td>
                                                <td
                                                    style="border: 1px solid #3491d1; padding: 1.5mm; text-align: center; font-size: 9.5pt;">
                                                    {{ number_format($item->cost, $viewingPurchase->currency === 'USD' ? 2 : 0) }}</td>
                                                <td
                                                    style="border: 1px solid #3491d1; padding: 1.5mm; text-align: center; font-size: 9.5pt;">
                                                    {{ number_format($item->subtotal, $viewingPurchase->currency === 'USD' ? 2 : 0) }}</td>
                                            </tr>
                                        @endforeach
                                        @for($i = count($viewingPurchase->items); $i < 6; $i++)
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
                                <div
                                    style="font-size: 10pt; font-weight: 700; text-align: center; margin-top: 3mm; margin-bottom: 2mm;">
                                    {{ \App\Services\ArabicAmountToWords::translate($viewingPurchase->grand_total, $viewingPurchase->currency) }}
                                </div>

                                <!-- Summary Row -->
                                <div
                                    style="display: grid; grid-template-columns: 1fr 40mm 32mm; border: 2px solid #3491d1; align-items: center;">
                                    <div style="padding: 1.5mm 3mm; font-size: 9pt; font-weight: 700; text-align: center;">
                                    </div>
                                    <div
                                        style="background-color: #deeaf6; padding: 1.5mm; text-align: center; font-weight: 900; border-right: 2px solid #3491d1; border-left: 2px solid #3491d1;">
                                        {{ $viewingPurchase->currency === 'USD' ? 'Total USD / المجموع' : 'Total Dinar / المجموع' }}
                                    </div>
                                    <div style="padding: 1.5mm; text-align: center; font-weight: 900; font-size: 11pt;">
                                        {{ number_format($viewingPurchase->grand_total, $viewingPurchase->currency === 'USD' ? 2 : 0) }}
                                    </div>
                                </div>

                                <!-- Terms -->
                                <div
                                    style="display: flex; justify-content: space-between; font-size: 9pt; font-weight: 700; color: #0b2b4a; margin-top: 5mm; border-top: 2px solid #3491d1; padding-top: 2mm;">
                                    <div>Goods once sold will not be taken back.<br>All Electrical items carry no guarantee.
                                    </div>
                                    <div style="text-align: center; color: #0056b3; font-size: 10pt;">TRUST OF<br>GENUINE
                                        PARTS</div>
                                    <div style="text-align: left;">البضاعة المباعة لا ترد ولا تستبدل<br>كافة الأدوات
                                        الكهربائية غير مضمونة</div>
                                </div>

                                <!-- Signatures -->
                                <div
                                    style="display: flex; justify-content: space-between; padding: 5mm 10mm 0; font-size: 10pt; font-weight: 700;">
                                    <div>Storekeeper's Signature / توقيع الأمين</div>
                                    <div>For ALI HADI TRADING CO.</div>
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

    <script>
        document.addEventListener('livewire:init', () => {
            // Purchase Create Modal
            Livewire.on('open-purchase-modal', () => {
                var myModalEl = document.getElementById('purchaseModal');
                var modal = bootstrap.Modal.getOrCreateInstance(myModalEl);
                modal.show();
            });
            Livewire.on('close-purchase-modal', () => {
                var myModalEl = document.getElementById('purchaseModal');
                var modal = bootstrap.Modal.getInstance(myModalEl);
                if (modal) modal.hide();
            });

            // View Purchase Modal
            Livewire.on('open-view-modal', () => {
                var myModalEl = document.getElementById('viewModal');
                var modal = bootstrap.Modal.getOrCreateInstance(myModalEl);
                modal.show();
            });

            // Direct Printing Logic
            Livewire.on('trigger-direct-print', (dataArray) => {
                const data = dataArray[0];
                const frame = document.getElementById('printFrame');
                frame.src = data.url;
                frame.onload = function () {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                };
            });
        });
    </script>
</div>