<div>
    {{-- Page Header --}}
    <x-mary-header title="Outstanding Management" separator>
        <x-slot:subtitle>
            Track all receivables and payables in one place
        </x-slot:subtitle>
        <x-slot:actions>
            <x-mary-button
                icon="o-arrow-down-tray"
                label="Export"
                class="btn-outline"
                @click="$wire.exportOutstanding()" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <x-mary-stat
            title="Total Receivables"
            description="From Clients"
            :value="'₹' . number_format($totalReceivables, 2)"
            icon="o-arrow-trending-up"
            class="bg-success/10"
            tooltip="Total amount clients owe us" />

        <x-mary-stat
            title="Total Payables"
            description="To Suppliers"
            :value="'₹' . number_format($totalPayables, 2)"
            icon="o-arrow-trending-down"
            class="bg-error/10"
            tooltip="Total amount we owe suppliers" />

        <x-mary-stat
            title="Net Position"
            :description="$netPosition >= 0 ? 'Favorable' : 'Unfavorable'"
            :value="'₹' . number_format(abs($netPosition), 2)"
            icon="o-scale"
            :class="$netPosition >= 0 ? 'bg-info/10' : 'bg-warning/10'"
            tooltip="Receivables minus Payables" />

        <x-mary-stat
            title="Overdue Receivables"
            description="Past Due"
            :value="'₹' . number_format($overdueReceivables, 2)"
            icon="o-exclamation-triangle"
            class="bg-warning/10"
            tooltip="Amount overdue from clients" />

        <x-mary-stat
            title="Total Outstanding"
            description="Combined"
            :value="'₹' . number_format($totalReceivables + $totalPayables, 2)"
            icon="o-banknotes"
            class="bg-neutral/10"
            tooltip="Total outstanding amounts" />
    </div>

    {{-- Filters Section --}}
    <div class="card bg-base-100 shadow mb-6">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <x-mary-select
                    label="Entity Type"
                    wire:model.live="entityType"
                    :options="[
                        ['value' => 'all', 'label' => 'All'],
                        ['value' => 'client', 'label' => 'Clients Only'],
                        ['value' => 'supplier', 'label' => 'Suppliers Only']
                    ]"
                    option-value="value"
                    option-label="label"
                    icon="o-users" />

                <x-mary-select
                    label="Outstanding Range"
                    wire:model.live="outstandingFilter"
                    :options="collect($outstandingRanges)->map(fn($range, $key) => ['value' => $key, 'label' => $range['label']])->values()->toArray()"
                    option-value="value"
                    option-label="label"
                    icon="o-currency-rupee" />

                <x-mary-select
                    label="Per Page"
                    wire:model.live="perPage"
                    :options="[
                        ['value' => 10, 'label' => '10'],
                        ['value' => 15, 'label' => '15'],
                        ['value' => 25, 'label' => '25'],
                        ['value' => 50, 'label' => '50'],
                        ['value' => 100, 'label' => '100']
                    ]"
                    option-value="value"
                    option-label="label"
                    icon="o-list-bullet" />

                <x-mary-input
                    label="Search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Name, phone, city..."
                    icon="o-magnifying-glass"
                    clearable />
            </div>
        </div>
    </div>

    {{-- Outstanding Table --}}
    <div class="card bg-base-100 shadow">
        <div class="card-body">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-semibold text-lg">Outstanding Details ({{ $totalEntries }} entries)</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th class="cursor-pointer" wire:click="updateSort('name')">
                                <div class="flex items-center gap-1">
                                    Name
                                    @if($sortBy['column'] === 'name')
                                    <span class="text-xs">{{ $sortBy['direction'] === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </div>
                            </th>
                            <th>Contact</th>
                            <th class="cursor-pointer text-right" wire:click="updateSort('outstanding')">
                                <div class="flex items-center justify-end gap-1">
                                    Outstanding
                                    @if($sortBy['column'] === 'outstanding')
                                    <span class="text-xs">{{ $sortBy['direction'] === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </div>
                            </th>
                            <th class="text-center">Overdue</th>
                            <th class="text-center cursor-pointer" wire:click="updateSort('days_outstanding')">
                                <div class="flex items-center justify-center gap-1">
                                    Days
                                    @if($sortBy['column'] === 'days_outstanding')
                                    <span class="text-xs">{{ $sortBy['direction'] === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </div>
                            </th>
                            <th class="text-center">Invoices</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($outstandingData as $item)
                        <tr>
                            <td>
                                @if($item['entity_type'] === 'client')
                                <x-mary-badge value="Receivable" class="badge-success badge-sm" />
                                @else
                                <x-mary-badge value="Payable" class="badge-error badge-sm" />
                                @endif
                            </td>
                            <td>
                                <div class="font-medium">{{ $item['name'] }}</div>
                                @if($item['city'])
                                <div class="text-xs text-gray-500">{{ $item['city'] }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="text-sm">{{ $item['phone'] ?: '-' }}</span>
                            </td>
                            <td class="text-right">
                                <span class="font-bold {{ $item['entity_type'] === 'client' ? 'text-success' : 'text-error' }}">
                                    ₹{{ number_format($item['outstanding'], 2) }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($item['overdue_amount'] > 0)
                                <div class="tooltip" data-tip="₹{{ number_format($item['overdue_amount'], 2) }} overdue">
                                    <x-mary-badge value="{{ $item['overdue_count'] }}" class="badge-warning" />
                                </div>
                                @else
                                <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($item['days_outstanding'] > 0)
                                <span class="badge badge-ghost badge-sm">
                                    {{ $item['days_outstanding'] }}d
                                </span>
                                @else
                                <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($item['total_invoices'] > 0)
                                <span class="badge badge-neutral badge-sm">
                                    {{ $item['total_invoices'] }}
                                </span>
                                @else
                                <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <x-mary-button
                                    icon="o-eye"
                                    class="btn-ghost btn-sm"
                                    tooltip="View Details"
                                    wire:click="showEntityDetails({{ $item['id'] }}, '{{ $item['entity_type'] }}')" />
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-500">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p>No outstanding records found</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $outstandingData->links() }}
            </div>
        </div>
    </div>

    {{-- Details Modal --}}
    @if($selectedEntity)
    <x-mary-modal wire:model="showDetailsModal" title="{{ $selectedEntity->name }} - Outstanding Details" box-class="max-w-4xl">
        <div class="space-y-4">
            {{-- Entity Info --}}
            <div class="grid grid-cols-2 gap-4 bg-base-200 p-4 rounded-lg">
                <div>
                    <span class="text-sm text-gray-600">Phone:</span>
                    <p class="font-medium">{{ $selectedEntity->phone ?: 'N/A' }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-600">City:</span>
                    <p class="font-medium">{{ $selectedEntity->city ?: 'N/A' }}</p>
                </div>
                <div>
                    <span class="text-sm text-gray-600">Current Balance:</span>
                    <p class="font-bold text-lg {{ $selectedEntityType === 'client' ? 'text-success' : 'text-error' }}">
                        ₹{{ number_format($selectedEntity->ledger->current_balance ?? 0, 2) }}
                    </p>
                </div>
                @if($selectedEntityType === 'client')
                <div>
                    <span class="text-sm text-gray-600">Unpaid Invoices:</span>
                    <p class="font-medium">
                        {{-- ✅ Use withoutTrashed() --}}
                        {{ App\Models\Invoice::withoutTrashed()
                        ->where('client_id', $selectedEntity->id)
                        ->whereIn('payment_status', ['unpaid', 'partial'])
                        ->count() }}
                    </p>
                </div>
                @endif
            </div>

            {{-- Unpaid Invoices (for clients) --}}
            @if($selectedEntityType === 'client')
            @php
            // ✅ Use withoutTrashed()
            $unpaidInvoices = App\Models\Invoice::withoutTrashed()
            ->where('client_id', $selectedEntity->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('balance_amount', '>', 0)
            ->orderBy('invoice_date', 'asc')
            ->get();
            @endphp

            @if($unpaidInvoices->count() > 0)
            <div>
                <h4 class="font-semibold mb-2">Unpaid Invoices</h4>
                <div class="overflow-x-auto max-h-64">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Due Date</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($unpaidInvoices as $invoice)
                            <tr class="{{ $invoice->due_date && $invoice->due_date->isPast() ? 'bg-warning/10' : '' }}">
                                <td class="font-medium">{{ $invoice->invoice_number }}</td>
                                <td>{{ $invoice->invoice_date->format('d M Y') }}</td>
                                <td>
                                    {{ $invoice->due_date ? $invoice->due_date->format('d M Y') : '-' }}
                                    @if($invoice->due_date && $invoice->due_date->isPast())
                                    <x-mary-badge value="Overdue" class="badge-warning badge-xs ml-1" />
                                    @endif
                                </td>
                                <td class="text-right">₹{{ number_format($invoice->total_amount, 2) }}</td>
                                <td class="text-right font-bold">₹{{ number_format($invoice->balance_amount, 2) }}</td>
                                <td>
                                    <x-mary-badge
                                        :value="ucfirst($invoice->payment_status)"
                                        :class="$invoice->payment_status === 'paid' ? 'badge-success' : ($invoice->payment_status === 'partial' ? 'badge-warning' : 'badge-error')" />
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
            @endif

            {{-- Recent Transactions --}}
            @if($selectedEntity->ledger)
            <div>
                <h4 class="font-semibold mb-2">Recent Transactions</h4>
                <div class="overflow-x-auto max-h-64">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($selectedEntity->ledger->transactions()->orderBy('date', 'desc')->take(10)->get() as $transaction)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($transaction->date)->format('d M Y') }}</td>
                                <td>
                                    <span class="badge badge-ghost badge-sm">{{ ucfirst($transaction->type) }}</span>
                                </td>
                                <td class="text-sm">{{ $transaction->description }}</td>
                                <td class="text-right">
                                    {{ $transaction->debit_amount > 0 ? '₹' . number_format($transaction->debit_amount, 2) : '-' }}
                                </td>
                                <td class="text-right">
                                    {{ $transaction->credit_amount > 0 ? '₹' . number_format($transaction->credit_amount, 2) : '-' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="Close" @click="$wire.showDetailsModal = false" />
            @if($selectedEntityType === 'client')
            <x-mary-button
                label="Receive Payment"
                class="btn-success"
                link="/payments" />
            @else
            <x-mary-button
                label="Make Payment"
                class="btn-error"
                link="/payments" />
            @endif
        </x-slot:actions>
    </x-mary-modal>
    @endif
</div>