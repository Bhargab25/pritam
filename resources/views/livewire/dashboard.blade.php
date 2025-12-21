<div>
    {{-- Header with Period Selector --}}
    <div class="mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Dashboard</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Welcome back! Here's what's happening with your business.
                </p>
            </div>

            {{-- Period Selector --}}
            <div class="flex flex-wrap gap-2">
                <x-mary-button label="Today" wire:click="setPeriod('today')"
                    :class="$period === 'today' ? 'btn-primary' : 'btn-outline'" xs />
                <x-mary-button label="This Week" wire:click="setPeriod('this_week')"
                    :class="$period === 'this_week' ? 'btn-primary' : 'btn-outline'" xs />
                <x-mary-button label="This Month" wire:click="setPeriod('this_month')"
                    :class="$period === 'this_month' ? 'btn-primary' : 'btn-outline'" xs />
                <x-mary-button label="This Year" wire:click="setPeriod('this_year')"
                    :class="$period === 'this_year' ? 'btn-primary' : 'btn-outline'" xs />
            </div>
        </div>

        {{-- Custom Date Range --}}
        <div class="mt-4 flex flex-wrap gap-4">
            <x-mary-datetime wire:model.live="dateFrom" label="From" icon="o-calendar" />
            <x-mary-datetime wire:model.live="dateTo" label="To" icon="o-calendar" />
        </div>
    </div>

    {{-- Key Metrics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        {{-- Total Sales --}}
        <x-mary-stat
            title="Total Sales"
            description="Period sales revenue"
            :value="'₹ ' . number_format($totalSales, 2)"
            icon="o-chart-bar-square"
            class="bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-lg" />

        {{-- Total Expenses --}}
        <x-mary-stat
            title="Total Expenses"
            description="Period expenses"
            :value="'₹ ' . number_format($totalExpenses, 2)"
            icon="o-receipt-refund"
            class="bg-gradient-to-br from-red-500 to-red-600 text-white shadow-lg" />

        {{-- Net Profit --}}
        <x-mary-stat
            title="Net Profit"
            description="Sales - Expenses"
            :value="'₹ ' . number_format($netProfit, 2)"
            icon="o-chart-bar"
            :class="$netProfit >= 0 
                ? 'bg-gradient-to-br from-green-500 to-green-600 text-white shadow-lg' 
                : 'bg-gradient-to-br from-orange-500 to-orange-600 text-white shadow-lg'" />

        {{-- Cash & Bank Balance --}}
        <x-mary-stat
            title="Cash & Bank"
            description="Available balance"
            :value="'₹ ' . number_format($cashBalance + $bankBalance, 2)"
            icon="o-banknotes"
            class="bg-gradient-to-br from-purple-500 to-purple-600 text-white shadow-lg" />
    </div>

    {{-- Secondary Metrics --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        {{-- Outstanding Receivables --}}
        <x-mary-card title="Outstanding Receivables" class="shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-3xl font-bold text-orange-600">₹{{ number_format($totalOutstanding, 2) }}</p>
                    <p class="text-sm text-gray-500 mt-1">{{ $unpaidInvoices }} unpaid invoices</p>
                </div>
                <x-mary-icon name="o-arrow-trending-up" class="w-12 h-12 text-orange-300" />
            </div>
        </x-mary-card>

        {{-- Outstanding Payables --}}
        <x-mary-card title="Outstanding Payables" class="shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-3xl font-bold text-red-600">₹{{ number_format($totalPayable, 2) }}</p>
                    <p class="text-sm text-gray-500 mt-1">To suppliers</p>
                </div>
                <x-mary-icon name="o-arrow-trending-down" class="w-12 h-12 text-red-300" />
            </div>
        </x-mary-card>

        {{-- Total Invoices --}}
        <x-mary-card title="Invoices" class="shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-3xl font-bold text-blue-600">{{ $totalInvoices }}</p>
                    <p class="text-sm text-gray-500 mt-1">{{ $paidInvoices }} paid, {{ $unpaidInvoices }} pending</p>
                </div>
                <x-mary-icon name="o-document-text" class="w-12 h-12 text-blue-300" />
            </div>
        </x-mary-card>

        {{-- Low Stock Alert --}}
        <x-mary-card title="Low Stock Alert" class="shadow-md">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-3xl font-bold text-yellow-600">{{ $lowStockProducts }}</p>
                    <p class="text-sm text-gray-500 mt-1">Products need restock</p>
                </div>
                <x-mary-icon name="o-exclamation-triangle" class="w-12 h-12 text-yellow-300" />
            </div>
        </x-mary-card>
    </div>

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Sales vs Expenses Chart --}}
    <x-mary-card title="Sales vs Expenses (Daily)" class="shadow-md">
        <div class="h-64">
            <x-mary-chart wire:model.live="salesExpensesChart" />
        </div>
    </x-mary-card>

    {{-- Monthly Comparison Chart --}}
    <x-mary-card title="Monthly Comparison (Last 6 Months)" class="shadow-md">
        <div class="h-64">
            <x-mary-chart wire:model.live="monthlyComparisonChart" />
        </div>
    </x-mary-card>
</div>

    {{-- Data Tables Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Recent Invoices --}}
        <x-mary-card title="Recent Invoices" class="shadow-md">
            @if($recentInvoices->count() > 0)
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentInvoices as $invoice)
                        <tr>
                            <td class="font-medium">{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->client?->name ?? $invoice->client_name }}</td>
                            <td>{{ $invoice->invoice_date->format('d M Y') }}</td>
                            <td>₹{{ number_format($invoice->total_amount, 2) }}</td>
                            <td>
                                <x-mary-badge
                                    :value="ucfirst($invoice->payment_status)"
                                    :class="match($invoice->payment_status) {
                                                'paid' => 'badge-success',
                                                'partial' => 'badge-warning',
                                                'unpaid' => 'badge-error',
                                                default => 'badge-ghost'
                                            }" />
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-8 text-gray-400">
                <x-mary-icon name="o-document" class="w-12 h-12 mx-auto mb-2" />
                <p>No invoices found</p>
            </div>
            @endif
        </x-mary-card>

        {{-- Recent Expenses --}}
        <x-mary-card title="Recent Expenses" class="shadow-md">
            @if($recentExpenses->count() > 0)
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Ref #</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentExpenses as $expense)
                        <tr>
                            <td class="font-medium">{{ $expense->expense_ref }}</td>
                            <td>{{ $expense->category?->name ?? 'Uncategorized' }}</td>
                            <td>{{ $expense->expense_date->format('d M Y') }}</td>
                            <td class="text-red-600">₹{{ number_format($expense->amount, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-8 text-gray-400">
                <x-mary-icon name="o-receipt-refund" class="w-12 h-12 mx-auto mb-2" />
                <p>No expenses found</p>
            </div>
            @endif
        </x-mary-card>
    </div>

    {{-- Bottom Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Top Clients --}}
        <x-mary-card title="Top Clients by Sales" class="shadow-md">
            @if($topClients->count() > 0)
            <div class="space-y-3">
                @foreach($topClients as $index => $item)
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold">
                            {{ $index + 1 }}
                        </div>
                        <div>
                            <p class="font-medium">{{ $item->client?->name ?? 'Unknown' }}</p>
                            <p class="text-sm text-gray-500">{{ $item->client?->city }}</p>
                        </div>
                    </div>
                    <p class="text-lg font-bold text-green-600">₹{{ number_format($item->total_sales, 2) }}</p>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-8 text-gray-400">
                <x-mary-icon name="o-users" class="w-12 h-12 mx-auto mb-2" />
                <p>No client data found</p>
            </div>
            @endif
        </x-mary-card>

        {{-- Low Stock Items --}}
        <x-mary-card title="Low Stock Items" class="shadow-md">
            @if($lowStockItems->count() > 0)
            <div class="space-y-3">
                @foreach($lowStockItems as $product)
                <div class="flex items-center justify-between p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                    <div>
                        <p class="font-medium">{{ $product->name }}</p>
                        <p class="text-sm text-gray-500">SKU: {{ $product->sku }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-orange-600">{{ $product->stock_quantity }} {{ $product->unit }}</p>
                        @if(isset($product->minimum_stock_level))
                        <p class="text-xs text-gray-500">Min: {{ $product->minimum_stock_level }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-8 text-gray-400">
                <x-mary-icon name="o-check-circle" class="w-12 h-12 mx-auto mb-2 text-green-500" />
                <p>All products are well stocked!</p>
            </div>
            @endif
        </x-mary-card>
    </div>

</div>