<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Product as Products;
use App\Models\ProductCategory;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Product extends Component
{
    use WithPagination;
    use Toast;

    public $myModal = false;
    public $showDrawer = false;
    public $name;
    public $category_id;
    public $unit;
    public $stock_quantity = 0;
    public $min_stock_quantity = 0;
    public $is_active = true;
    public $isEdit = false;
    public $search = '';
    public $statusFilter = [];
    public $appliedStatusFilter = [];
    public $categoryFilter = [];
    public $appliedCategoryFilter = [];

    public $productId;
    public $sortBy = ['column' => 'name', 'direction' => 'asc'];
    public $sortDirection = 'asc';
    public $perPage = 10;
    public $selected = [];
    public $filter = 'all'; // 'all', 'active', 'inactive'

    public $showDeleteModal = false;
    public $productToDelete = null;
    public $deleteError = '';

    // Options for filters
    public $statusOptions = [
        ['id' => 'active', 'name' => 'Active'],
        ['id' => 'inactive', 'name' => 'Inactive'],
    ];

    public $categoryOptions = [];

    protected $listeners = ['refreshProducts' => '$refresh'];

    public function mount()
    {
        // Load categories for filter options
        $this->categoryOptions = ProductCategory::where('is_active', true)
            ->get()
            ->map(function ($category) {
                return ['id' => $category->id, 'name' => $category->name];
            })
            ->toArray();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilter()
    {
        $this->resetPage();
    }

    public function updateSort($column, $direction = 'asc')
    {
        $this->sortBy = ['column' => $column, 'direction' => $direction];
        $this->resetPage();
    }

    public function resetSort()
    {
        $this->sortBy = ['column' => 'name', 'direction' => 'asc'];
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->reset(['statusFilter', 'appliedStatusFilter', 'categoryFilter', 'appliedCategoryFilter']);
        $this->resetPage();
    }

    public function applyFilters()
    {
        $this->appliedStatusFilter = $this->statusFilter;
        $this->appliedCategoryFilter = $this->categoryFilter;
        $this->showDrawer = false;
        $this->resetPage();
        $this->success('Filters Applied!', 'Products filtered successfully.');
    }

    public function newProduct()
    {
        $this->reset(['productId', 'name', 'category_id', 'unit', 'stock_quantity', 'is_active']);
        $this->isEdit = false;
        $this->myModal = true;
    }

    public function editProduct($id)
    {
        $product = Products::find($id);
        if ($product) {
            $this->productId = $product->id;
            $this->name = $product->name;
            $this->category_id = $product->category_id;
            $this->unit = $product->unit;
            $this->stock_quantity = $product->stock_quantity;
            $this->min_stock_quantity = $product->min_stock_quantity;
            $this->is_active = $product->is_active;
            $this->isEdit = true;
            $this->myModal = true;
        }
    }

    public function saveProduct()
    {
        $this->validate([
            'name' => 'required|unique:products,name,' . $this->productId,
            'category_id' => 'required|exists:product_categories,id',
            'unit' => 'required|string|max:50',
            'stock_quantity' => 'required|numeric|min:0',
            'min_stock_quantity' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($this->isEdit && $this->productId) {
            // Update
            $product = Products::find($this->productId);
            if ($product) {
                $product->update([
                    'name' => $this->name,
                    'category_id' => $this->category_id,
                    'unit' => $this->unit,
                    'stock_quantity' => $this->stock_quantity,
                    'min_stock_quantity' => $this->min_stock_quantity,
                    'is_active' => $this->is_active,
                ]);
                $this->success('Product Updated!', 'The product has been updated successfully.');
            }
        } else {
            // Create
            Products::create([
                'name' => $this->name,
                'category_id' => $this->category_id,
                'unit' => $this->unit,
                'stock_quantity' => $this->stock_quantity,
                'min_stock_quantity' => $this->min_stock_quantity,
                'is_active' => $this->is_active,
            ]);
            $this->success('Product Created!', 'The product has been added successfully.');
        }

        $this->reset(['name', 'category_id', 'unit', 'stock_quantity', 'min_stock_quantity', 'is_active', 'productId']);
        $this->myModal = false;
        $this->isEdit = false;
        $this->dispatch('refreshProducts');
    }

    public function confirmDelete($id)
    {
        $this->productToDelete = Products::find($id);
        $this->deleteError = '';

        if ($this->productToDelete) {
            // Check if product is used in other tables
            $canDelete = $this->checkProductCanBeDeleted($id);

            if (!$canDelete['can_delete']) {
                $this->deleteError = $canDelete['message'];
            }

            $this->showDeleteModal = true;
        }
    }

    public function deleteProduct()
    {
        if (!$this->productToDelete) {
            $this->error('Product not found.');
            return;
        }

        try {
            // Double-check constraints before deletion
            $canDelete = $this->checkProductCanBeDeleted($this->productToDelete->id);

            if (!$canDelete['can_delete']) {
                $this->error($canDelete['message']);
                $this->closeDeleteModal();
                return;
            }

            // Perform the deletion
            $productName = $this->productToDelete->name;
            $this->productToDelete->delete();

            $this->success('Product Deleted!', "'{$productName}' has been removed successfully.");
            $this->closeDeleteModal();
            $this->dispatch('refreshProducts');
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle foreign key constraint violation
            if ($e->getCode() == '23000') {
                $this->error('Cannot delete this product!', 'This product is being used in invoices, stock movements, or other records.');
            } else {
                $this->error('Error occurred while deleting the product.');
            }
            $this->closeDeleteModal();
        } catch (\Exception $e) {
            $this->error('An unexpected error occurred.');
            $this->closeDeleteModal();
        }
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->productToDelete = null;
        $this->deleteError = '';
    }

    private function checkProductCanBeDeleted($productId)
    {
        $constraints = [];
        $canDelete = true;

        // Check invoice items
        $invoiceItemsCount = DB::table('invoice_items')
            ->where('product_id', $productId)
            ->count();

        if ($invoiceItemsCount > 0) {
            $constraints[] = "Used in {$invoiceItemsCount} invoice item(s)";
            $canDelete = false;
        }

        // Check challan items (if exists)
        if (Schema::hasTable('challan_items')) {
            $challanItemsCount = DB::table('challan_items')
                ->where('product_id', $productId)
                ->count();

            if ($challanItemsCount > 0) {
                $constraints[] = "Used in {$challanItemsCount} challan item(s)";
                $canDelete = false;
            }
        }

        // Check stock movements
        $stockMovementsCount = DB::table('stock_movements')
            ->where('product_id', $productId)
            ->count();

        if ($stockMovementsCount > 0) {
            $constraints[] = "Has {$stockMovementsCount} stock movement record(s)";
            $canDelete = false;
        }

        // Check stock adjustments
        if (Schema::hasTable('stock_adjustments')) {
            $stockAdjustmentsCount = DB::table('stock_adjustments')
                ->where('product_id', $productId)
                ->count();

            if ($stockAdjustmentsCount > 0) {
                $constraints[] = "Has {$stockAdjustmentsCount} stock adjustment record(s)";
                $canDelete = false;
            }
        }

        $message = '';
        if (!$canDelete) {
            $message = "Cannot delete this product because it is:\n• " . implode("\n• ", $constraints);
        }

        return [
            'can_delete' => $canDelete,
            'message' => $message,
            'constraints' => $constraints
        ];
    }

    public function toggleStatus()
    {
        foreach ($this->selected as $id) {
            $this->toggleActive($id);
        }
        $this->success('Products Updated!', count($this->selected) . ' products status toggled.');
        $this->dispatch('refreshProducts');
        $this->reset('selected');
    }

    public function toggleActive($id)
    {
        $product = Products::find($id);
        if ($product) {
            $product->is_active = !$product->is_active;
            $product->save();
        }
    }

    public function cancel()
    {
        $this->reset(['name', 'category_id', 'unit', 'stock_quantity', 'is_active']);
        $this->isEdit = false;
        $this->myModal = false;
    }

    public function clearSearch()
    {
        $this->reset('search');
    }

    public function render()
    {
        $products = Products::query()
            ->with('category')
            // Search functionality
            ->when($this->search, function ($query) {
                return $query->where(function ($subQuery) {
                    $subQuery->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('unit', 'like', '%' . $this->search . '%')
                        ->orWhereHas('category', function ($categoryQuery) {
                            $categoryQuery->where('name', 'like', '%' . $this->search . '%');
                        });
                });
            })
            // Status filter
            ->when(!empty($this->appliedStatusFilter), function ($query) {
                if (in_array('active', $this->appliedStatusFilter) && !in_array('inactive', $this->appliedStatusFilter)) {
                    return $query->where('is_active', true);
                }
                if (in_array('inactive', $this->appliedStatusFilter) && !in_array('active', $this->appliedStatusFilter)) {
                    return $query->where('is_active', false);
                }
                return $query;
            })
            // Category filter
            ->when(!empty($this->appliedCategoryFilter), function ($query) {
                return $query->whereIn('category_id', $this->appliedCategoryFilter);
            })
            // Dropdown filter (fallback)
            ->when(empty($this->appliedStatusFilter) && $this->filter === 'active', function ($query) {
                return $query->where('is_active', true);
            })
            ->when(empty($this->appliedStatusFilter) && $this->filter === 'inactive', function ($query) {
                return $query->where('is_active', false);
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->paginate($this->perPage);

        $headers = [
            ['label' => 'ID', 'key' => 'id', 'sortable' => false],
            ['label' => 'Name', 'key' => 'name', 'sortable' => true],
            ['label' => 'Category', 'key' => 'category.name', 'sortable' => true],
            ['label' => 'Stock', 'key' => 'stock_quantity', 'sortable' => true],
            ['label' => 'Unit', 'key' => 'unit', 'sortable' => false],
            ['label' => 'Min Stock', 'key' => 'min_stock_quantity', 'sortable' => true],
            ['label' => 'Status', 'key' => 'is_active', 'sortable' => false],
            ['label' => 'Created At', 'key' => 'created_at', 'sortable' => true, 'format' => ['date', 'd/m/Y']],
            ['label' => 'Actions', 'key' => 'actions', 'type' => 'button', 'sortable' => false],
        ];

        $row_decoration = [
            'bg-warning/20' => fn($product) => !$product->is_active,
            'text-error' => fn($product) => $product->is_active === 0,
            'bg-red-50' => fn($product) => $product->stock_quantity === 0, // Out of stock
        ];

        return view('livewire.product', [
            'products' => $products,
            'headers' => $headers,
            'row_decoration' => $row_decoration,
            'categories' => ProductCategory::where('is_active', true)->get(),
        ]);
    }
}
