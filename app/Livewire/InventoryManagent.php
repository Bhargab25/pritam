<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\Challan;
use App\Models\ChallanItem;
use App\Models\StockMovement;
use App\Models\StockAdjustment;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StockReportExport;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryManagent extends Component
{
    use WithPagination, WithFileUploads, Toast;

    public $lowStockCount = 0;
    public $outOfStockCount = 0;
    public $totalItems = 0;
    public $totalValue = 0;
    public $activeTab = 'inventory';

    // Search and filters
    public $search = '';
    public $categoryFilter = '';
    public $statusFilter = '';
    public $perPage = 10;
    public $sortBy = ['column' => 'name', 'direction' => 'asc'];

    // Selection
    public $selected = [];

    // Modal properties
    public $showAddStockModal = false;
    public $challanNumber = '';
    public $challanDate = '';
    public $supplierId = '';
    public $remarks = '';

    // Dynamic rows for products
    public $stockItems = [];

    // Product History Modal properties
    public $showHistoryModal = false;
    public $selectedProduct = null;
    public $productHistory = [];
    public $historyActiveTab = 'movements';

    // Stock Adjustment Modal properties
    public $showAdjustmentModal = false;
    public $adjustmentProduct = null;
    public $adjustmentType = '';
    public $adjustmentQuantity = '';
    public $adjustmentReason = '';

    public $importFile;
    public $showImportModal = false;

    // Report filters
    public $reportDateFrom;
    public $reportDateTo;
    public $reportType = 'overview';

    protected $rules = [
        'challanNumber' => 'required|string|unique:challans,challan_number',
        'challanDate' => 'required|date',
        'supplierId' => 'nullable|exists:suppliers,id',
        'remarks' => 'nullable|string|max:500',
        'stockItems.*.product_id' => 'required|exists:products,id',
        'stockItems.*.quantity' => 'required|numeric|min:0.01',
        'stockItems.*.price' => 'nullable|numeric|min:0',
    ];

    protected $messages = [
        'stockItems.*.product_id.required' => 'Please select a product',
        'stockItems.*.quantity.required' => 'Quantity is required',
        'stockItems.*.quantity.min' => 'Quantity must be greater than 0',
        'adjustmentType.required' => 'Please select an adjustment type',
        'adjustmentQuantity.required' => 'Adjustment quantity is required',
        'adjustmentQuantity.min' => 'Adjustment quantity must be greater than 0',
    ];

    public function mount()
    {
        $this->calculateStats();
        $this->challanDate = now()->format('Y-m-d');
        $this->reportDateFrom = now()->subDays(30)->format('Y-m-d');
        $this->reportDateTo = now()->format('Y-m-d');
    }

    public function calculateStats()
    {
        $products = Product::with('category')->get();

        $this->totalItems = $products->count();
        $this->lowStockCount = $products->filter(fn($product) => $product->isLowStock())->count();
        $this->outOfStockCount = $products->filter(fn($product) => $product->stock_quantity == 0)->count();
        // You can add price field to products table and calculate total value
        // $this->totalValue = $products->sum(fn($product) => $product->stock_quantity * $product->unit_price);
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    // Modal Methods
    public function openAddStockModal()
    {
        $this->showAddStockModal = true;
        $this->challanNumber = 'CH-' . now()->format('YmdHis');
        $this->challanDate = now()->format('Y-m-d');
        $this->supplierId = '';
        $this->remarks = '';
        $this->stockItems = [
            ['product_id' => '', 'quantity' => '', 'price' => '']
        ];
    }

    public function closeAddStockModal()
    {
        $this->showAddStockModal = false;
        $this->resetValidation();
        $this->reset(['challanNumber', 'challanDate', 'supplierId', 'remarks', 'stockItems']);
    }

    public function addStockItem()
    {
        $this->stockItems[] = ['product_id' => '', 'quantity' => '', 'price' => ''];
    }

    public function removeStockItem($index)
    {
        if (count($this->stockItems) > 1) {
            unset($this->stockItems[$index]);
            $this->stockItems = array_values($this->stockItems);
        }
    }

    public function saveStock()
    {
        $this->validate();

        try {
            DB::transaction(function () {
                // Create Challan
                $challan = Challan::create([
                    'challan_number' => $this->challanNumber,
                    'challan_date' => $this->challanDate,
                    'supplier_id' => $this->supplierId ?: null,
                    'remarks' => $this->remarks,
                ]);

                // Create Challan Items and Stock Movements
                foreach ($this->stockItems as $item) {
                    $challanItem = ChallanItem::create([
                        'challan_id' => $challan->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'] ?: null,
                    ]);

                    // Update product stock
                    $product = Product::find($item['product_id']);
                    $product->stock_quantity += $item['quantity'];
                    $product->save();

                    // Create stock movement record
                    StockMovement::create([
                        'product_id' => $item['product_id'],
                        'type' => 'in',
                        'quantity' => $item['quantity'],
                        'reason' => 'purchase',
                        'reference_type' => ChallanItem::class,
                        'reference_id' => $challanItem->id,
                    ]);
                }
            });

            $this->dispatch('success', message: 'Stock added successfully!');
            $this->closeAddStockModal();
            $this->calculateStats();
        } catch (\Exception $e) {
            Log::error('Error adding stock: ' . $e->getMessage());
            $this->dispatch('error', message: 'Error adding stock: ' . $e->getMessage());
        }
    }

    public function openAdjustmentModal($productId)
    {
        $this->adjustmentProduct = Product::with('category')->find($productId);
        $this->showAdjustmentModal = true;
        $this->adjustmentType = '';
        $this->adjustmentQuantity = '';
        $this->adjustmentReason = '';
        $this->resetValidation();
    }

    public function closeAdjustmentModal()
    {
        $this->showAdjustmentModal = false;
        $this->adjustmentProduct = null;
        $this->adjustmentType = '';
        $this->adjustmentQuantity = '';
        $this->adjustmentReason = '';
        $this->resetValidation();
    }

    public function saveAdjustment()
    {
        $this->validate([
            'adjustmentType' => 'required|in:defect,expiry,manual',
            'adjustmentQuantity' => 'required|numeric|min:0.01',
            'adjustmentReason' => 'nullable|string|max:500',
        ]);

        // Check if adjustment quantity is not more than available stock
        if ($this->adjustmentQuantity > $this->adjustmentProduct->stock_quantity) {
            $this->dispatch('error', message: 'Adjustment quantity cannot exceed current stock!');
            return;
        }

        try {
            DB::transaction(function () {
                // Create Stock Adjustment
                $adjustment = StockAdjustment::create([
                    'product_id' => $this->adjustmentProduct->id,
                    'adjustment_type' => $this->adjustmentType,
                    'quantity' => $this->adjustmentQuantity,
                    'reason' => $this->adjustmentReason,
                ]);

                // Update product stock (reduce)
                $this->adjustmentProduct->stock_quantity -= $this->adjustmentQuantity;
                $this->adjustmentProduct->save();

                // Create stock movement record
                StockMovement::create([
                    'product_id' => $this->adjustmentProduct->id,
                    'type' => 'out',
                    'quantity' => $this->adjustmentQuantity,
                    'reason' => $this->adjustmentType,
                    'reference_type' => StockAdjustment::class,
                    'reference_id' => $adjustment->id,
                ]);
            });

            $this->dispatch('success', message: 'Stock adjustment saved successfully!');
            $this->closeAdjustmentModal();
            $this->calculateStats();
        } catch (\Exception $e) {
            Log::error('Error saving adjustment: ' . $e->getMessage());
            $this->dispatch('error', message: 'Error saving adjustment: ' . $e->getMessage());
        }
    }

    // Add this method for the import button
    public function openImportModal()
    {
        $this->showImportModal = true;
    }

    public function closeImportModal()
    {
        $this->showImportModal = false;
        $this->importFile = null;
    }

    public function importStock()
    {
        $this->validate([
            'importFile' => 'required|mimes:csv,xlsx,xls|max:2048',
        ]);

        // Store the file temporarily
        $filePath = $this->importFile->store('stock-imports', 'local');

        // Dispatch background job
        \App\Jobs\ImportStockJob::dispatch($filePath, Auth::id());

        // Show immediate feedback
        $this->success(
            'Stock Import Started!',
            'Your stock import is being processed. You will receive a notification when complete.'
        );

        $this->closeImportModal();
    }

    public function showProductHistory($productId)
    {
        $this->selectedProduct = Product::with('category')->find($productId);
        $this->loadProductHistory();
        $this->showHistoryModal = true;
    }

    public function closeHistoryModal()
    {
        $this->showHistoryModal = false;
        $this->selectedProduct = null;
        $this->productHistory = [];
    }

    public function switchHistoryTab($tab)
    {
        $this->historyActiveTab = $tab;
        $this->loadProductHistory();
    }

    private function loadProductHistory()
    {
        if (!$this->selectedProduct) return;

        switch ($this->historyActiveTab) {
            case 'movements':
                $this->productHistory = StockMovement::where('product_id', $this->selectedProduct->id)
                    ->with(['reference'])
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get();
                break;

            case 'challans':
                $this->productHistory = ChallanItem::where('product_id', $this->selectedProduct->id)
                    ->with(['challan.supplier'])
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get();
                break;

            case 'adjustments':
                $this->productHistory = StockAdjustment::where('product_id', $this->selectedProduct->id)
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get();
                break;
        }
    }

    public function getReportData()
    {
        return [
            'categoryStock' => $this->getCategoryStockData(),
            'topProducts' => $this->getTopProducts(),
            'lowStockProducts' => $this->getLowStockProducts(),
            'stockMovements' => $this->getStockMovements(),
            'categoryDistribution' => $this->getCategoryDistribution(),
            'monthlyMovements' => $this->getMonthlyMovements(),
        ];
    }

    private function getCategoryStockData()
    {
        return ProductCategory::with('products')
            ->where('is_active', true)
            ->get()
            ->map(function ($category) {
                return [
                    'name' => $category->name,
                    'total_stock' => $category->products->sum('stock_quantity'),
                    'total_products' => $category->products->count(),
                    'low_stock_count' => $category->products->filter(fn($p) => $p->stock_quantity <= $p->min_stock_quantity)->count(),
                    'out_of_stock_count' => $category->products->filter(fn($p) => $p->stock_quantity == 0)->count(), // ✅ Add this
                ];
            });
    }

    private function getTopProducts()
    {
        return Product::with('category')
            ->where('is_active', true)
            ->orderBy('stock_quantity', 'desc')
            ->limit(10)
            ->get();
    }

    private function getLowStockProducts()
    {
        return Product::with('category')
            ->where('is_active', true)
            ->whereRaw('stock_quantity <= min_stock_quantity')
            ->orderBy('stock_quantity', 'asc')
            ->limit(10)
            ->get();
    }

    private function getStockMovements()
    {
        return StockMovement::with('product')
            ->whereBetween('created_at', [$this->reportDateFrom, $this->reportDateTo])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    private function getCategoryDistribution()
    {
        return ProductCategory::with('products')
            ->where('is_active', true)
            ->get()
            ->map(function ($category) {
                return [
                    'name' => $category->name,
                    'count' => $category->products->count(),
                ];
            });
    }

    private function getMonthlyMovements()
    {
        return StockMovement::selectRaw('
                MONTH(created_at) as month,
                YEAR(created_at) as year,
                type,
                SUM(quantity) as total_quantity
            ')
            ->whereBetween('created_at', [$this->reportDateFrom, $this->reportDateTo])
            ->groupBy('year', 'month', 'type')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();
    }

    public function downloadStockReport()
    {
        $reportData = $this->getReportData();
        $filters = [
            'date_from' => $this->reportDateFrom,
            'date_to' => $this->reportDateTo,
            'category_filter' => $this->categoryFilter,
            'status_filter' => $this->statusFilter,
        ];

        // Dispatch the job
        \App\Jobs\GenerateStockReportJob::dispatch(
            auth()->id(),
            $reportData,
            'stock',
            $filters
        );

        $this->success(
            'Stock Report Generation Started!',
            'Your stock report is being generated. You will receive a notification when it\'s ready for download.'
        );
    }

    public function downloadCategoryReport()
    {
        $categoryData = $this->getCategoryStockData();
        $filters = [
            'generated_at' => now()->toDateTimeString(),
        ];

        // Convert collection to array for CategoryReportExport
        $categoryDataArray = $categoryData->toArray();

        // Dispatch the job
        \App\Jobs\GenerateStockReportJob::dispatch(
            auth()->id(),
            $categoryDataArray, // ✅ Pass as array
            'category',
            $filters
        );

        $this->success(
            'Category Report Generation Started!',
            'Your category report is being generated. You will receive a notification when it\'s ready for download.'
        );
    }

    public function getChartData($reportData)
    {
        return [
            'categoryStock' => [
                'series' => $reportData['categoryStock']->pluck('total_stock')->toArray(),
                'labels' => $reportData['categoryStock']->pluck('name')->toArray()
            ],
            'categoryDistribution' => [
                'series' => $reportData['categoryDistribution']->pluck('count')->toArray(),
                'labels' => $reportData['categoryDistribution']->pluck('name')->toArray()
            ]
        ];
    }
    public function getMonthlyMovementsData($reportData)
    {
        $movements = $reportData['monthlyMovements'] ?? collect();

        // Group movements by month and separate by type
        $stockInData = [];
        $stockOutData = [];
        $categories = [];

        // Get last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthYear = $date->format('M Y');
            $categories[] = $monthYear;

            // Find movements for this month
            $monthMovements = $movements->filter(function ($movement) use ($date) {
                return $movement->month == $date->month && $movement->year == $date->year;
            });

            $stockIn = $monthMovements->where('type', 'in')->sum('total_quantity');
            $stockOut = $monthMovements->where('type', 'out')->sum('total_quantity');

            $stockInData[] = $stockIn;
            $stockOutData[] = $stockOut;
        }

        return [
            'stockInData' => $stockInData,
            'stockOutData' => $stockOutData,
            'categories' => $categories
        ];
    }

    public function render()
    {
        $reportData = $this->getReportData();
        $chartData = $this->getChartData($reportData);
        $monthlyMovementsData = $this->getMonthlyMovementsData($reportData);

        $products = Product::with('category')
            // Search functionality
            ->when($this->search, function ($query) {
                return $query->where('name', 'like', '%' . $this->search . '%');
            })
            // Category filter
            ->when($this->categoryFilter, function ($query) {
                return $query->where('category_id', $this->categoryFilter);
            })
            // Status filter
            ->when($this->statusFilter === 'low_stock', function ($query) {
                return $query->whereRaw('stock_quantity <= min_stock_quantity');
            })
            ->when($this->statusFilter === 'out_of_stock', function ($query) {
                return $query->where('stock_quantity', 0);
            })
            ->when($this->statusFilter === 'in_stock', function ($query) {
                return $query->where('stock_quantity', '>', 0);
            })
            ->where('is_active', true)
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate($this->perPage);

        $categories = ProductCategory::where('is_active', true)->get();
        $suppliers = Supplier::where('is_active', true)->get();
        $allProducts = Product::where('is_active', true)->get();

        $headers = [
            ['label' => 'Sl No.', 'key' => 'sl_no', 'sortable' => false],
            ['label' => 'Product Name', 'key' => 'name', 'sortable' => true],
            ['label' => 'Category', 'key' => 'category', 'sortable' => false],
            ['label' => 'Current Stock', 'key' => 'stock_quantity', 'sortable' => true],
            ['label' => 'Min Stock', 'key' => 'min_stock_quantity', 'sortable' => true],
            ['label' => 'Unit', 'key' => 'unit', 'sortable' => false],
            ['label' => 'Status', 'key' => 'status', 'sortable' => false],
            ['label' => 'Actions', 'key' => 'actions', 'sortable' => false],
        ];

        $row_decoration = [
            'bg-error/10' => fn($product) => $product->stock_quantity == 0,
            'bg-warning/10' => fn($product) => $product->isLowStock() && $product->stock_quantity > 0,
        ];

        return view('livewire.inventory-managent', [
            'products' => $products,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'allProducts' => $allProducts,
            'headers' => $headers,
            'row_decoration' => $row_decoration,
            'reportData' => $reportData,
            'categoryStockData' => $chartData,
            'monthlyMovementsData' => $monthlyMovementsData,
        ]);
    }
}
