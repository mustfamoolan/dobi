<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Employee;
use Livewire\Volt\Component;

new class extends Component {
    public $query = '';
    public $results = [];

    public function updatedQuery()
    {
        if (strlen($this->query) < 2) {
            $this->results = [];
            return;
        }

        $query = $this->query;

        $products = Product::where('name', 'like', "%{$query}%")
            ->orWhere('sku', 'like', "%{$query}%")
            ->take(5)
            ->get()
            ->map(fn($p) => [
                'type' => __('Product'),
                'title' => $p->name,
                'sub' => $p->sku,
                'url' => route('admin.products.index', ['search' => $p->sku]),
                'icon' => 'bi-box-seam'
            ]);

        $customers = Customer::where('name', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->take(5)
            ->get()
            ->map(fn($c) => [
                'type' => __('Customer'),
                'title' => $c->name,
                'sub' => $c->phone,
                'url' => route('admin.customers.ledger', $c->id),
                'icon' => 'bi-person'
            ]);

        $sales = Sale::where('id', 'like', "%{$query}%")
            ->orWhereHas('customer', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->with('customer')
            ->take(5)
            ->get()
            ->map(fn($s) => [
                'type' => __('Sale'),
                'title' => __('Invoice') . " #{$s->id}",
                'sub' => $s->customer->name ?? '',
                'url' => route('admin.sales.index', ['search' => $s->id]),
                'icon' => 'bi-file-earmark-text'
            ]);

        $employees = Employee::where('name', 'like', "%{$query}%")
            ->take(5)
            ->get()
            ->map(fn($e) => [
                'type' => __('Employee'),
                'title' => $e->name,
                'sub' => $e->position ?? '',
                'url' => route('admin.employees.ledger', $e->id),
                'icon' => 'bi-person-badge'
            ]);

        $this->results = collect($products)
            ->concat($customers)
            ->concat($sales)
            ->concat($employees)
            ->toArray();
    }

    public function clear()
    {
        $this->reset(['query', 'results']);
    }
};
?>

<div class="dropdown features-dropdown w-100">
    <div class="form-icon">
        <input type="search" class="form-control form-control-icon border-0 shadow-none"
            wire:model.live.debounce.300ms="query" placeholder="{{ __('Search Customers, Products, Invoices...') }}"
            autocomplete="off">
        <i class="ri-search-2-line text-muted"></i>
    </div>

    @if(!empty($results))
        <div class="dropdown-menu show w-100 shadow-lg border-0 mt-1 py-1"
            style="max-height: 400px; overflow-y: auto; display: block;">
            @foreach($results as $res)
                <a href="{{ $res['url'] }}" class="dropdown-item py-2 px-3 border-bottom hstack gap-3">
                    <div class="avatar-item avatar-sm avatar-title bg-light text-primary rounded-circle">
                        <i class="bi {{ $res['icon'] }}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold text-dark fs-13">{{ $res['title'] }}</div>
                        <div class="text-muted fs-11">{{ $res['type'] }} | {{ $res['sub'] }}</div>
                    </div>
                    <i class="ri-arrow-right-s-line text-muted"></i>
                </a>
            @endforeach
            <div class="dropdown-header text-center py-2 bg-light-subtle">
                <span class="fs-12 text-muted">{{ count($results) }} {{ __('Results found') }}</span>
            </div>
        </div>
    @elseif(strlen($query) >= 2)
        <div class="dropdown-menu show w-100 shadow-lg border-0 mt-1 py-2 text-center" style="display: block;">
            <span class="text-muted fs-13">{{ __('No results found for') }} "{{ $query }}"</span>
        </div>
    @endif
</div>