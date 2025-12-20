<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\AccountLedger;
use App\Models\LedgerTransaction;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Title('Outstanding Management')]
class OutstandingManagement extends Component
{
    use WithPagination, Toast;

    // Filters
    public $search = '';
    public $entityType = 'all';
    public $outstandingFilter = 'all';
    public $sortBy = ['column' => 'outstanding', 'direction' => 'desc'];
    public $perPage = 15;

    // View toggle
    public $viewMode = 'summary';

    // Modal states
    public $showDetailsModal = false;
    public $selectedEntity = null;
    public $selectedEntityType = null;

    // Statistics
    public $totalReceivables = 0;
    public $totalPayables = 0;
    public $netPosition = 0;
    public $overdueReceivables = 0;
    public $overduePayables = 0;

    // Outstanding ranges for filtering
    public $outstandingRanges = [
        'all' => ['label' => 'All Outstanding', 'min' => 0, 'max' => null],
        'high' => ['label' => 'High (>50k)', 'min' => 50000, 'max' => null],
        'medium' => ['label' => 'Medium (10k-50k)', 'min' => 10000, 'max' => 50000],
        'low' => ['label' => 'Low (<10k)', 'min' => 0, 'max' => 10000],
    ];

    public function mount()
    {
        $this->calculateStatistics();
    }

    public function calculateStatistics()
    {
        // Total Receivables (what clients owe us)
        $clientLedgers = AccountLedger::where('ledger_type', 'client')
            ->where('is_active', true)
            ->get();

        $this->totalReceivables = $clientLedgers->where('current_balance', '>', 0)
            ->sum('current_balance');

        // Total Payables (what we owe suppliers)
        $supplierLedgers = AccountLedger::where('ledger_type', 'supplier')
            ->where('is_active', true)
            ->get();

        $this->totalPayables = $supplierLedgers->where('current_balance', '>', 0)
            ->sum('current_balance');

        // Net Position
        $this->netPosition = $this->totalReceivables - $this->totalPayables;

        // ✅ Overdue Receivables - exclude deleted invoices
        $this->overdueReceivables = Invoice::withoutTrashed()
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('due_date', '<', now())
            ->where('balance_amount', '>', 0)
            ->sum('balance_amount');

        $this->overduePayables = 0;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedEntityType()
    {
        $this->resetPage();
    }

    public function updatedOutstandingFilter()
    {
        $this->resetPage();
    }

    public function updateSort($column)
    {
        if ($this->sortBy['column'] === $column) {
            $this->sortBy['direction'] = $this->sortBy['direction'] === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = ['column' => $column, 'direction' => 'desc'];
        }
        $this->resetPage();
    }

    public function showEntityDetails($entityId, $entityType)
    {
        if ($entityType === 'client') {
            $this->selectedEntity = Client::with(['ledger.transactions'])->find($entityId);
        } else {
            $this->selectedEntity = Supplier::with(['ledger.transactions'])->find($entityId);
        }

        $this->selectedEntityType = $entityType;
        $this->showDetailsModal = true;
    }

    public function getOutstandingDataProperty()
    {
        $data = collect();

        // Get Clients Outstanding (Receivables)
        if (in_array($this->entityType, ['all', 'client'])) {
            $clients = Client::with('ledger')
                ->whereHas('ledger', function ($query) {
                    $query->where('is_active', true)
                        ->where('current_balance', '>', 0);
                })
                ->get()
                ->map(function ($client) {
                    // ✅ Use withoutTrashed() for overdue invoices
                    $overdueInvoices = Invoice::withoutTrashed()
                        ->where('client_id', $client->id)
                        ->whereIn('payment_status', ['unpaid', 'partial'])
                        ->where('due_date', '<', now())
                        ->where('is_cancelled', false)
                        ->where('balance_amount', '>', 0)
                        ->get();
                    

                    // ✅ Use withoutTrashed() for unpaid invoices
                    $unpaidInvoices = Invoice::withoutTrashed()
                        ->where('client_id', $client->id)
                        ->whereIn('payment_status', ['unpaid', 'partial'])
                        ->where('is_cancelled', false)
                        ->where('balance_amount', '>', 0)
                        ->get();

                    return [
                        'id' => $client->id,
                        'entity_type' => 'client',
                        'name' => $client->name,
                        'phone' => $client->phone,
                        'city' => $client->city,
                        'outstanding' => $client->ledger->current_balance,
                        'ledger_balance' => $client->ledger->current_balance,
                        'overdue_amount' => $overdueInvoices->sum('balance_amount'),
                        'overdue_count' => $overdueInvoices->count(),
                        'total_invoices' => $unpaidInvoices->count(),
                        'oldest_invoice_date' => $unpaidInvoices->min('invoice_date'),
                        'days_outstanding' => $unpaidInvoices->min('invoice_date')
                            ? now()->diffInDays($unpaidInvoices->min('invoice_date'))
                            : 0,
                    ];
                });

            $data = $data->merge($clients);
        }

        // Get Suppliers Outstanding (Payables)
        if (in_array($this->entityType, ['all', 'supplier'])) {
            $suppliers = Supplier::with('ledger')
                ->whereHas('ledger', function ($query) {
                    $query->where('is_active', true)
                        ->where('current_balance', '>', 0);
                })
                ->get()
                ->map(function ($supplier) {
                    return [
                        'id' => $supplier->id,
                        'entity_type' => 'supplier',
                        'name' => $supplier->name,
                        'phone' => $supplier->phone,
                        'city' => $supplier->city,
                        'outstanding' => $supplier->ledger->current_balance,
                        'ledger_balance' => $supplier->ledger->current_balance,
                        'overdue_amount' => 0,
                        'overdue_count' => 0,
                        'total_invoices' => 0,
                        'oldest_invoice_date' => null,
                        'days_outstanding' => 0,
                    ];
                });

            $data = $data->merge($suppliers);
        }

        // Apply search filter
        if ($this->search) {
            $data = $data->filter(function ($item) {
                return stripos($item['name'], $this->search) !== false ||
                    stripos($item['phone'], $this->search) !== false ||
                    stripos($item['city'], $this->search) !== false;
            });
        }

        // Apply outstanding range filter
        if ($this->outstandingFilter !== 'all') {
            $range = $this->outstandingRanges[$this->outstandingFilter];
            $data = $data->filter(function ($item) use ($range) {
                $outstanding = $item['outstanding'];
                $matchesMin = $outstanding >= $range['min'];
                $matchesMax = $range['max'] === null || $outstanding <= $range['max'];
                return $matchesMin && $matchesMax;
            });
        }

        // Apply sorting
        $column = $this->sortBy['column'];
        $direction = $this->sortBy['direction'];

        $data = $data->sortBy(function ($item) use ($column) {
            return $item[$column] ?? 0;
        }, SORT_REGULAR, $direction === 'desc');

        return $data->values();
    }

    public function exportOutstanding()
    {
        $this->info('Export feature coming soon!');
    }

    public function render()
    {
        $outstandingData = $this->outstandingData;

        // Manual pagination
        $currentPage = $this->getPage();
        $items = $outstandingData->slice(($currentPage - 1) * $this->perPage, $this->perPage);

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $outstandingData->count(),
            $this->perPage,
            $currentPage,
            ['path' => request()->url()]
        );

        return view('livewire.outstanding-management', [
            'outstandingData' => $paginator,
            'totalEntries' => $outstandingData->count(),
        ]);
    }
}
