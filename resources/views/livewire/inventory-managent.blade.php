<div>
    <x-mary-header title="Inventory" subtitle="Manage Inventory" separator>
        <x-slot:middle class="!justify-end">
            <div class="flex gap-2 items-center">
                <x-mary-button icon="o-plus" label="Add Stock" class="btn-primary" @click="$wire.openAddStockModal" />
                <x-mary-button icon="o-arrow-up-tray" class="btn-secondary" label="Import Stock" wire:click="openImportModal" />
            </div>
        </x-slot:middle>
    </x-mary-header>

    {{-- Low stock alert --}}
    @if($lowStockCount > 0)
    <x-mary-alert icon="o-exclamation-triangle" class="alert-error alert-outline">
        {{ $lowStockCount }} item(s) are running low on stock.
    </x-mary-alert>
    @endif

    <div class="mb-6 mt-4">
        <div class="bg-gray-100 rounded-xl p-1 inline-flex">
            <button
                wire:click="switchTab('inventory')"
                class="px-5 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 {{ $activeTab === 'inventory' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                Inventory
            </button>
            <button
                wire:click="switchTab('reports')"
                class="px-5 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 {{ $activeTab === 'reports' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                Reports
            </button>
            <!-- <button
                wire:click="switchTab('low_stock')"
                class="px-5 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 {{ $activeTab === 'low_stock' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                Low Stock
            </button> -->
        </div>
    </div>
    {{-- Persistent Tab Content Containers --}}
    <div class="tab-content-wrapper">

        <div wire:key="supplier-details-tab" class="space-y-6 {{ $activeTab === 'inventory' ? '' : 'hidden' }}">
            {{-- Inventory Overview --}}
            <div class="flex flex-wrap gap-4 mb-6">
                <div class="flex-1 min-w-[200px] p-4 rounded-lg border flex items-center gap-3">
                    <x-mary-icon name="o-cube" class="text-blue-500 text-2xl" />
                    <div class="flex flex-col">
                        <div class="text-2xl font-bold">{{ $totalItems }}</div>
                        <div class="text-sm">Total Items</div>
                    </div>
                </div>
                <div class="flex-1 min-w-[200px] p-4 rounded-lg border flex items-center gap-3">
                    <x-mary-icon name="o-chevron-double-down" class="text-orange-500 text-2xl" />
                    <div class="flex flex-col">
                        <div class="text-2xl font-bold">{{ $lowStockCount }}</div>
                        <div class="text-sm">Low Stock</div>
                    </div>
                </div>
                <div class="flex-1 min-w-[200px] p-4 rounded-lg border flex items-center gap-3">
                    <x-mary-icon name="o-exclamation-triangle" class="text-red-500 text-2xl" />
                    <div class="flex flex-col">
                        <div class="text-2xl font-bold">{{ $outOfStockCount }}</div>
                        <div class="text-sm">Out of Stock</div>
                    </div>
                </div>
                <div class="flex-1 min-w-[200px] p-4 rounded-lg border flex items-center gap-3">
                    <x-mary-icon name="o-currency-rupee" class="text-green-500 text-2xl" />
                    <div class="flex flex-col">
                        <div class="text-2xl font-bold">₹{{ number_format($totalValue) }}</div>
                        <div class="text-sm">Total Value</div>
                    </div>
                </div>
            </div>

            {{-- Inventory Table --}}
            <x-mary-card class="bg-base-200">
                {{-- Search and Filters --}}
                <div class="flex flex-wrap gap-4 mb-4 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <x-mary-input
                            label="Search Products"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search by product name..."
                            icon="o-magnifying-glass" />
                    </div>
                    <div class="min-w-[150px]">
                        <x-mary-select
                            label="Category"
                            wire:model.live="categoryFilter"
                            placeholder="All Categories"
                            :options="$categories"
                            option-value="id"
                            option-label="name" />
                    </div>
                    <div class="min-w-[150px]">
                        <x-mary-select
                            label="Stock Status"
                            wire:model.live="statusFilter"
                            placeholder="All Status"
                            icon="o-flag"
                            :options="[
                                ['value' => 'in_stock', 'label' => 'In Stock'],
                                ['value' => 'low_stock', 'label' => 'Low Stock'],
                                ['value' => 'out_of_stock', 'label' => 'Out of Stock'],
                            ]"
                            option-value="value"
                            option-label="label" />
                    </div>
                </div>

                <x-mary-hr />

                <x-mary-table
                    :headers="$headers"
                    :rows="$products"
                    striped
                    :sort-by="$sortBy"
                    :per-page="$perPage"
                    :row-decoration="$row_decoration"
                    :per-page-values="[5, 10, 20, 50]"
                    with-pagination
                    show-empty-text
                    empty-text="No products found!"
                    wire:model.live="selected"
                    selectable>

                    @scope('cell_sl_no', $row)
                    <span class="font-medium">{{ $loop->iteration }}</span>
                    @endscope

                    @scope('cell_name', $row)
                    <div class="font-medium">{{ $row->name }}</div>
                    @endscope

                    @scope('cell_category', $row)
                    <span class="text-sm">{{ $row->category->name }}</span>
                    @endscope

                    @scope('cell_stock_quantity', $row)
                    <div class="text-right">
                        <span class="font-mono text-lg">{{ number_format($row->stock_quantity, 2) }}</span>
                    </div>
                    @endscope

                    @scope('cell_min_stock_quantity', $row)
                    <div class="text-right">
                        <span class="font-mono text-sm text-gray-600">{{ number_format($row->min_stock_quantity, 2) }}</span>
                    </div>
                    @endscope

                    @scope('cell_unit', $row)
                    <x-mary-badge :value="strtoupper($row->unit)" class="badge-outline" />
                    @endscope

                    @scope('cell_status', $row)
                    <x-mary-badge
                        :value="$row->stock_status"
                        :class="$row->stock_status_color . ' badge-soft'" />
                    @endscope

                    @scope('cell_actions', $row)
                    <div class="flex gap-2 justify-center items-center">
                        <x-mary-button
                            icon="o-clock"
                            class="btn-circle btn-ghost btn-xs btn-info"
                            tooltip-left="View History"
                            @click="$wire.showProductHistory({{ $row->id }})" />
                        <x-mary-button
                            icon="o-minus-circle"
                            class="btn-circle btn-ghost btn-xs btn-warning"
                            tooltip-left="Add Adjustment"
                            @click="$wire.openAdjustmentModal({{ $row->id }})" />
                    </div>
                    @endscope
                </x-mary-table>
            </x-mary-card>

        </div>

        {{-- Ledger Tab - Always present, controlled by CSS --}}
        <div wire:key="supplier-ledger-tab" class="space-y-6 {{ $activeTab === 'reports' ? '' : 'hidden' }}">
            <x-mary-card class="bg-base-200">
                <div class="flex flex-wrap gap-4 items-end mb-4">
                    <div class="flex-1 min-w-[150px]">
                        <x-mary-input
                            label="From Date"
                            wire:model.live="reportDateFrom"
                            type="date" />
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <x-mary-input
                            label="To Date"
                            wire:model.live="reportDateTo"
                            type="date" />
                    </div>
                    <div class="flex gap-2">
                        <x-mary-button
                            icon="o-arrow-down-tray"
                            class="btn-primary"
                            wire:click="downloadStockReport"
                            label="Download Stock Report" />
                        <x-mary-button
                            icon="o-arrow-down-tray"
                            class="btn-secondary"
                            wire:click="downloadCategoryReport"
                            label="Download Category Report" />
                    </div>
                </div>
            </x-mary-card>

            {{-- Charts Row --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Stock by Category Chart --}}
                <x-mary-card class="bg-base-200">
                    <x-mary-header title="Stock by Category" subtitle="Total stock quantity per category" />
                    <div id="categoryStockChart" style="height: 300px;"></div>
                </x-mary-card>

                {{-- Category Distribution Chart --}}
                <x-mary-card class="bg-base-200">
                    <x-mary-header title="Product Distribution" subtitle="Number of products per category" />
                    <div id="categoryDistributionChart" style="height: 300px;"></div>
                </x-mary-card>
            </div>

            {{-- Monthly Stock Movement Chart --}}
            <x-mary-card class="bg-base-200">
                <x-mary-header title="Stock Movements (Last 6 Months)" subtitle="In vs Out stock movements" />
                <div id="monthlyMovementsChart" style="height: 400px;"></div>
            </x-mary-card>

            {{-- Top Lists Row --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Top 10 Products by Stock --}}
                <x-mary-card class="bg-base-200">
                    <x-mary-header title="Top 10 Products by Stock" subtitle="Highest stock quantities" />
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        @foreach($reportData['topProducts'] as $index => $product)
                        <div class="flex items-center justify-between p-3 bg-base-100 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-primary text-primary-content rounded-full flex items-center justify-center text-sm font-bold">
                                    {{ $index + 1 }}
                                </div>
                                <div>
                                    <div class="font-medium">{{ $product->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $product->category->name }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-lg">{{ number_format($product->stock_quantity, 2) }}</div>
                                <div class="text-sm text-gray-500">{{ strtoupper($product->unit) }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </x-mary-card>

                {{-- Low Stock Alert List --}}
                <x-mary-card class="bg-base-200">
                    <x-mary-header title="Low Stock Alert" subtitle="Products requiring attention" />
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        @foreach($reportData['lowStockProducts'] as $product)
                        <div class="flex items-center justify-between p-3 bg-error/10 border border-error/20 rounded-lg">
                            <div class="flex items-center gap-3">
                                <x-mary-icon name="o-exclamation-triangle" class="text-error" />
                                <div>
                                    <div class="font-medium">{{ $product->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $product->category->name }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-error">{{ number_format($product->stock_quantity, 2) }}</div>
                                <div class="text-xs text-gray-500">Min: {{ $product->min_stock_quantity }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </x-mary-card>
            </div>

            {{-- Category Summary Table --}}
            <x-mary-card class="bg-base-200">
                <x-mary-header title="Category Summary" subtitle="Detailed breakdown by category" />
                <div class="overflow-x-auto">
                    <table class="table table-striped w-full">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-right">Total Products</th>
                                <th class="text-right">Total Stock</th>
                                <th class="text-right">Low Stock Items</th>
                                <th class="text-right">Stock Health</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reportData['categoryStock'] as $category)
                            <tr>
                                <td class="font-medium">{{ $category['name'] }}</td>
                                <td class="text-right">{{ $category['total_products'] }}</td>
                                <td class="text-right font-mono">{{ number_format($category['total_stock'], 2) }}</td>
                                <td class="text-right">
                                    @if($category['low_stock_count'] > 0)
                                    <span class="badge badge-error badge-soft">{{ $category['low_stock_count'] }}</span>
                                    @else
                                    <span class="badge badge-success badge-soft">0</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @php
                                    $healthPercentage = $category['total_products'] > 0 ?
                                    (($category['total_products'] - $category['low_stock_count']) / $category['total_products']) * 100 : 100;
                                    @endphp
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                            <div class="bg-success h-2 rounded-full" style="width: {{ $healthPercentage }}%"></div>
                                        </div>
                                        <span class="text-sm">{{ round($healthPercentage) }}%</span>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-mary-card>

            {{-- Recent Stock Movements --}}
            <x-mary-card class="bg-base-200">
                <x-mary-header title="Recent Stock Movements" subtitle="Latest inventory changes" />
                <div class="overflow-x-auto">
                    <table class="table table-striped w-full">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Type</th>
                                <th class="text-right">Quantity</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($reportData['stockMovements'] as $movement)
                            <tr>
                                <td class="font-mono text-sm">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                                <td class="font-medium">{{ $movement->product->name }}</td>
                                <td>
                                    @if($movement->type === 'in')
                                    <span class="badge badge-success badge-soft">Stock In</span>
                                    @else
                                    <span class="badge badge-error badge-soft">Stock Out</span>
                                    @endif
                                </td>
                                <td class="text-right font-mono">{{ number_format($movement->quantity, 2) }}</td>
                                <td class="text-sm text-gray-600">{{ $movement->reason ?: 'N/A' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-mary-card>
        </div>


        {{-- Transaction Tab - Always present, controlled by CSS --}}
        <div wire:key="supplier-transaction-tab" class="space-y-6 {{ $activeTab === 'low_stock' ? '' : 'hidden' }}">

        </div>
    </div>


    <!-- Add Stock Modal -->
    <x-mary-modal wire:model="showAddStockModal" box-class="backdrop-blur max-w-5xl" title="Add Stock" subtitle="Add stock via challan" class="backdrop-blur">
        <div class="space-y-6">
            <!-- Challan Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input
                    label="Challan Number"
                    wire:model="challanNumber"
                    placeholder="Auto-generated"
                    required />

                <x-mary-datetime
                    label="Challan Date"
                    wire:model="challanDate"
                    type="date"
                    required />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Fixed Supplier Select -->
                <x-mary-select
                    label="Supplier (Optional)"
                    wire:model="supplierId"
                    :options="$suppliers"
                    option-value="id"
                    option-label="name"
                    placeholder="Select supplier"
                    icon="o-truck" />

                <x-mary-textarea
                    label="Remarks (Optional)"
                    wire:model="remarks"
                    placeholder="Enter any remarks..."
                    rows="2" />
            </div>

            <!-- Stock Items -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Stock Items</h3>
                    <x-mary-button
                        icon="o-plus-circle"
                        class="btn-sm btn-secondary"
                        label="Add Item"
                        @click="$wire.addStockItem()" />
                </div>

                <!-- Dynamic Stock Items -->
                @foreach($stockItems as $index => $item)
                <div class="grid grid-cols-12 gap-4 items-end p-4 bg-base-100 rounded-lg border" wire:key="item-{{ $index }}">
                    <!-- Fixed Product Selection -->
                    <div class="col-span-5">
                        <x-mary-select
                            label="Product"
                            wire:model.live="stockItems.{{ $index }}.product_id"
                            :options="$allProducts"
                            option-value="id"
                            option-label="name"
                            placeholder="Select product"
                            icon="o-cube"
                            :error="$errors->first('stockItems.' . $index . '.product_id')" />
                    </div>

                    <!-- Quantity -->
                    <div class="col-span-2">
                        <x-mary-input
                            label="Quantity"
                            wire:model.live="stockItems.{{ $index }}.quantity"
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                            icon="o-scale"
                            :error="$errors->first('stockItems.' . $index . '.quantity')" />
                    </div>

                    <!-- Price (Optional) -->
                    <div class="col-span-2">
                        <x-mary-input
                            label="Unit Price"
                            wire:model.live="stockItems.{{ $index }}.price"
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                            prefix="₹"
                            icon="o-currency-rupee" />
                    </div>

                    <!-- Line Total -->
                    <div class="col-span-2">
                        <x-mary-input
                            label="Line Total"
                            value="{{ number_format((float)($item['quantity'] ?? 0) * (float)($item['price'] ?? 0), 2) }}"
                            readonly
                            prefix="₹"
                            class="font-semibold" />
                    </div>

                    <!-- Remove Button -->
                    <div class="col-span-1">
                        @if(count($stockItems) > 1)
                        <x-mary-button
                            icon="o-trash"
                            class="btn-circle btn-ghost btn-sm btn-error"
                            @click="$wire.removeStockItem({{ $index }})" />
                        @endif
                    </div>
                </div>
                @endforeach

                <!-- Total Amount Display -->
                <div class="flex justify-end p-4 bg-primary/10 rounded-lg border-2 border-primary">
                    <div class="text-right">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Total Amount</p>
                        <p class="text-3xl font-bold text-primary">
                            ₹{{ number_format($this->totalAmount, 2) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Actions -->
        <x-slot:actions>
            <x-mary-button label="Cancel" @click="$wire.closeAddStockModal()" />
            <x-mary-button
                label="Add Stock"
                class="btn-primary"
                type="submit"
                spinner="saveStock"
                @click="$wire.saveStock()" />
        </x-slot:actions>
    </x-mary-modal>



    <!-- Product History Modal -->
    <x-mary-modal
        wire:model="showHistoryModal"
        :title="$selectedProduct ? 'History: ' . $selectedProduct->name : 'Product History'"
        :subtitle="$selectedProduct ? $selectedProduct->category->name : ''"
        box-class="backdrop-blur max-w-4xl">

        @if($selectedProduct)
        <div class="space-y-6">
            <!-- Product Summary -->
            <div class="bg-base-100 p-4 rounded-lg border">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-primary">{{ number_format($selectedProduct->stock_quantity, 2) }}</div>
                        <div class="text-sm text-base-content/70">Current Stock</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-warning">{{ number_format($selectedProduct->min_stock_quantity, 2) }}</div>
                        <div class="text-sm text-base-content/70">Min Stock</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold">{{ strtoupper($selectedProduct->unit) }}</div>
                        <div class="text-sm text-base-content/70">Unit</div>
                    </div>
                    <div class="text-center">
                        <x-mary-badge
                            :value="$selectedProduct->stock_status"
                            :class="$selectedProduct->stock_status_color . ' badge-soft'" />
                    </div>
                </div>
            </div>

            <!-- History Tabs -->
            <div class="bg-gray-100 rounded-xl p-1 inline-flex">
                <button
                    wire:click="switchHistoryTab('movements')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ $historyActiveTab === 'movements' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                    Stock Movements
                </button>
                <button
                    wire:click="switchHistoryTab('challans')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ $historyActiveTab === 'challans' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                    Challan History
                </button>
                <button
                    wire:click="switchHistoryTab('adjustments')"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 {{ $historyActiveTab === 'adjustments' ? 'bg-primary text-white shadow-lg' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-200' }}">
                    Adjustments
                </button>
            </div>

            <!-- History Content -->
            <div class="max-h-96 overflow-y-auto">
                @if($historyActiveTab === 'movements')
                <!-- Stock Movements -->
                <div class="space-y-3">
                    @forelse($productHistory as $movement)
                    <div class="flex items-center justify-between p-3 bg-base-100 rounded-lg border">
                        <div class="flex items-center gap-3">
                            @if($movement->type === 'in')
                            <x-mary-icon name="o-arrow-up-circle" class="text-green-500 w-5 h-5" />
                            @else
                            <x-mary-icon name="o-arrow-down-circle" class="text-red-500 w-5 h-5" />
                            @endif
                            <div>
                                <div class="font-medium">
                                    {{ ucfirst($movement->type) }} - {{ ucfirst($movement->reason) }}
                                </div>
                                <div class="text-sm text-base-content/70">
                                    {{ $movement->created_at->format('M j, Y g:i A') }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono font-bold {{ $movement->type === 'in' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $movement->type === 'in' ? '+' : '-' }}{{ number_format($movement->quantity, 2) }} {{ strtoupper($selectedProduct->unit) }}
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-8 text-base-content/50">
                        No stock movements found
                    </div>
                    @endforelse
                </div>
                @elseif($historyActiveTab === 'challans')
                <!-- Challan History -->
                <div class="space-y-3">
                    @forelse($productHistory as $challanItem)
                    <div class="flex items-center justify-between p-3 bg-base-100 rounded-lg border">
                        <div class="flex items-center gap-3">
                            <x-mary-icon name="o-document-text" class="text-blue-500 w-5 h-5" />
                            <div>
                                <div class="font-medium">{{ $challanItem->challan->challan_number }}</div>
                                <div class="text-sm text-base-content/70">
                                    {{ $challanItem->challan->supplier->name ?? 'No Supplier' }} •
                                    {{ $challanItem->challan->challan_date->format('M j, Y') }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono font-bold text-green-600">
                                +{{ number_format($challanItem->quantity, 2) }}
                            </div>
                            @if($challanItem->price)
                            <div class="text-sm text-base-content/70">
                                ₹{{ number_format($challanItem->price, 2) }} each
                            </div>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-8 text-base-content/50">
                        No challan records found
                    </div>
                    @endforelse
                </div>
                @else
                <!-- Adjustments -->
                <div class="space-y-3">
                    @forelse($productHistory as $adjustment)
                    <div class="flex items-center justify-between p-3 bg-base-100 rounded-lg border">
                        <div class="flex items-center gap-3">
                            <x-mary-icon name="o-wrench-screwdriver" class="text-orange-500 w-5 h-5" />
                            <div>
                                <div class="font-medium">{{ ucfirst($adjustment->adjustment_type) }} Adjustment</div>
                                <div class="text-sm text-base-content/70">
                                    {{ $adjustment->created_at->format('M j, Y g:i A') }}
                                </div>
                                @if($adjustment->reason)
                                <div class="text-xs text-base-content/60 mt-1">{{ $adjustment->reason }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono font-bold text-red-600">
                                -{{ number_format($adjustment->quantity, 2) }} {{ strtoupper($selectedProduct->unit) }}
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-8 text-base-content/50">
                        No adjustments found
                    </div>
                    @endforelse
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Modal Actions -->
        <x-slot:actions>
            <x-mary-button label="Close" @click="$wire.closeHistoryModal()" />
        </x-slot:actions>
    </x-mary-modal>

    <!-- Stock Adjustment Modal -->
    <x-mary-modal
        wire:model="showAdjustmentModal"
        :title="$adjustmentProduct ? 'Adjust Stock: ' . $adjustmentProduct->name : 'Stock Adjustment'"
        :subtitle="$adjustmentProduct ? $adjustmentProduct->category->name : ''"
        box-class="backdrop-blur max-w-2xl">

        @if($adjustmentProduct)
        <div class="space-y-6">
            <!-- Product Information -->
            <div class="bg-base-100 p-4 rounded-lg border">
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold text-primary">{{ number_format($adjustmentProduct->stock_quantity, 2) }}</div>
                        <div class="text-sm text-base-content/70">Current Stock</div>
                    </div>
                    <div>
                        <div class="text-lg font-bold">{{ strtoupper($adjustmentProduct->unit) }}</div>
                        <div class="text-sm text-base-content/70">Unit</div>
                    </div>
                    <div>
                        <x-mary-badge
                            :value="$adjustmentProduct->stock_status"
                            :class="$adjustmentProduct->stock_status_color . ' badge-soft'" />
                    </div>
                </div>
            </div>

            <!-- Adjustment Form -->
            <div class="space-y-4">
                <!-- Adjustment Type -->
                <x-mary-select
                    label="Adjustment Type"
                    wire:model="adjustmentType"
                    :options="[
                    ['value' => 'defect', 'label' => 'Defect/Damage'],
                    ['value' => 'expiry', 'label' => 'Expired'],
                    ['value' => 'manual', 'label' => 'Manual Adjustment'],
                ]"
                    option-value="value"
                    option-label="label"
                    placeholder="Select adjustment type"
                    icon="o-exclamation-triangle"
                    :error="$errors->first('adjustmentType')"
                    required />

                <!-- Adjustment Quantity -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-mary-input
                        label="Adjustment Quantity"
                        wire:model="adjustmentQuantity"
                        type="number"
                        step="0.01"
                        placeholder="0.00"
                        icon="o-scale"
                        :error="$errors->first('adjustmentQuantity')"
                        required>
                        <x-slot:append>
                            <span class="bg-base-200 px-3 py-2 text-sm">{{ strtoupper($adjustmentProduct->unit) }}</span>
                        </x-slot:append>
                    </x-mary-input>

                    <!-- Quick Quantity Buttons -->
                    <div class="flex flex-col justify-end">
                        <label class="text-sm font-medium mb-2">Quick Select</label>
                        <div class="flex gap-2">
                            <x-mary-button
                                label="25%"
                                class="btn-xs btn-outline"
                                @click="$wire.adjustmentQuantity = {{ number_format($adjustmentProduct->stock_quantity * 0.25, 2) }}" />
                            <x-mary-button
                                label="50%"
                                class="btn-xs btn-outline"
                                @click="$wire.adjustmentQuantity = {{ number_format($adjustmentProduct->stock_quantity * 0.5, 2) }}" />
                            <x-mary-button
                                label="All"
                                class="btn-xs btn-outline btn-error"
                                @click="$wire.adjustmentQuantity = {{ $adjustmentProduct->stock_quantity }}" />
                        </div>
                    </div>
                </div>

                <!-- Reason -->
                <x-mary-textarea
                    label="Reason (Optional)"
                    wire:model="adjustmentReason"
                    placeholder="Describe the reason for this adjustment..."
                    rows="3"
                    :error="$errors->first('adjustmentReason')" />

                <!-- Warning Message -->
                @if($adjustmentQuantity && $adjustmentQuantity > 0)
                <div class="bg-warning/10 border border-warning/20 rounded-lg p-4">
                    <div class="flex items-center gap-2 text-warning">
                        <x-mary-icon name="o-exclamation-triangle" class="w-5 h-5" />
                        <span class="font-medium">Stock Reduction Warning</span>
                    </div>
                    <div class="mt-2 text-sm text-base-content/80">
                        This will reduce stock from
                        <span class="font-bold">{{ number_format($adjustmentProduct->stock_quantity, 2) }}</span>
                        to
                        <span class="font-bold">{{ number_format($adjustmentProduct->stock_quantity - $adjustmentQuantity, 2) }}</span>
                        {{ strtoupper($adjustmentProduct->unit) }}
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        <!-- Modal Actions -->
        <x-slot:actions>
            <x-mary-button
                label="Cancel"
                @click="$wire.closeAdjustmentModal()" />
            <x-mary-button
                label="Save Adjustment"
                class="btn-warning"
                type="submit"
                spinner="saveAdjustment"
                @click="$wire.saveAdjustment()" />
        </x-slot:actions>
    </x-mary-modal>


    <!-- Import Stock Modal -->
    <x-mary-modal wire:model="showImportModal" title="Import Stock" class="backdrop-blur">
        <div class="space-y-4">
            <x-mary-alert icon="o-information-circle" class="alert-info">
                <strong>CSV Format Required:</strong><br>
                Columns: product_name, category_name, quantity, unit, min_stock_quantity (optional)<br>
                Units: kg, g, box, pcs
            </x-mary-alert>

            <x-mary-file
                wire:model="importFile"
                label="Select CSV File"
                hint="Maximum file size: 2MB. Supported formats: CSV, XLSX, XLS"
                accept=".csv,.xlsx,.xls" />

            @if($importFile)
            <div class="text-sm text-gray-600">
                Selected: {{ $importFile->getClientOriginalName() }}
                ({{ number_format($importFile->getSize() / 1024, 2) }} KB)
            </div>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button
                icon="o-arrow-down-tray"
                label="Download Template"
                class="btn-outline"
                onclick="window.open('/templates/stock-import-template.csv', '_blank')" />
            <x-mary-button label="Cancel" wire:click="closeImportModal" />
            <x-mary-button
                label="Import Stock"
                class="btn-primary"
                wire:click="importStock"
                :disabled="!$importFile"
                spinner="importStock" />
        </x-slot:actions>
    </x-mary-modal>



    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Wait for Livewire to finish loading
            document.addEventListener('livewire:navigated', function() {
                initializeCharts();
            });

            // Also initialize on first load
            initializeCharts();

            function initializeCharts() {
                // Category Stock Chart
                if (document.querySelector("#categoryStockChart")) {
                    const categoryStockOptions = {
                        series: @json($categoryStockData['categoryStock']['series'] ?? []),
                        chart: {
                            type: 'donut',
                            height: 300
                        },
                        labels: @json($categoryStockData['categoryStock']['labels'] ?? []),
                        colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'],
                        legend: {
                            position: 'bottom'
                        },
                        plotOptions: {
                            pie: {
                                donut: {
                                    size: '50%'
                                }
                            }
                        }
                    };
                    new ApexCharts(document.querySelector("#categoryStockChart"), categoryStockOptions).render();
                }

                // Category Distribution Chart
                if (document.querySelector("#categoryDistributionChart")) {
                    const categoryDistributionOptions = {
                        series: @json($categoryStockData['categoryDistribution']['series'] ?? []),
                        chart: {
                            type: 'pie',
                            height: 300
                        },
                        labels: @json($categoryStockData['categoryDistribution']['labels'] ?? []),
                        colors: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4']
                    };
                    new ApexCharts(document.querySelector("#categoryDistributionChart"), categoryDistributionOptions).render();
                }

                // Monthly Movements Chart
                if (document.querySelector("#monthlyMovementsChart")) {
                    const monthlyMovementsOptions = {
                        series: [{
                            name: 'Stock In',
                            data: @json($monthlyMovementsData['stockInData'] ?? [])
                        }, {
                            name: 'Stock Out',
                            data: @json($monthlyMovementsData['stockOutData'] ?? [])
                        }],
                        chart: {
                            type: 'bar',
                            height: 400
                        },
                        xaxis: {
                            categories: @json($monthlyMovementsData['categories'] ?? [])
                        },
                        colors: ['#10b981', '#ef4444']
                    };
                    new ApexCharts(document.querySelector("#monthlyMovementsChart"), monthlyMovementsOptions).render();
                }
            }
        });
    </script>
</div>