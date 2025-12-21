<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Expense;
use App\Models\Product;
use App\Models\AccountLedger;
use App\Models\CompanyBankAccount;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Arr;


class Dashboard extends Component
{
    public $period = 'this_month';
    public $dateFrom;
    public $dateTo;

    public array $salesExpensesChart = [
        'type' => 'line',
        'data' => [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => [],
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Expenses',
                    'data' => [],
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4,
                ]
            ]
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                ]
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true
                ]
            ]
        ]
    ];

    public array $monthlyComparisonChart = [
        'type' => 'bar',
        'data' => [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => [],
                    'backgroundColor' => 'rgba(59, 130, 246, 0.8)',
                ],
                [
                    'label' => 'Expenses',
                    'data' => [],
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                ],
                [
                    'label' => 'Profit',
                    'data' => [],
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                ]
            ]
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                ]
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true
                ]
            ]
        ]
    ];

    // Key Metrics
    public $totalSales = 0;
    public $totalExpenses = 0;
    public $netProfit = 0;
    public $cashBalance = 0;
    public $bankBalance = 0;
    public $totalOutstanding = 0;
    public $totalPayable = 0;

    // Statistics
    public $totalInvoices = 0;
    public $paidInvoices = 0;
    public $unpaidInvoices = 0;
    public $totalClients = 0;
    public $totalSuppliers = 0;
    public $totalProducts = 0;
    public $lowStockProducts = 0;

    // Recent Data
    public $recentInvoices = [];
    public $recentExpenses = [];
    public $recentPayments = [];
    public $topClients = [];
    public $lowStockItems = [];

    // Chart Data
    public $salesChartData = [];
    public $expenseChartData = [];
    public $monthlyComparisonData = [];

    public function mount()
    {
        $this->setPeriod('this_month');
    }

    public function setPeriod($period)
    {
        $this->period = $period;

        switch ($period) {
            case 'today':
                $this->dateFrom = now()->startOfDay()->format('Y-m-d');
                $this->dateTo = now()->endOfDay()->format('Y-m-d');
                break;
            case 'yesterday':
                $this->dateFrom = now()->subDay()->startOfDay()->format('Y-m-d');
                $this->dateTo = now()->subDay()->endOfDay()->format('Y-m-d');
                break;
            case 'this_week':
                $this->dateFrom = now()->startOfWeek()->format('Y-m-d');
                $this->dateTo = now()->endOfWeek()->format('Y-m-d');
                break;
            case 'last_week':
                $this->dateFrom = now()->subWeek()->startOfWeek()->format('Y-m-d');
                $this->dateTo = now()->subWeek()->endOfWeek()->format('Y-m-d');
                break;
            case 'this_month':
                $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
                $this->dateTo = now()->endOfMonth()->format('Y-m-d');
                break;
            case 'last_month':
                $this->dateFrom = now()->subMonth()->startOfMonth()->format('Y-m-d');
                $this->dateTo = now()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'this_quarter':
                $this->dateFrom = now()->firstOfQuarter()->format('Y-m-d');
                $this->dateTo = now()->lastOfQuarter()->format('Y-m-d');
                break;
            case 'this_year':
                $this->dateFrom = now()->startOfYear()->format('Y-m-d');
                $this->dateTo = now()->endOfYear()->format('Y-m-d');
                break;
            case 'last_year':
                $this->dateFrom = now()->subYear()->startOfYear()->format('Y-m-d');
                $this->dateTo = now()->subYear()->endOfYear()->format('Y-m-d');
                break;
        }

        $this->calculateMetrics();
    }

    public function updatedDateFrom()
    {
        $this->period = 'custom';
        $this->calculateMetrics();
    }

    public function updatedDateTo()
    {
        $this->period = 'custom';
        $this->calculateMetrics();
    }

    private function calculateMetrics()
    {
        // Sales Metrics
        $invoicesQuery = Invoice::whereBetween('invoice_date', [$this->dateFrom, $this->dateTo]);
        $this->totalSales = $invoicesQuery->sum('total_amount');
        $this->totalInvoices = $invoicesQuery->count();
        $this->paidInvoices = $invoicesQuery->where('payment_status', 'paid')->count();
        $this->unpaidInvoices = $invoicesQuery->whereIn('payment_status', ['unpaid', 'partial'])->count();

        // Expense Metrics
        $expensesQuery = Expense::whereBetween('expense_date', [$this->dateFrom, $this->dateTo])
            ->where('approval_status', 'approved');
        $this->totalExpenses = $expensesQuery->sum('amount');

        // Net Profit
        $this->netProfit = $this->totalSales - $this->totalExpenses;

        // Cash & Bank Balance
        $cashLedger = AccountLedger::where('ledger_type', 'cash')
            ->where('ledger_name', 'Cash in Hand')
            ->first();
        $this->cashBalance = $cashLedger ? $cashLedger->current_balance : 0;
        $this->bankBalance = CompanyBankAccount::active()->sum('current_balance');

        // Outstanding Receivables
        $this->totalOutstanding = Invoice::where('invoice_type', 'client')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->sum('balance_amount');

        // Outstanding Payables
        $supplierLedgers = AccountLedger::where('ledger_type', 'supplier')->get();
        $this->totalPayable = $supplierLedgers->where('current_balance', '>', 0)->sum('current_balance');

        // General Statistics
        $this->totalClients = Client::where('is_active', true)->count();
        $this->totalSuppliers = Supplier::where('is_active', true)->count();
        $this->totalProducts = Product::where('is_active', true)->count();
        try {
            // Try using minimum_stock_level if it exists
            $this->lowStockProducts = Product::where('is_active', true)
                ->whereRaw('stock_quantity <= COALESCE(minimum_stock_level, 10)')
                ->count();
        } catch (\Exception $e) {
            // Fallback to fixed threshold if column doesn't exist
            $this->lowStockProducts = Product::where('is_active', true)
                ->where('stock_quantity', '<=', 10)
                ->count();
        }

        // Recent Data
        $this->loadRecentData();

        // Chart Data
        $this->loadChartData();
    }

    private function loadRecentData()
    {
        // Recent Invoices (Last 5)
        $this->recentInvoices = Invoice::with('client')
            ->whereBetween('invoice_date', [$this->dateFrom, $this->dateTo])
            ->orderBy('invoice_date', 'desc')
            ->take(5)
            ->get();

        // Recent Expenses (Last 5)
        $this->recentExpenses = Expense::with('category')
            ->whereBetween('expense_date', [$this->dateFrom, $this->dateTo])
            ->where('approval_status', 'approved')
            ->orderBy('expense_date', 'desc')
            ->take(5)
            ->get();

        // Recent Payments (Last 5)
        $clientLedgerIds = AccountLedger::where('ledger_type', 'client')->pluck('id');
        $this->recentPayments = LedgerTransaction::with('ledger.ledgerable')
            ->whereIn('ledger_id', $clientLedgerIds)
            ->where('type', 'payment')
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->orderBy('date', 'desc')
            ->take(5)
            ->get();

        // Top Clients by Sales
        $this->topClients = Invoice::select('client_id', DB::raw('SUM(total_amount) as total_sales'))
            ->where('invoice_type', 'client')
            ->whereBetween('invoice_date', [$this->dateFrom, $this->dateTo])
            ->with('client')
            ->groupBy('client_id')
            ->orderBy('total_sales', 'desc')
            ->take(5)
            ->get();

        // Low Stock Items
        $this->lowStockItems = Product::where('is_active', true)
            ->where('stock_quantity', '<=', 10) // Use a fixed threshold like 10
            ->orderBy('stock_quantity', 'asc')
            ->take(5)
            ->get();
    }

    private function loadChartData()
    {
        // Daily Sales & Expenses Chart Data
        $dailyData = collect();
        $start = Carbon::parse($this->dateFrom);
        $end = Carbon::parse($this->dateTo);

        while ($start->lte($end)) {
            $date = $start->format('Y-m-d');
            $sales = Invoice::whereDate('invoice_date', $date)->sum('total_amount');
            $expenses = Expense::whereDate('expense_date', $date)
                ->where('approval_status', 'approved')
                ->sum('amount');

            $dailyData->push([
                'date' => $start->format('d M'),
                'sales' => $sales,
                'expenses' => $expenses,
            ]);

            $start->addDay();
        }

        // Update Mary UI chart data
        Arr::set($this->salesExpensesChart, 'data.labels', $dailyData->pluck('date')->toArray());
        Arr::set($this->salesExpensesChart, 'data.datasets.0.data', $dailyData->pluck('sales')->toArray());
        Arr::set($this->salesExpensesChart, 'data.datasets.1.data', $dailyData->pluck('expenses')->toArray());

        $this->salesChartData = $dailyData->toArray();

        // Monthly Comparison (Last 6 months)
       $monthlyData = collect();
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();

            $sales = Invoice::whereBetween('invoice_date', [$monthStart, $monthEnd])->sum('total_amount');
            $expenses = Expense::whereBetween('expense_date', [$monthStart, $monthEnd])
                ->where('approval_status', 'approved')
                ->sum('amount');

            $monthlyData->push([
                'month' => $monthStart->format('M Y'),
                'sales' => $sales,
                'expenses' => $expenses,
                'profit' => $sales - $expenses,
            ]);
        }

        // Update Mary UI chart data
        Arr::set($this->monthlyComparisonChart, 'data.labels', $monthlyData->pluck('month')->toArray());
        Arr::set($this->monthlyComparisonChart, 'data.datasets.0.data', $monthlyData->pluck('sales')->toArray());
        Arr::set($this->monthlyComparisonChart, 'data.datasets.1.data', $monthlyData->pluck('expenses')->toArray());
        Arr::set($this->monthlyComparisonChart, 'data.datasets.2.data', $monthlyData->pluck('profit')->toArray());
    
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
