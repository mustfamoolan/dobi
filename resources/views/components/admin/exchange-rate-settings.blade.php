<?php

use App\Models\AppSetting;
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $exchange_rate;

    public function mount()
    {
        $setting = AppSetting::first();
        $this->exchange_rate = $setting->exchange_rate ?? 1500;
    }

    public function save()
    {
        $this->validate([
            'exchange_rate' => 'required|numeric|min:1',
        ]);

        $setting = AppSetting::first();
        $setting->update([
            'exchange_rate' => $this->exchange_rate,
        ]);

        session()->flash('success', __('Exchange rate updated successfully.'));
    }
};
?>

<div>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Financial Settings') }}</h5>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <form wire:submit.prevent="save">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Default Exchange Rate (1 USD = ? IQD)') }}</label>
                            <div class="input-group">
                                <span class="input-group-text">1 USD =</span>
                                <input type="number" step="0.001" wire:model="exchange_rate" class="form-control"
                                    placeholder="1500">
                                <span class="input-group-text">IQD</span>
                            </div>
                            <small
                                class="text-muted">{{ __('This rate will be used as a default for all new financial transactions.') }}</small>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">{{ __('Update Rate') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>