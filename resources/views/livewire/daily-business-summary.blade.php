<div>
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Daily Business Summary</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Track your daily business performance with opening/closing balances and cash flow.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <x-mary-select wire:model.live="viewMode" label="View Mode" :options="[
                    ['id' => 'daily', 'name' => 'Daily'],
                    ['id' => 'weekly', 'name' => 'Weekly'],
                    ['id' => 'monthly', 'name' => 'Monthly'],
                ]" />
            </div>
        </div>

        {{-- Date Range --}}
        <div class="mt-4 flex flex-wrap gap-4">
            <x-mary-datetime wire:model.live="dateFrom" label="From Date" icon="o-calendar" />
            <x-mary-datetime wire:model.live="dateTo" label="To Date" icon="o-calendar" />
        </div>
    </div>

    {{-- Total Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <x-mary-stat
            title="Opening Balance"
            :value="'₹ ' . number_format($totalSummary['opening_balance'], 2)"
            icon="o-banknotes"
            class="bg-gradient-to-br from-blue-500 to-blue-600 text-white" />
        <x-mary-stat
            title="Net Cash Flow"
            :value="'₹ ' . number_format($totalSummary['net_cash_flow'], 2)"
            icon="o-arrow-trending-up"
            :class="$totalSummary['net_cash_flow'] >= 0 ? 'bg-gradient-to-br from-green-500 to-green-600 text-white' : 'bg-gradient-to-br from-red-500 to-red-600 text-white'" />
        <x-mary-stat
            title="Closing Balance"
            :value="'₹ ' . number_format($totalSummary['closing_balance'], 2)"
            icon="o-banknotes"
            class="bg-gradient-to-br from-emerald-500 to-emerald-600 text-white" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Daily Summary Table --}}
        <x-mary-card title="Daily Breakdown">
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Sales</th>
                            <th>Purchases</th>
                            <th>Expenses</th>
                            <th>Recv</th>
                            <th>Paid</th>
                            <th>Net Flow</th>
                            <th>Closing</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dailySummary as $day)
                        <tr class="hover">
                            <td class="font-medium">{{ $day['date_display'] }}</td>
                            <td class="text-green-600 font-medium">₹{{ number_format($day['total_sales'], 2) }}</td>
                            <td class="text-orange-600">₹{{ number_format($day['total_purchases'], 2) }}</td>
                            <td class="text-red-600">₹{{ number_format($day['total_expenses'], 2) }}</td>
                            <td class="text-blue-600">₹{{ number_format($day['payments_received'], 2) }}</td>
                            <td class="text-purple-600">₹{{ number_format($day['payments_given'], 2) }}</td>
                            <td class="{{ $day['net_cash_flow'] >= 0 ? 'text-green-600' : 'text-red-600' }} font-medium">
                                ₹{{ number_format($day['net_cash_flow'], 2) }}
                            </td>
                            <td class="font-bold text-emerald-600">₹{{ number_format($day['closing_balance'], 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-gray-400">
                                <x-mary-icon name="o-calendar" class="w-12 h-12 mx-auto mb-2" />
                                No data available for selected period
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-mary-card>

        {{-- Cash Flow Chart --}}
        <x-mary-card title="Cash Flow Trend">
            <div class="h-80">
                <x-mary-chart wire:model.live="dailyCashFlowChart" />
            </div>
        </x-mary-card>
    </div>

    {{-- Period Totals --}}
    <x-mary-card title="Period Totals">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
            <div class="text-center p-6 bg-gray-50 dark:bg-gray-800 rounded-xl">
                <p class="text-3xl font-bold text-blue-600">₹{{ number_format($totalSummary['total_sales'], 2) }}</p>
                <p class="text-sm font-medium text-gray-600 mt-1">Total Sales</p>
            </div>
            <div class="text-center p-6 bg-gray-50 dark:bg-gray-800 rounded-xl">
                <p class="text-3xl font-bold text-orange-600">₹{{ number_format($totalSummary['total_purchases'], 2) }}</p>
                <p class="text-sm font-medium text-gray-600 mt-1">Total Purchases</p>
            </div>
            <div class="text-center p-6 bg-gray-50 dark:bg-gray-800 rounded-xl">
                <p class="text-3xl font-bold text-red-600">₹{{ number_format($totalSummary['total_expenses'], 2) }}</p>
                <p class="text-sm font-medium text-gray-600 mt-1">Total Expenses</p>
            </div>
            <div class="text-center p-6 bg-gray-50 dark:bg-gray-800 rounded-xl">
                <p class="text-3xl font-bold text-emerald-600">₹{{ number_format($totalSummary['payments_received'], 2) }}</p>
                <p class="text-sm font-medium text-gray-600 mt-1">Payments Received</p>
            </div>
            <div class="text-center p-6 bg-gray-50 dark:bg-gray-800 rounded-xl">
                <p class="text-3xl font-bold text-purple-600">₹{{ number_format($totalSummary['payments_given'], 2) }}</p>
                <p class="text-sm font-medium text-gray-600 mt-1">Payments Given</p>
            </div>
            <div class="text-center p-6 bg-gradient-to-br from-emerald-500 to-emerald-600 text-white rounded-xl">
                <p class="text-3xl font-bold">₹{{ number_format($totalSummary['net_cash_flow'], 2) }}</p>
                <p class="text-sm font-medium">Net Cash Flow</p>
            </div>
        </div>
    </x-mary-card>
</div>