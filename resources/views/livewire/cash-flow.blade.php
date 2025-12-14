<div>
    <div class="flex justify-between items-center mb-6">
        <x-mary-header title="Cash Flow Management" subtitle="Track your money in and out" separator />
        
        {{-- Action Buttons --}}
        <div class="flex gap-2">
            <x-mary-button 
                icon="o-plus-circle" 
                class="btn-primary btn-sm"
                wire:click="openCashTransactionModal">
                Add Cash Transaction
            </x-mary-button>
            
            <x-mary-button 
                icon="o-arrow-path" 
                class="btn-secondary btn-sm"
                wire:click="openTransferModal">
                Transfer Funds
            </x-mary-button>
        </div>
    </div>

    {{-- Current Balances Card --}}
    <x-mary-card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 bg-success/10 rounded-lg">
                <p class="text-sm text-gray-600">Cash in Hand</p>
                <p class="text-3xl font-bold text-success">₹{{ number_format($cashBalance, 2) }}</p>
            </div>
            <div class="text-center p-4 bg-info/10 rounded-lg">
                <p class="text-sm text-gray-600">Total Bank Balance</p>
                <p class="text-3xl font-bold text-info">₹{{ number_format($bankBalance, 2) }}</p>
            </div>
            <div class="text-center p-4 bg-primary/10 rounded-lg">
                <p class="text-sm text-gray-600">Total Balance</p>
                <p class="text-3xl font-bold text-primary">₹{{ number_format($cashBalance + $bankBalance, 2) }}</p>
            </div>
        </div>
    </x-mary-card>

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
                        wire:click="setPeriod('this_month')" />
                    <x-mary-button label="Last Month" size="sm"
                        class="{{ $period === 'last_month' ? 'btn-primary' : 'btn-ghost' }}"
                        wire:click="setPeriod('last_month')" />
                    <x-mary-button label="This Quarter" size="sm"
                        class="{{ $period === 'this_quarter' ? 'btn-primary' : 'btn-ghost' }}"
                        wire:click="setPeriod('this_quarter')" />
                    <x-mary-button label="This Year" size="sm"
                        class="{{ $period === 'this_year' ? 'btn-primary' : 'btn-ghost' }}"
                        wire:click="setPeriod('this_year')" />
                </div>
            </div>

            {{-- Custom Date Range --}}
            <x-mary-input label="From Date" wire:model.live="dateFrom" type="date" />
            <x-mary-input label="To Date" wire:model.live="dateTo" type="date" />

            {{-- Account Filter --}}
            <div>
                <label class="label">
                    <span class="label-text">Account</span>
                </label>
                <select wire:model.live="selectedAccount" class="select select-bordered w-full">
                    @foreach($accountOptions as $account)
                        <option value="{{ $account->id }}">{{ $account->account_name }}</option>
                    @endforeach
                </select>
            </div>
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

    {{-- All Transactions --}}
    <x-mary-card>
        <div class="flex justify-between items-center mb-4">
            <x-mary-header title="All Transactions" subtitle="Cash and bank transactions in selected period" />
            <x-mary-button label="Export" icon="o-arrow-down-tray" class="btn-sm"
                wire:click="exportCashFlow" />
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Account</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Reference</th>
                        <th class="text-right">Inflow</th>
                        <th class="text-right">Outflow</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allTransactions->take(50) as $transaction)
                    <tr>
                        <td class="text-sm">{{ $transaction['date']->format('d M Y') }}</td>
                        <td class="text-sm">
                            <div class="flex items-center gap-1">
                                @if($transaction['account_type'] === 'cash')
                                    <span class="text-lg">💵</span>
                                @else
                                    <span class="text-lg">🏦</span>
                                @endif
                                {{ $transaction['account'] }}
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-sm">{{ $transaction['category'] }}</span>
                        </td>
                        <td class="text-sm max-w-xs truncate">{{ $transaction['description'] }}</td>
                        <td class="text-xs text-gray-600">{{ $transaction['reference'] ?: '-' }}</td>
                        <td class="text-right text-success font-medium">
                            @if($transaction['type'] === 'credit')
                            ₹{{ number_format($transaction['amount'], 2) }}
                            @endif
                        </td>
                        <td class="text-right text-error font-medium">
                            @if($transaction['type'] === 'debit')
                            ₹{{ number_format($transaction['amount'], 2) }}
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-8 text-gray-500">
                            No transactions found for the selected period
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-mary-card>

    {{-- Add Cash Transaction Modal --}}
    <x-mary-modal wire:model="showCashTransactionModal" title="Add Cash Transaction">
        <div class="space-y-4">
            <x-mary-input label="Date *" wire:model="newTransaction.date" type="date" required />

            <div>
                <label class="label">
                    <span class="label-text">Transaction Type *</span>
                </label>
                <select wire:model="newTransaction.type" class="select select-bordered w-full" required>
                    <option value="receipt">💰 Cash Receipt (+)</option>
                    <option value="payment">💸 Cash Payment (-)</option>
                </select>
            </div>

            <div>
                <label class="label">
                    <span class="label-text">Category *</span>
                </label>
                <select wire:model="newTransaction.category" class="select select-bordered w-full" required>
                    @foreach($categoryOptions as $category)
                        <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <x-mary-textarea label="Description *" wire:model="newTransaction.description" 
                placeholder="Enter transaction details" required />

            <x-mary-input label="Amount *" wire:model="newTransaction.amount" 
                type="number" step="0.01" prefix="₹" required />

            <x-mary-input label="Reference" wire:model="newTransaction.reference" 
                placeholder="Receipt/Bill number" />
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showCashTransactionModal', false)" />
            <x-mary-button label="Save Transaction" class="btn-primary" 
                wire:click="addCashTransaction" spinner="addCashTransaction" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Transfer Funds Modal --}}
    <x-mary-modal wire:model="showTransferModal" title="Transfer Funds">
        <div class="space-y-4">
            <x-mary-input label="Date *" wire:model="transfer.date" type="date" required />

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">
                        <span class="label-text">From *</span>
                    </label>
                    <select wire:model.live="transfer.from_type" class="select select-bordered w-full" required>
                        <option value="cash">💵 Cash</option>
                        <option value="bank">🏦 Bank</option>
                    </select>
                </div>

                <div>
                    <label class="label">
                        <span class="label-text">To *</span>
                    </label>
                    <select wire:model.live="transfer.to_type" class="select select-bordered w-full" required>
                        <option value="cash">💵 Cash</option>
                        <option value="bank">🏦 Bank</option>
                    </select>
                </div>
            </div>

            @if(in_array('bank', [$transfer['from_type'], $transfer['to_type']]))
            <div>
                <label class="label">
                    <span class="label-text">Bank Account *</span>
                </label>
                <select wire:model="transfer.bank_account_id" class="select select-bordered w-full" required>
                    <option value="">Select bank account</option>
                    @foreach($bankAccounts as $account)
                        <option value="{{ $account->id }}">
                            {{ $account->bank_name }} - {{ $account->account_number }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <x-mary-input label="Amount *" wire:model="transfer.amount" 
                type="number" step="0.01" prefix="₹" required />

            <x-mary-textarea label="Description *" wire:model="transfer.description" required />

            <x-mary-input label="Reference" wire:model="transfer.reference" 
                placeholder="Transaction reference" />
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showTransferModal', false)" />
            <x-mary-button label="Transfer" class="btn-success" 
                wire:click="transferFunds" spinner="transferFunds" />
        </x-slot:actions>
    </x-mary-modal>
</div>
