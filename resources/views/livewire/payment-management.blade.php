<div>
    {{-- Page Header --}}
    <x-mary-header title="Payment Management" separator>
        <x-slot:actions>
            <x-mary-button
                icon="o-arrow-down-tray"
                class="btn-success"
                label="Receive Payment"
                @click="$wire.openReceivePaymentModal()" />
            <x-mary-button
                icon="o-arrow-up-tray"
                class="btn-error"
                label="Make Payment"
                @click="$wire.openMakePaymentModal()" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-mary-stat
            title="Total Received"
            :value="'₹' . number_format($totalReceived, 2)"
            icon="o-arrow-trending-up"
            class="bg-success/10"
            tooltip="Total payments received in selected period" />

        <x-mary-stat
            title="Total Paid"
            :value="'₹' . number_format($totalPaid, 2)"
            icon="o-arrow-trending-down"
            class="bg-error/10"
            tooltip="Total payments made in selected period" />

        <x-mary-stat
            title="Today Received"
            :value="'₹' . number_format($todayReceived, 2)"
            icon="o-currency-rupee"
            class="bg-info/10"
            tooltip="Payments received today" />

        <x-mary-stat
            title="Today Paid"
            :value="'₹' . number_format($todayPaid, 2)"
            icon="o-banknotes"
            class="bg-warning/10"
            tooltip="Payments made today" />
    </div>

    {{-- Filters Section --}}
    <div class="card bg-base-100 shadow mb-6">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <x-mary-input
                    label="Date From"
                    wire:model.live="dateFrom"
                    type="date"
                    icon="o-calendar" />

                <x-mary-input
                    label="Date To"
                    wire:model.live="dateTo"
                    type="date"
                    icon="o-calendar" />

                <x-mary-select
                    label="Entity Type"
                    wire:model.live="entityFilter"
                    :options="[
                        ['value' => 'all', 'label' => 'All'],
                        ['value' => 'client', 'label' => 'Clients'],
                        ['value' => 'supplier', 'label' => 'Suppliers']
                    ]"
                    option-value="value"
                    option-label="label"
                    icon="o-users" />

                <x-mary-select
                    label="Payment Method"
                    wire:model.live="paymentMethodFilter"
                    :options="[
                        ['value' => 'all', 'label' => 'All Methods'],
                        ['value' => 'cash', 'label' => 'Cash'],
                        ['value' => 'bank', 'label' => 'Bank']
                    ]"
                    option-value="value"
                    option-label="label"
                    icon="o-banknotes" />

                <x-mary-input
                    label="Search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search reference, description..."
                    icon="o-magnifying-glass"
                    clearable />
            </div>
        </div>
    </div>

    {{-- Transactions Table --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Entity</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th class="text-right">Amount</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                        <tr>
                            <td>
                                <div class="font-medium">
                                    {{ \Carbon\Carbon::parse($transaction->date)->format('d M Y') }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ \Carbon\Carbon::parse($transaction->date)->format('h:i A') }}
                                </div>
                            </td>
                            <td>
                                @if($transaction->ledger->ledger_type === 'client')
                                <x-mary-badge value="Received" class="badge-success" />
                                @else
                                <x-mary-badge value="Paid" class="badge-error" />
                                @endif
                            </td>
                            <td>
                                <div class="font-medium">
                                    {{ $transaction->ledger->ledgerable->name ?? 'N/A' }}
                                </div>
                                <div class="text-xs text-gray-500 capitalize">
                                    {{ $transaction->ledger->ledger_type }}
                                </div>
                            </td>
                            <td class="max-w-xs truncate">
                                {{ $transaction->description }}
                            </td>
                            <td>
                                <span class="badge badge-ghost badge-sm">
                                    {{ $transaction->reference ?: '-' }}
                                </span>
                            </td>
                            <td class="text-right font-bold">
                                @if($transaction->ledger->ledger_type === 'client')
                                <span class="text-success">
                                    +₹{{ number_format($transaction->credit_amount, 2) }}
                                </span>
                                @else
                                <span class="text-error">
                                    -₹{{ number_format($transaction->credit_amount, 2) }}
                                </span>
                                @endif
                            </td>
                            <td>
                                @php
                                // Try to determine payment method from bank transactions
                                $method = 'Cash';
                                if ($transaction->bankTransaction) {
                                $method = 'Bank';
                                }
                                @endphp
                                <span class="badge badge-outline">{{ $method }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-500">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p>No transactions found</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $transactions->links() }}
            </div>
        </div>
    </div>

    {{-- Receive Payment Modal --}}
    <x-mary-modal wire:model="showReceivePaymentModal" title="Receive Payment from Client" box-class="max-w-4xl">
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Left Column: Form --}}
                <div class="space-y-4">
                    <x-mary-choices-offline
                        label="Client *"
                        wire:model.live="receivePayment.client_id"
                        :options="$clients"
                        option-value="id"
                        option-label="name"
                        placeholder="Select client"
                        single
                        searchable
                        required />

                    {{-- Client Outstanding Info --}}
                    @if($this->clientOutstanding)
                    <div class="alert alert-info">
                        <div class="flex flex-col w-full">
                            <div class="flex justify-between text-sm">
                                <span>Total Outstanding:</span>
                                <span class="font-bold">₹{{ number_format($this->clientOutstanding['total_outstanding'], 2) }}</span>
                            </div>
                            <div class="flex justify-between text-sm mt-1">
                                <span>Unpaid Invoices:</span>
                                <span class="font-bold">{{ $this->clientOutstanding['unpaid_invoices_count'] }}</span>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                        <x-mary-input
                            label="Payment Date *"
                            wire:model="receivePayment.date"
                            type="date"
                            required />

                        <x-mary-input
                            label="Amount *"
                            wire:model.live.debounce.500ms="receivePayment.amount"
                            type="number"
                            step="0.01"
                            prefix="₹"
                            placeholder="0.00"
                            required />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <x-mary-select
                            label="Payment Method *"
                            wire:model.live="receivePayment.payment_method"
                            :options="[
                            ['value' => 'cash', 'label' => '💵 Cash'],
                            ['value' => 'bank', 'label' => '🏦 Bank Transfer']
                        ]"
                            option-value="value"
                            option-label="label"
                            required />

                        {{-- Bank Account Selection (shown only if payment method is bank) --}}
                        @if($receivePayment['payment_method'] === 'bank')
                        <x-mary-select
                            label="Bank Account *"
                            wire:model="receivePayment.bank_account_id"
                            :options="$bankAccounts"
                            option-value="id"
                            option-label="display_name"
                            icon="o-building-library"
                            placeholder="Select bank account"
                            hint="Select the bank account for this transaction"
                            :error="$errors->first('receivePayment.bank_account_id')"
                            required />
                        @else
                        @endif
                    </div>

                    <x-mary-input
                        label="Reference Number"
                        wire:model="receivePayment.reference"
                        placeholder="Cheque/Transaction reference" />

                    <x-mary-textarea
                        label="Description *"
                        wire:model="receivePayment.description"
                        placeholder="Payment description..."
                        rows="2"
                        required />
                </div>

                {{-- Right Column: Allocation Preview --}}
                <div class="space-y-4">
                    <div class="border-l-4 border-info pl-4">
                        <h4 class="font-semibold text-sm mb-3">Payment Allocation Preview</h4>

                        @if($this->paymentAllocationPreview && $this->paymentAllocationPreview['has_allocations'])
                        <div class="space-y-3">
                            {{-- Allocation Summary --}}
                            <div class="bg-base-200 p-3 rounded-lg">
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Total Payment:</span>
                                    <span class="font-bold">₹{{ number_format($receivePayment['amount'] ?? 0, 2) }}</span>
                                </div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span>Will be Allocated:</span>
                                    <span class="font-bold text-success">₹{{ number_format($this->paymentAllocationPreview['total_allocated'], 2) }}</span>
                                </div>
                                @if($this->paymentAllocationPreview['remaining'] > 0)
                                <div class="flex justify-between text-sm">
                                    <span>Advance/Credit:</span>
                                    <span class="font-bold text-warning">₹{{ number_format($this->paymentAllocationPreview['remaining'], 2) }}</span>
                                </div>
                                @endif
                            </div>

                            {{-- Invoice Allocations --}}
                            <div class="max-h-64 overflow-y-auto space-y-2">
                                @foreach($this->paymentAllocationPreview['allocations'] as $index => $allocation)
                                <div class="bg-base-100 p-3 rounded border border-base-300">
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <div class="font-medium text-sm">{{ $allocation['invoice_number'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $allocation['invoice_date'] }}</div>
                                        </div>
                                        <x-mary-badge
                                            :value="ucfirst($allocation['status'])"
                                            :class="$allocation['status'] === 'paid' ? 'badge-success' : 'badge-warning'" />
                                    </div>
                                    <div class="space-y-1 text-xs">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Balance:</span>
                                            <span>₹{{ number_format($allocation['balance_before'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Will Pay:</span>
                                            <span class="text-success font-bold">₹{{ number_format($allocation['will_allocate'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Remaining:</span>
                                            <span class="font-medium">₹{{ number_format($allocation['balance_after'], 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @elseif($receivePayment['client_id'] && $receivePayment['amount'] > 0)
                        <div class="alert alert-warning">
                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span class="text-sm">No unpaid invoices found. Full amount will be recorded as advance payment.</span>
                        </div>
                        @else
                        <div class="text-center text-gray-500 py-8">
                            <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-sm">Select a client and enter amount to see allocation preview</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" @click="$wire.showReceivePaymentModal = false" />
            <x-mary-button
                label="Save Payment"
                class="btn-success"
                spinner="saveReceivePayment"
                @click="$wire.saveReceivePayment()" />
        </x-slot:actions>
    </x-mary-modal>


    {{-- Make Payment Modal --}}
    <x-mary-modal wire:model="showMakePaymentModal" title="Make Payment to Supplier" box-class="max-w-2xl">
        <div class="space-y-4">
            <x-mary-choices-offline
                label="Supplier *"
                wire:model.live="makePayment.supplier_id"
                :options="$suppliers"
                option-value="id"
                option-label="name"
                placeholder="Select supplier"
                single
                searchable
                required />

            <div class="grid grid-cols-2 gap-4">
                <x-mary-input
                    label="Payment Date *"
                    wire:model="makePayment.date"
                    type="date"
                    required />

                <x-mary-input
                    label="Amount *"
                    wire:model="makePayment.amount"
                    type="number"
                    step="0.01"
                    prefix="₹"
                    placeholder="0.00"
                    required />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <x-mary-select
                    label="Payment Method *"
                    wire:model.live="makePayment.payment_method"
                    :options="[
                        ['value' => 'cash', 'label' => '💵 Cash'],
                        ['value' => 'bank', 'label' => '🏦 Bank Transfer']
                    ]"
                    option-value="value"
                    option-label="label"
                    required />

                @if($makePayment['payment_method'] === 'bank')
                <x-mary-select
                    label="Bank Account *"
                    wire:model="makePayment.bank_account_id"
                    :options="$bankAccounts"
                    option-value="id"
                    option-label="display_name"
                    icon="o-building-library"
                    placeholder="Select bank account"
                    hint="Select the bank account for this transaction"
                    :error="$errors->first('makePayment.bank_account_id')"
                    required />
                @endif
            </div>

            <x-mary-input
                label="Reference Number"
                wire:model="makePayment.reference"
                placeholder="Cheque/Transaction reference" />

            <x-mary-textarea
                label="Description *"
                wire:model="makePayment.description"
                placeholder="Payment description..."
                rows="2"
                required />
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" @click="$wire.showMakePaymentModal = false" />
            <x-mary-button
                label="Save Payment"
                class="btn-error"
                spinner="saveMakePayment"
                @click="$wire.saveMakePayment()" />
        </x-slot:actions>
    </x-mary-modal>
</div>