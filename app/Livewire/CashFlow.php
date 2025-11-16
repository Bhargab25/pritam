<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use App\Models\CompanyBankAccount;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CashFlow extends Component
{
    use WithPagination, Toast;

    // Date filters
    public $dateFrom;
    public $dateTo;
    public $period = 'this_month'; // this_month, last_month, this_quarter, this_year, custom

    // Bank account filter
    public $selectedBankAccount = 'all';

    // Statistics
    public $openingBalance = 0;
    public $totalInflow = 0;
    public $totalOutflow = 0;
    public $closingBalance = 0;

    // Cash flow data
    public $inflowCategories = [];
    public $outflowCategories = [];
    public $bankTransactions = [];
    public $dailyCashFlow = [];

    public function mount()
    {
        $this->setPeriod('this_month');
    }

    public function setPeriod($period)
    {
        $this->period = $period;

        switch ($period) {
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

        $this->calculateCashFlow();
    }

    public function updatedDateFrom()
    {
        $this->period = 'custom';
        $this->calculateCashFlow();
    }

    public function updatedDateTo()
    {
        $this->period = 'custom';
        $this->calculateCashFlow();
    }

    public function updatedSelectedBankAccount()
    {
        $this->calculateCashFlow();
    }

    public function calculateCashFlow()
    {
        // Get opening balance (balance before date range)
        $this->openingBalance = $this->getOpeningBalance();

        // Get inflow and outflow data
        $this->calculateInflowOutflow();

        // Calculate closing balance
        $this->closingBalance = $this->openingBalance + $this->totalInflow - $this->totalOutflow;

        // Get daily cash flow for chart
        $this->calculateDailyCashFlow();
    }

    private function getOpeningBalance()
    {
        if ($this->selectedBankAccount === 'all') {
            $accounts = CompanyBankAccount::active()->get();
            $openingBalance = 0;

            foreach ($accounts as $account) {
                // Get the last transaction before date range
                $lastTransaction = BankTransaction::where('bank_account_id', $account->id)
                    ->where('transaction_date', '<', $this->dateFrom)
                    ->orderBy('transaction_date', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                $openingBalance += $lastTransaction ? $lastTransaction->balance_after : $account->opening_balance;
            }

            return $openingBalance;
        } else {
            $account = CompanyBankAccount::find($this->selectedBankAccount);
            $lastTransaction = BankTransaction::where('bank_account_id', $account->id)
                ->where('transaction_date', '<', $this->dateFrom)
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            return $lastTransaction ? $lastTransaction->balance_after : $account->opening_balance;
        }
    }

    private function calculateInflowOutflow()
    {
        $query = BankTransaction::whereBetween('transaction_date', [$this->dateFrom, $this->dateTo]);

        if ($this->selectedBankAccount !== 'all') {
            $query->where('bank_account_id', $this->selectedBankAccount);
        }

        // Calculate inflows (credits)
        $inflows = $query->clone()->where('type', 'credit')->get();
        $this->totalInflow = $inflows->sum('amount');

        $this->inflowCategories = $inflows->groupBy('category')
            ->map(function ($transactions, $category) {
                return [
                    'category' => $category ?: 'Uncategorized',
                    'amount' => $transactions->sum('amount'),
                    'count' => $transactions->count(),
                    'percentage' => 0, // Will calculate below
                ];
            })->sortByDesc('amount')->values()->toArray();

        // Calculate percentages for inflow
        foreach ($this->inflowCategories as &$category) {
            $category['percentage'] = $this->totalInflow > 0 
                ? ($category['amount'] / $this->totalInflow) * 100 
                : 0;
        }

        // Calculate outflows (debits)
        $outflows = $query->clone()->where('type', 'debit')->get();
        $this->totalOutflow = $outflows->sum('amount');

        $this->outflowCategories = $outflows->groupBy('category')
            ->map(function ($transactions, $category) {
                return [
                    'category' => $category ?: 'Uncategorized',
                    'amount' => $transactions->sum('amount'),
                    'count' => $transactions->count(),
                    'percentage' => 0, // Will calculate below
                ];
            })->sortByDesc('amount')->values()->toArray();

        // Calculate percentages for outflow
        foreach ($this->outflowCategories as &$category) {
            $category['percentage'] = $this->totalOutflow > 0 
                ? ($category['amount'] / $this->totalOutflow) * 100 
                : 0;
        }

        // Get recent transactions
        $this->bankTransactions = $query->clone()
            ->with(['bankAccount'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();
    }

    private function calculateDailyCashFlow()
    {
        $query = BankTransaction::whereBetween('transaction_date', [$this->dateFrom, $this->dateTo]);

        if ($this->selectedBankAccount !== 'all') {
            $query->where('bank_account_id', $this->selectedBankAccount);
        }

        $dailyData = $query->selectRaw('
                DATE(transaction_date) as date,
                SUM(CASE WHEN type = "credit" THEN amount ELSE 0 END) as inflow,
                SUM(CASE WHEN type = "debit" THEN amount ELSE 0 END) as outflow
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $runningBalance = $this->openingBalance;
        $this->dailyCashFlow = $dailyData->map(function ($day) use (&$runningBalance) {
            $runningBalance = $runningBalance + $day->inflow - $day->outflow;
            return [
                'date' => Carbon::parse($day->date)->format('d M'),
                'inflow' => $day->inflow,
                'outflow' => $day->outflow,
                'net' => $day->inflow - $day->outflow,
                'balance' => $runningBalance,
            ];
        })->toArray();
    }

    public function exportCashFlow()
    {
        // Implement export functionality (CSV/PDF)
        $this->info('Export functionality coming soon!');
    }

    public function render()
    {
        return view('livewire.cash-flow', [
            'bankAccounts' => CompanyBankAccount::active()->get(),
        ]);
    }
}
