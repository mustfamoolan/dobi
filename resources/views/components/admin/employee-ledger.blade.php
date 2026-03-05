<?php

use App\Models\Employee;
use App\Models\EmployeeLedger;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $employeeId;
    public $startDate;
    public $endDate;

    protected $paginationTheme = 'bootstrap';

    public function mount($employeeId)
    {
        $this->employeeId = $employeeId;
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
    }

    public function render(): mixed
    {
        $employee = Employee::findOrFail($this->employeeId);

        $query = EmployeeLedger::where('employee_id', $this->employeeId)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->oldest('date')
            ->oldest('id');

        $entries = $query->paginate(20);

        // Calculate running balance logic (simplified)
        $previousBalance = EmployeeLedger::where('employee_id', $this->employeeId)
            ->where('date', '<', $this->startDate)
            ->sum(DB::raw('credit - debit'));

        return view('components.admin.employee-ledger', [
            'employee' => $employee,
            'entries' => $entries,
            'previousBalance' => $previousBalance,
        ]);
    }
};
?>

<div>
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">{{ __('Financial Statement') }}: {{ $employee->name }}</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a
                                href="{{ route('admin.employees.index') }}">{{ __('Employees') }}</a></li>
                        <li class="breadcrumb-item active">{{ __('Ledger') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0">
            <div class="row g-4 align-items-center">
                <div class="col-sm">
                    <div>
                        <h5 class="card-title mb-0">{{ __('Transaction History') }}</h5>
                    </div>
                </div>
                <div class="col-sm-auto">
                    <div class="d-flex gap-2">
                        <input type="date" wire:model.live="startDate" class="form-control form-control-sm">
                        <input type="date" wire:model.live="endDate" class="form-control form-control-sm">
                        <button class="btn btn-soft-secondary btn-sm" onclick="window.print()"><i
                                class="ri-printer-line align-bottom"></i> {{ __('Print') }}</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered align-middle table-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th class="text-end">{{ __('Earnings (Credit)') }}</th>
                            <th class="text-end">{{ __('Payments (Debit)') }}</th>
                            <th class="text-end">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="3" class="fw-medium">{{ __('Opening Balance (Before') }} {{ $startDate }})</td>
                            <td colspan="2"></td>
                            <td class="text-end fw-bold {{ $previousBalance >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($previousBalance, 0) }}
                            </td>
                        </tr>
                        @php $runningBalance = $previousBalance; @endphp
                        @foreach($entries as $entry)
                            @php $runningBalance += ($entry->credit - $entry->debit); @endphp
                            <tr>
                                <td>{{ $entry->date }}</td>
                                <td>{{ $entry->description }}</td>
                                <td><span class="badge bg-info-subtle text-info text-uppercase">{{ $entry->type }}</span>
                                </td>
                                <td class="text-end text-success">
                                    {{ $entry->credit > 0 ? number_format($entry->credit, 0) : '-' }}
                                    @if($entry->currency && $entry->currency !== 'IQD')
                                        <br><small class="text-muted">{{ number_format($entry->credit, 0) }}
                                            {{ $entry->currency }}</small>
                                    @endif
                                </td>
                                <td class="text-end text-danger">
                                    {{ $entry->debit > 0 ? number_format($entry->debit, 0) : '-' }}
                                    @if($entry->currency && $entry->currency !== 'IQD')
                                        <br><small class="text-muted">{{ number_format($entry->debit, 0) }}
                                            {{ $entry->currency }}</small>
                                    @endif
                                </td>
                                <td class="text-end fw-medium">{{ number_format($runningBalance, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3" class="text-end">{{ __('Total Current Period') }}</th>
                            <th class="text-end text-success">{{ number_format($entries->sum('credit'), 0) }}</th>
                            <th class="text-end text-danger">{{ number_format($entries->sum('debit'), 0) }}</th>
                            <th class="text-end fw-bold">{{ number_format($runningBalance, 0) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="mt-3">
                {{ $entries->links() }}
            </div>
        </div>
    </div>
</div>