<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Invoice;
use App\Models\Expense;
use App\Models\AccountLedger;
use App\Models\CompanyBankAccount;
use App\Models\LedgerTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

class DailyBusinessSummary extends Component
{
    public $dateFrom;
    public $dateTo;
    public $viewMode = 'daily'; // 'daily', 'weekly', 'monthly'
    
    // Daily Summary Data
    public $dailySummary = [];
    public $totalSummary = [
        'opening_balance' => 0,
        'total_sales' => 0,
        'total_purchases' => 0,
        'total_expenses' => 0,
        'payments_received' => 0,
        'payments_given' => 0,
        'net_cash_flow' => 0,
        'closing_balance' => 0,
    ];

    // Chart Data (Mary UI format)
    public array $dailyCashFlowChart = [
        'type' => 'line',
        'data' => [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Opening Balance',
                    'data' => [],
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Closing Balance',
                    'data' => [],
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Net Cash Flow',
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
                'legend' => ['position' => 'top']
            ],
            'scales' => [
                'y' => ['beginAtZero' => true]
            ]
        ]
    ];

    public function mount()
    {
        $this->dateFrom = now()->subDays(1)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->loadData();
    }

    public function updatedDateFrom()
    {
        $this->loadData();
    }

    public function updatedDateTo()
    {
        $this->loadData();
    }

    public function updatedViewMode()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->generateDailySummary();
        $this->updateChartData();
    }

    private function generateDailySummary()
    {
        $this->dailySummary = [];
        $startDate = Carbon::parse($this->dateFrom);
        $endDate = Carbon::parse($this->dateTo);
        
        $runningBalance = $this->getOpeningBalance($startDate->subDay());
        
        while ($startDate->lte($endDate)) {
            $date = $startDate->copy();
            
            // Get cash ledger
            $cashLedger = AccountLedger::where('ledger_type', 'cash')
                ->where('ledger_name', 'Cash in Hand')
                ->first();

            $dayData = [
                'date' => $date->format('Y-m-d'),
                'date_display' => $date->format('d M Y'),
                'opening_balance' => $runningBalance,
                
                // Sales (Invoices)
                'total_sales' => Invoice::whereDate('invoice_date', $date->format('Y-m-d'))
                    ->sum('final_amount'),
                
                // Purchases (Supplier ledger debits)
                'total_purchases' => LedgerTransaction::whereDate('date', $date->format('Y-m-d'))
                    ->whereHas('ledger', fn($q) => $q->where('ledger_type', 'supplier'))
                    ->where('debit_amount', '>', 0)
                    ->sum('debit_amount'),
                
                // Expenses (Approved expenses)
                'total_expenses' => Expense::whereDate('expense_date', $date->format('Y-m-d'))
                    ->where('approval_status', 'approved')
                    ->sum('amount'),
                
                // Payments Received (Client ledger credits)
                'payments_received' => LedgerTransaction::whereDate('date', $date->format('Y-m-d'))
                    ->whereHas('ledger', fn($q) => $q->where('ledger_type', 'client'))
                    ->where('credit_amount', '>', 0)
                    ->sum('credit_amount'),
                
                // Payments Given (Supplier ledger credits)
                'payments_given' => LedgerTransaction::whereDate('date', $date->format('Y-m-d'))
                    ->whereHas('ledger', fn($q) => $q->where('ledger_type', 'supplier'))
                    ->where('credit_amount', '>', 0)
                    ->sum('credit_amount'),
                
                'net_cash_flow' => 0,
                'closing_balance' => 0,
            ];

            // Calculate net cash flow and closing balance
            $inflows = $dayData['total_sales'] + $dayData['payments_received'];
            $outflows = $dayData['total_purchases'] + $dayData['total_expenses'] + $dayData['payments_given'];
            
            $dayData['net_cash_flow'] = $inflows - $outflows;
            $dayData['closing_balance'] = $runningBalance + $dayData['net_cash_flow'];
            
            // Update running balance for next day
            $runningBalance = $dayData['closing_balance'];

            $this->dailySummary[] = $dayData;
            $startDate->addDay();
        }

        // Calculate totals
        $this->calculateTotals();
    }

    private function getOpeningBalance($date)
    {
        $cashLedger = AccountLedger::where('ledger_type', 'cash')
            ->where('ledger_name', 'Cash in Hand')
            ->first();

        if (!$cashLedger) return 0;

        return $cashLedger->opening_balance + 
            LedgerTransaction::where('ledger_id', $cashLedger->id)
                ->where('date', '<=', $date->format('Y-m-d'))
                ->sum(DB::raw('debit_amount - credit_amount'));
    }

    private function calculateTotals()
    {
        $this->totalSummary = [
            'opening_balance' => $this->dailySummary[0]['opening_balance'] ?? 0,
            'total_sales' => collect($this->dailySummary)->sum('total_sales'),
            'total_purchases' => collect($this->dailySummary)->sum('total_purchases'),
            'total_expenses' => collect($this->dailySummary)->sum('total_expenses'),
            'payments_received' => collect($this->dailySummary)->sum('payments_received'),
            'payments_given' => collect($this->dailySummary)->sum('payments_given'),
            'net_cash_flow' => collect($this->dailySummary)->sum('net_cash_flow'),
            'closing_balance' => collect($this->dailySummary)->last()['closing_balance'] ?? 0,
        ];
    }

    private function updateChartData()
    {
        $labels = collect($this->dailySummary)->pluck('date_display')->toArray();
        $openingBalances = collect($this->dailySummary)->pluck('opening_balance')->toArray();
        $closingBalances = collect($this->dailySummary)->pluck('closing_balance')->toArray();
        $netFlows = collect($this->dailySummary)->pluck('net_cash_flow')->toArray();

        Arr::set($this->dailyCashFlowChart, 'data.labels', $labels);
        Arr::set($this->dailyCashFlowChart, 'data.datasets.0.data', $openingBalances);
        Arr::set($this->dailyCashFlowChart, 'data.datasets.1.data', $closingBalances);
        Arr::set($this->dailyCashFlowChart, 'data.datasets.2.data', $netFlows);
    }

    public function render()
    {
        return view('livewire.daily-business-summary');
    }
}
