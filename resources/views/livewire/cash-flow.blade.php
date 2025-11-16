<div>
    <x-mary-header title="Cash Flow Management" subtitle="Track your money in and out" separator />

    {{-- Filters --}}
    <x-mary-card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            {{-- Period Selection --}}
            <div class="md:col-span-2">
                <label class="label">
                    <span class="label-text">Period</span>
                </label>
                <div class="flex flex-wrap gap-2">
                    <x-mary-button label="This Month" size="sm"
                        class="{{ $period === 'this_month' ? 'btn-primary' : 'btn-ghost' }}"
                        @click="$wire.setPeriod('this_month')" />
                    <x-mary-button label="Last Month" size="sm"
                        class="{{ $period === 'last_month' ? 'btn-primary' : 'btn-ghost' }}"
                        @click="$wire.setPeriod('last_month')" />
                    <x-mary-button label="This Quarter" size="sm"
                        class="{{ $period === 'this_quarter' ? 'btn-primary' : 'btn-ghost' }}"
                        @click="$wire.setPeriod('this_quarter')" />
                    <x-mary-button label="This Year" size="sm"
                        class="{{ $period === 'this_year' ? 'btn-primary' : 'btn-ghost' }}"
                        @click="$wire.setPeriod('this_year')" />
                </div>
            </div>

            {{-- Custom Date Range --}}
            <x-mary-input label="From Date" wire:model.live="dateFrom" type="date" />
            <x-mary-input label="To Date" wire:model.live="dateTo" type="date" />

            {{-- Bank Account Filter --}}
            <x-mary-select
                label="Bank Account"
                wire:model="selectedBankAccount"
                :options="$bankAccounts"
                option-value="id" 
                option-label="account_name" 
                placeholder="Select account" />

        </div>
    </x-mary-card>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-mary-card>
            <div class="text-center">
                <p class="text-sm text-gray-600">Opening Balance</p>
                <p class="text-2xl font-bold">₹{{ number_format($openingBalance, 2) }}</p>
            </div>
        </x-mary-card>

        <x-mary-card>
            <div class="text-center">
                <p class="text-sm text-gray-600">Total Inflow</p>
                <p class="text-2xl font-bold text-success">+₹{{ number_format($totalInflow, 2) }}</p>
            </div>
        </x-mary-card>

        <x-mary-card>
            <div class="text-center">
                <p class="text-sm text-gray-600">Total Outflow</p>
                <p class="text-2xl font-bold text-error">-₹{{ number_format($totalOutflow, 2) }}</p>
            </div>
        </x-mary-card>

        <x-mary-card>
            <div class="text-center">
                <p class="text-sm text-gray-600">Closing Balance</p>
                <p class="text-2xl font-bold {{ $closingBalance >= $openingBalance ? 'text-success' : 'text-error' }}">
                    ₹{{ number_format($closingBalance, 2) }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $closingBalance >= $openingBalance ? '↑' : '↓' }}
                    ₹{{ number_format(abs($closingBalance - $openingBalance), 2) }}
                </p>
            </div>
        </x-mary-card>
    </div>

    {{-- Daily Cash Flow Chart --}}
    @if(!empty($dailyCashFlow))
    <x-mary-card class="mb-6">
        <x-mary-header title="Daily Cash Flow" subtitle="Daily inflow, outflow and balance" />
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-right">Inflow</th>
                        <th class="text-right">Outflow</th>
                        <th class="text-right">Net</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dailyCashFlow as $day)
                    <tr>
                        <td>{{ $day['date'] }}</td>
                        <td class="text-right text-success">₹{{ number_format($day['inflow'], 2) }}</td>
                        <td class="text-right text-error">₹{{ number_format($day['outflow'], 2) }}</td>
                        <td class="text-right font-semibold {{ $day['net'] >= 0 ? 'text-success' : 'text-error' }}">
                            {{ $day['net'] >= 0 ? '+' : '' }}₹{{ number_format($day['net'], 2) }}
                        </td>
                        <td class="text-right font-bold">₹{{ number_format($day['balance'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-mary-card>
    @endif

    {{-- Inflow and Outflow Categories --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        {{-- Inflow Categories --}}
        <x-mary-card>
            <x-mary-header title="Inflow by Category" subtitle="Money coming in" />
            <div class="space-y-3">
                @forelse($inflowCategories as $category)
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-medium">{{ $category['category'] }}</span>
                        <span class="text-sm font-bold text-success">₹{{ number_format($category['amount'], 2) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <progress class="progress progress-success w-full"
                            value="{{ $category['percentage'] }}" max="100"></progress>
                        <span class="text-xs text-gray-600">{{ number_format($category['percentage'], 1) }}%</span>
                    </div>
                    <p class="text-xs text-gray-500">{{ $category['count'] }} transactions</p>
                </div>
                @empty
                <p class="text-center text-gray-500 py-4">No inflow transactions</p>
                @endforelse
            </div>
        </x-mary-card>

        {{-- Outflow Categories --}}
        <x-mary-card>
            <x-mary-header title="Outflow by Category" subtitle="Money going out" />
            <div class="space-y-3">
                @forelse($outflowCategories as $category)
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-medium">{{ $category['category'] }}</span>
                        <span class="text-sm font-bold text-error">₹{{ number_format($category['amount'], 2) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <progress class="progress progress-error w-full"
                            value="{{ $category['percentage'] }}" max="100"></progress>
                        <span class="text-xs text-gray-600">{{ number_format($category['percentage'], 1) }}%</span>
                    </div>
                    <p class="text-xs text-gray-500">{{ $category['count'] }} transactions</p>
                </div>
                @empty
                <p class="text-center text-gray-500 py-4">No outflow transactions</p>
                @endforelse
            </div>
        </x-mary-card>
    </div>

    {{-- Recent Transactions --}}
    <x-mary-card>
        <div class="flex justify-between items-center mb-4">
            <x-mary-header title="Recent Transactions" subtitle="Last 50 transactions in selected period" />
            <x-mary-button label="Export" icon="o-arrow-down-tray" class="btn-sm"
                @click="$wire.exportCashFlow()" />
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Bank Account</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Reference</th>
                        <th class="text-right">Inflow</th>
                        <th class="text-right">Outflow</th>
                        <th class="text-right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bankTransactions as $transaction)
                    <tr>
                        <td class="text-sm">{{ $transaction->transaction_date->format('d M Y') }}</td>
                        <td class="text-sm">{{ $transaction->bankAccount->account_name }}</td>
                        <td>
                            <span class="badge badge-sm">{{ $transaction->category ?: 'N/A' }}</span>
                        </td>
                        <td class="text-sm max-w-xs truncate">{{ $transaction->description }}</td>
                        <td class="text-xs text-gray-600">{{ $transaction->reference_number }}</td>
                        <td class="text-right text-success font-medium">
                            @if($transaction->type === 'credit')
                            ₹{{ number_format($transaction->amount, 2) }}
                            @endif
                        </td>
                        <td class="text-right text-error font-medium">
                            @if($transaction->type === 'debit')
                            ₹{{ number_format($transaction->amount, 2) }}
                            @endif
                        </td>
                        <td class="text-right font-semibold">₹{{ number_format($transaction->balance_after, 2) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-8 text-gray-500">
                            No transactions found for the selected period
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-mary-card>
</div>