<div>
    <x-mary-header title="Clients" subtitle="Manage client information" separator>
        <x-slot:middle class="!justify-end">
            <div class="flex gap-2 items-center">
                <x-mary-input
                    icon="o-magnifying-glass"
                    placeholder="Search clients..."
                    wire:model.live.debounce.300ms="search"
                    class="w-64" />
                @if($search)
                <x-mary-button
                    icon="o-x-mark"
                    class="btn-ghost btn-sm btn-circle"
                    wire:click="clearSearch"
                    tooltip="Clear search" />
                @endif
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <button
                @click="$wire.showDrawer = true"
                class="flex items-center gap-2 px-3 py-2 bg-base-200 hover:bg-base-300 rounded-lg transition-colors">
                <x-mary-icon name="o-funnel" class="w-4 h-4" />
                <span class="text-sm">Filters</span>
                @if(count($appliedStatusFilter) > 0 || count($appliedLocationFilter) > 0)
                <x-mary-badge :value="count($appliedStatusFilter) + count($appliedLocationFilter)" class="badge-primary badge-sm" />
                @endif
            </button>
        </x-slot:actions>
    </x-mary-header>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
            <div class="flex items-center gap-3">
                <x-mary-icon name="o-users" class="text-blue-500 text-2xl" />
                <div class="flex flex-col">
                    <div class="text-2xl font-bold text-blue-600">{{ $totalClients }}</div>
                    <div class="text-sm text-blue-600">Total Clients</div>
                </div>
            </div>
        </div>
        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
            <div class="flex items-center gap-3">
                <x-mary-icon name="o-check-circle" class="text-green-500 text-2xl" />
                <div class="flex flex-col">
                    <div class="text-2xl font-bold text-green-600">{{ $activeClients }}</div>
                    <div class="text-sm text-green-600">Active Clients</div>
                </div>
            </div>
        </div>
        <div class="bg-red-50 p-4 rounded-lg border border-red-200">
            <div class="flex items-center gap-3">
                <x-mary-icon name="o-currency-rupee" class="text-red-500 text-2xl" />
                <div class="flex flex-col">
                    <div class="text-2xl font-bold text-red-600">₹{{ number_format($totalOutstanding, 2) }}</div>
                    <div class="text-sm text-red-600">Total Outstanding</div>
                </div>
            </div>
        </div>
        <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
            <div class="flex items-center gap-3">
                <x-mary-icon name="o-shopping-cart" class="text-purple-500 text-2xl" />
                <div class="flex flex-col">
                    <div class="text-2xl font-bold text-purple-600">₹{{ number_format($totalSales, 2) }}</div>
                    <div class="text-sm text-purple-600">Total Sales</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Client Modal with MaryUI Cards -->
    <x-mary-modal wire:model="myModal" box-class="max-w-4xl"
        title="{{ $isEdit ? 'Edit Client' : 'Create Client' }}"
        subtitle="{{ $isEdit ? 'Update the client information' : 'Add a new client to your database' }}">

        <div class="space-y-6">
            {{-- Basic Information --}}
            <x-mary-card>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <x-mary-icon name="o-user" class="w-5 h-5 text-blue-600" />
                        <span>Basic Information</span>
                    </div>
                </x-slot:title>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-mary-input label="Client Name *" icon="o-user" placeholder="Client name" wire:model="name" />
                    <x-mary-input label="Company" icon="o-building-office" placeholder="Company name" wire:model="company" />
                    <x-mary-input label="Contact Person" icon="o-user-circle" placeholder="Contact person name" wire:model="contact_person" />
                    <x-mary-input label="Phone" icon="o-phone" placeholder="+91 98765 43210" wire:model="phone" />
                    <x-mary-input label="Email" icon="o-envelope" type="email" placeholder="client@example.com" wire:model="email" />
                </div>
            </x-mary-card>

            {{-- Address Information --}}
            <x-mary-card>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <x-mary-icon name="o-map-pin" class="w-5 h-5 text-green-600" />
                        <span>Address Information</span>
                    </div>
                </x-slot:title>

                <div class="space-y-4">
                    <x-mary-textarea label="Address" icon="o-home" placeholder="Full address" wire:model="address" />
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <x-mary-input label="City" placeholder="City" wire:model="city" />
                        <x-mary-input label="State" placeholder="State" wire:model="state" />
                        <x-mary-input label="Country" placeholder="Country" wire:model="country" />
                        <x-mary-input label="Pincode" placeholder="123456" wire:model="pincode" />
                    </div>
                </div>
            </x-mary-card>

            {{-- Tax & Business Information --}}
            <x-mary-card>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <x-mary-icon name="o-document-text" class="w-5 h-5 text-purple-600" />
                        <span>Tax & Business Information</span>
                    </div>
                </x-slot:title>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-mary-input label="GSTIN" icon="o-document-text" placeholder="22AAAAA0000A1Z5" wire:model="gstin" />
                    <x-mary-input label="PAN" icon="o-identification" placeholder="AAAAA0000A" wire:model="pan" />
                    <x-mary-input label="TIN" icon="o-document" placeholder="TIN Number" wire:model="tin" />
                </div>
            </x-mary-card>

            {{-- Banking Information --}}
            <x-mary-card>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <x-mary-icon name="o-building-library" class="w-5 h-5 text-orange-600" />
                        <span>Banking Information</span>
                    </div>
                </x-slot:title>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-mary-input label="Bank Name" icon="o-building-library" placeholder="Bank name" wire:model="bank_name" />
                    <x-mary-input label="Branch" icon="o-map-pin" placeholder="Branch name" wire:model="branch" />
                    <x-mary-input label="Account Number" icon="o-credit-card" placeholder="Account number" wire:model="account_number" />
                    <x-mary-input label="IFSC Code" icon="o-hashtag" placeholder="IFSC Code" wire:model="ifsc_code" />
                </div>
            </x-mary-card>

            {{-- Status Settings --}}
            <x-mary-card>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <x-mary-icon name="o-cog-6-tooth" class="w-5 h-5 text-gray-600" />
                        <span>Status Settings</span>
                    </div>
                </x-slot:title>

                <x-mary-toggle label="Active Status" wire:model="is_active" />
            </x-mary-card>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" @click="$wire.cancel" />
            <x-mary-button
                label="{{ $isEdit ? 'Update' : 'Create' }}"
                class="btn-primary"
                @click="$wire.saveClient"
                spinner="saveClient" />
        </x-slot:actions>
    </x-mary-modal>


    <!-- Clients Table -->
    <x-mary-card class="bg-base-200">
        <div class="flex justify-end mb-4 gap-2">
            <x-mary-button
                class="btn-secondary"
                :badge="count($selected)"
                label="Toggle Status"
                icon="o-arrow-path"
                wire:click="toggleStatus"
                spinner="toggleStatus"
                :disabled="count($selected) === 0" />
            <x-mary-button icon="o-plus" class="btn-primary" @click="$wire.newClient" />
        </div>
        <x-mary-hr />

        <x-mary-table
            :headers="$headers"
            :rows="$clients"
            striped
            :sort-by="$sortBy"
            per-page="perPage"
            :row-decoration="$row_decoration"
            :per-page-values="[5, 10, 20, 50]"
            with-pagination
            show-empty-text
            empty-text="No clients found!"
            wire:model.live="selected"
            selectable>

            @scope('cell_sl_no', $row)
            <span class="font-medium">{{ $loop->index + 1 + 0 }}</span>
            @endscope

            @scope('cell_name', $row)
            <div class="font-medium">{{ $row->name }}</div>
            @endscope

            @scope('cell_company', $row)
            @if($row->company)
            <span class="text-sm">{{ $row->company }}</span>
            @else
            <span class="text-gray-400 text-xs">Not specified</span>
            @endif
            @endscope

            @scope('cell_contact_person', $row)
            @if($row->contact_person)
            <span class="text-sm">{{ $row->contact_person }}</span>
            @else
            <span class="text-gray-400 text-xs">Not specified</span>
            @endif
            @endscope

            @scope('cell_phone', $row)
            @if($row->phone)
            <a href="tel:{{ $row->phone }}" class="text-sm text-blue-600 hover:underline">{{ $row->phone }}</a>
            @else
            <span class="text-gray-400 text-xs">Not specified</span>
            @endif
            @endscope

            @scope('cell_is_active', $row)
            <x-mary-badge
                :value="$row->is_active ? 'Active' : 'Inactive'"
                :class="$row->is_active ? 'badge-success badge-soft' : 'badge-error badge-soft'" />
            @endscope

            @scope('cell_actions', $row)
            <div class="flex gap-2 justify-center items-center">
                <x-mary-button
                    icon="o-eye"
                    spinner
                    class="btn-circle btn-ghost btn-xs"
                    tooltip-left="View Details"
                    @click="$wire.showDetails({{ $row->id }})" />
                <x-mary-button
                    icon="o-pencil"
                    spinner
                    class="btn-circle btn-ghost btn-xs"
                    tooltip-left="Edit"
                    @click="$wire.editClient({{ $row->id }})" />
                <x-mary-button
                    icon="o-trash"
                    spinner
                    class="btn-circle btn-ghost btn-xs btn-error"
                    tooltip-left="Delete"
                    @click="$wire.confirmDeletion({{ $row->id }})" />
            </div>
            @endscope
        </x-mary-table>
    </x-mary-card>

    {{-- Client Details Modal --}}
    <x-mary-modal wire:model="showDetailsModal" box-class="max-w-5xl"
        title="{{ $selectedClient?->name ?? 'Client' }} - Complete Details & Ledger">

        @if($selectedClient)
        {{-- Statistics Cards --}}
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                <div class="text-2xl font-bold text-yellow-600">
                    {{ $selectedClient->ledger?->getFormattedBalance() ?? '₹0.00 Cr' }}
                </div>
                <div class="text-sm text-yellow-600">Current Balance</div>
            </div>
            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                <div class="text-2xl font-bold text-green-600">
                    ₹{{ number_format($selectedClient->ledger?->transactions->where('type', 'sale')->sum('debit_amount') ?? 0, 2) }}
                </div>
                <div class="text-sm text-green-600">Total Sales</div>
            </div>
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <div class="text-2xl font-bold text-blue-600">{{ $selectedClient->ledger?->transactions->count() ?? 0 }}</div>
                <div class="text-sm text-blue-600">Transactions</div>
            </div>
        </div>

        {{-- Tab Navigation --}}
        <div class="mb-6">
            <div class="bg-gray-100 rounded-xl p-1 inline-flex">
                <button
                    wire:click="switchTab('details')"
                    class="px-5 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 {{ $activeTab === 'details' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                    Client Details
                </button>
                <button
                    wire:click="switchTab('ledger')"
                    class="px-5 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 {{ $activeTab === 'ledger' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                    Ledger
                </button>
                <button
                    wire:click="switchTab('transaction')"
                    class="px-5 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 {{ $activeTab === 'transaction' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                    Add Transaction
                </button>
            </div>
        </div>

        {{-- Persistent Tab Content Containers --}}
        <div class="tab-content-wrapper">
            {{-- Details Tab --}}
            <div wire:key="client-details-tab"
                class="space-y-6 {{ $activeTab === 'details' ? '' : 'hidden' }}">

                {{-- Row 1: Basic Information & Address --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Basic Information Card --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <x-mary-icon name="o-information-circle" class="w-5 h-5 text-gray-600 mr-2" />
                            <h3 class="text-lg font-semibold text-gray-900">Basic Information</h3>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Name</dt>
                                <dd class="text-base font-semibold text-gray-900">{{ $selectedClient->name }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Company</dt>
                                <dd class="text-base text-gray-900">{{ $selectedClient->company ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Contact Person</dt>
                                <dd class="text-base text-gray-900">{{ $selectedClient->contact_person ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Email</dt>
                                <dd class="text-base text-gray-900">{{ $selectedClient->email ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Phone</dt>
                                <dd class="text-base text-gray-900">{{ $selectedClient->phone ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1">
                                    <x-mary-badge
                                        :value="$selectedClient->is_active ? 'Active' : 'Inactive'"
                                        :class="$selectedClient->is_active ? 'badge-success' : 'badge-error'" />
                                </dd>
                            </div>
                        </div>
                    </div>

                    {{-- Address Card --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <x-mary-icon name="o-map-pin" class="w-5 h-5 text-gray-600 mr-2" />
                            <h3 class="text-lg font-semibold text-gray-900">Address</h3>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Address</dt>
                                <dd class="text-base text-gray-900">{{ $selectedClient->address ?? '-' }}</dd>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">City</dt>
                                    <dd class="text-base text-gray-900">{{ $selectedClient->city ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">State</dt>
                                    <dd class="text-base text-gray-900">{{ $selectedClient->state ?? '-' }}</dd>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Country</dt>
                                    <dd class="text-base text-gray-900">{{ $selectedClient->country ?? '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Pincode</dt>
                                    <dd class="text-base text-gray-900">{{ $selectedClient->pincode ?? '-' }}</dd>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Row 2: Tax & Business Information & Banking Information --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Tax & Business Information Card --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <x-mary-icon name="o-document-text" class="w-5 h-5 text-gray-600 mr-2" />
                            <h3 class="text-lg font-semibold text-gray-900">Tax & Business Information</h3>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">GSTIN</dt>
                                <dd class="text-base font-mono text-gray-900">{{ $selectedClient->gstin ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">PAN</dt>
                                <dd class="text-base font-mono text-gray-900">{{ $selectedClient->pan ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">TIN</dt>
                                <dd class="text-base font-mono text-gray-900">{{ $selectedClient->tin ?? '-' }}</dd>
                            </div>
                        </div>
                    </div>

                    {{-- Banking Information Card --}}
                    <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                        <div class="flex items-center mb-4">
                            <x-mary-icon name="o-building-library" class="w-5 h-5 text-gray-600 mr-2" />
                            <h3 class="text-lg font-semibold text-gray-900">Banking Information</h3>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Bank Name</dt>
                                <dd class="text-base text-gray-900">{{ $selectedClient->bank_name ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Branch</dt>
                                <dd class="text-base text-gray-900">{{ $selectedClient->branch ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">Account Number</dt>
                                <dd class="text-base font-mono text-gray-900">{{ $selectedClient->account_number ?? '-' }}</dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500">IFSC Code</dt>
                                <dd class="text-base font-mono text-gray-900">{{ $selectedClient->ifsc_code ?? '-' }}</dd>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Ledger Tab --}}
            <div wire:key="client-ledger-tab"
                class="space-y-6 {{ $activeTab === 'ledger' ? '' : 'hidden' }}">

                {{-- Filter and Download Controls --}}
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <label class="text-sm font-medium text-gray-700">Show:</label>
                        <x-mary-select
                            wire:model.live="transactionFilter"
                            :options="[
                                ['id' => '10', 'name' => 'Last 10 Transactions'],
                                ['id' => '100', 'name' => 'Last 100 Transactions'],
                                ['id' => 'month', 'name' => 'Last Month'],
                                ['id' => '3month', 'name' => 'Last 3 Months'],
                                ['id' => 'all', 'name' => 'All Transactions'],
                            ]"
                            class="w-48" />
                    </div>

                    <x-mary-button
                        wire:click="downloadLedgerPdf"
                        icon="o-arrow-down-tray"
                        class="btn-primary"
                        spinner="downloadLedgerPdf">
                        Download PDF
                    </x-mary-button>
                </div>

                {{-- Transaction History --}}
                <div>
                    <h3 class="text-lg font-semibold mb-4">Transaction History</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Debit</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Credit</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @php
                                $runningBalance = $selectedClient->ledger?->opening_balance ?? 0;
                                @endphp
                                @forelse($this->filteredTransactions as $transaction)
                                @php
                                $runningBalance += ($transaction->debit_amount - $transaction->credit_amount);
                                @endphp
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $transaction->date->format('d/m/Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <x-mary-badge
                                            :value="ucfirst($transaction->type)"
                                            :class="match($transaction->type) {
                                            'sale' => 'badge-info',
                                            'payment' => 'badge-success', 
                                            'return' => 'badge-warning',
                                            'adjustment' => 'badge-secondary',
                                            default => 'badge-ghost'
                                        }" />
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $transaction->description }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-mono text-green-600">
                                        {{ $transaction->debit_amount > 0 ? '₹' . number_format($transaction->debit_amount, 2) : '' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-mono text-red-600">
                                        {{ $transaction->credit_amount > 0 ? '₹' . number_format($transaction->credit_amount, 2) : '' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-mono font-bold">
                                        ₹{{ number_format(abs($runningBalance), 2) }} {{ $runningBalance >= 0 ? 'Dr' : 'Cr' }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No transactions found for selected filter.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Transaction Count Info --}}
                    <div class="mt-4 text-sm text-gray-600">
                        Showing {{ $this->filteredTransactions->count() }} transactions
                    </div>
                </div>
            </div>

            {{-- Transaction Tab --}}
            <div wire:key="client-transaction-tab"
                class="space-y-6 {{ $activeTab === 'transaction' ? '' : 'hidden' }}">

                <h3 class="text-lg font-semibold mb-4">Add New Transaction</h3>

                {{-- Payment Allocation Preview --}}
                @if($newTransaction['type'] === 'payment' && $newTransaction['amount'] > 0)
                @php
                $preview = $this->getPaymentAllocationPreview();
                @endphp

                @if(!empty($preview['allocations']))
                <div class="bg-info/10 p-4 rounded-lg mb-4">
                    <h4 class="font-semibold mb-2">Payment Allocation Preview:</h4>
                    <div class="space-y-2 text-sm">
                        @foreach($preview['allocations'] as $allocation)
                        <div class="flex justify-between">
                            <span>{{ $allocation['invoice_number'] }} ({{ $allocation['invoice_date'] }})</span>
                            <span class="font-semibold">₹{{ number_format($allocation['will_allocate'], 2) }}</span>
                        </div>
                        @endforeach

                        @if($preview['remaining'] > 0)
                        <div class="border-t pt-2 mt-2 text-warning">
                            <strong>Advance Payment:</strong> ₹{{ number_format($preview['remaining'], 2) }}
                        </div>
                        @endif
                    </div>
                </div>
                @endif
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-mary-input
                        label="Date"
                        type="date"
                        wire:model="newTransaction.date"
                        required />

                    <x-mary-select
                        label="Transaction Type"
                        wire:model.live="newTransaction.type"
                        :options="[
                ['id' => 'sale', 'name' => 'Sale'],
                ['id' => 'payment', 'name' => 'Payment'],
                ['id' => 'return', 'name' => 'Return'],
                ['id' => 'adjustment', 'name' => 'Adjustment'],
            ]"
                        option-value="id"
                        option-label="name"
                        required />

                    <x-mary-input
                        label="Amount"
                        type="number"
                        step="0.01"
                        min="0"
                        wire:model.live.number="newTransaction.amount"
                        prefix="₹"
                        required />

                    <x-mary-input
                        label="Reference"
                        wire:model="newTransaction.reference"
                        placeholder="Invoice number, etc." />

                    {{-- Payment Method Selection --}}
                    <x-mary-select
                        label="Payment Method"
                        wire:model.live="newTransaction.payment_method"
                        :options="[
                ['id' => 'cash', 'name' => 'Cash'],
                ['id' => 'bank', 'name' => 'Bank Transfer'],
            ]"
                        option-value="id"
                        option-label="name"
                        icon="o-credit-card"
                        required />

                    {{-- Bank Account Selection (shown only if payment method is bank) --}}
                    @if($newTransaction['payment_method'] === 'bank')
                    <x-mary-select
                        label="Bank Account *"
                        wire:model="newTransaction.bank_account_id"
                        :options="$bankAccounts"
                        option-value="id"
                        option-label="display_name"
                        icon="o-building-library"
                        placeholder="Select bank account"
                        hint="Select the bank account for this transaction"
                        :error="$errors->first('newTransaction.bank_account_id')"
                        required />
                    @else
                    <div></div> {{-- Spacer to maintain grid layout --}}
                    @endif

                    <div class="md:col-span-2">
                        <x-mary-textarea
                            label="Description"
                            wire:model="newTransaction.description"
                            placeholder="Transaction description"
                            rows="2"
                            required />
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-mary-button
                        label="Add Transaction"
                        class="btn-primary"
                        icon="o-plus"
                        wire:click="addTransaction"
                        spinner="addTransaction" />
                </div>
            </div>
        </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Close" @click="$wire.showDetailsModal = false" />
        </x-slot:actions>
    </x-mary-modal>


    <!-- Delete Confirmation Modal -->
    <x-mary-modal wire:model="showConfirmDeleteModal" title="Confirm Deletion">
        <div>Are you sure you want to delete this client? This action cannot be undone.</div>

        <x-slot:actions>
            <x-mary-button label="Cancel" @click="$wire.cancelDeletion" />
            <x-mary-button label="Delete" class="btn-error" @click="$wire.deleteClient" />
        </x-slot:actions>
    </x-mary-modal>

    <!-- Filter Drawer -->
    <x-mary-drawer wire:model="showDrawer" title="Filters" right separator with-close-button>
        <div class="space-y-6">
            <div>
                <x-mary-header title="Status Filter" size="text-base" />
                <x-mary-choices-offline
                    wire:model="statusFilter"
                    :options="$statusOptions"
                    multiple
                    searchable />
            </div>

            <div>
                <x-mary-header title="Location Filter" size="text-base" />
                <x-mary-choices-offline
                    wire:model="locationFilter"
                    :options="$locationOptions"
                    multiple
                    searchable />
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Clear All" wire:click="resetFilters" />
            <x-mary-button label="Apply Filters" class="btn-primary" wire:click="applyFilters" />
        </x-slot:actions>
    </x-mary-drawer>

    <x-mary-toast />
</div>