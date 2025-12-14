<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use App\Models\CompanyBankAccount;
use App\Models\BankTransaction;
use App\Models\AccountLedger;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CashFlow extends Component
{
    use WithPagination, Toast;

    // Date filters
    public $dateFrom;
    public $dateTo;
    public $period = 'this_month';

    // Account filter - now includes cash
    public $selectedAccount = 'all'; // all, cash, or bank_account_id

    // Cash management
    public $cashLedger;
    public $showCashTransactionModal = false;
    public $showTransferModal = false;

    // Transaction form
    public $newTransaction = [
        'date' => '',
        'type' => 'receipt',
        'description' => '',
        'amount' => 0,
        'reference' => '',
        'category' => 'sales',
    ];

    // Transfer form
    public $transfer = [
        'date' => '',
        'amount' => 0,
        'from_type' => 'cash',
        'to_type' => 'bank',
        'bank_account_id' => null,
        'description' => '',
        'reference' => '',
    ];

    // Statistics
    public $cashBalance = 0;
    public $bankBalance = 0;
    public $openingBalance = 0;
    public $totalInflow = 0;
    public $totalOutflow = 0;
    public $closingBalance = 0;

    // Cash flow data
    public $inflowCategories = [];
    public $outflowCategories = [];
    public $allTransactions = [];
    public $dailyCashFlow = [];

    public $categoryOptions = [
        ['id' => 'opening', 'name' => 'Opening Balance'],
        ['id' => 'payment', 'name' => 'Payment'],
        ['id' => 'sale', 'name' => 'Sales'],
        ['id' => 'expense', 'name' => 'Expense'],
        ['id' => 'salary', 'name' => 'Salary'],
        ['id' => 'purchase', 'name' => 'Purchase'],
        ['id' => 'refund', 'name' => 'Refund'],
        ['id' => 'transfer', 'name' => 'Transfer'],
        ['id' => 'other', 'name' => 'Other'],
    ];

    public function mount()
    {
        // Get or create cash ledger
        $this->cashLedger = AccountLedger::firstOrCreate(
            ['ledger_type' => 'cash', 'ledger_name' => 'Cash in Hand'],
            [
                'opening_balance' => 0,
                'opening_balance_type' => 'debit',
                'current_balance' => 0,
                'is_active' => true,
            ]
        );

        $this->setPeriod('this_month');
        $this->resetTransactionForm();
        $this->resetTransferForm();
    }

    public function resetTransactionForm()
    {
        $this->newTransaction = [
            'date' => now()->format('Y-m-d'),
            'type' => 'receipt',
            'description' => '',
            'amount' => 0,
            'reference' => '',
            'category' => 'sales',
        ];
    }

    public function resetTransferForm()
    {
        $this->transfer = [
            'date' => now()->format('Y-m-d'),
            'amount' => 0,
            'from_type' => 'cash',
            'to_type' => 'bank',
            'bank_account_id' => null,
            'description' => 'Cash deposit to bank',
            'reference' => '',
        ];
    }

    public function openCashTransactionModal()
    {
        $this->resetTransactionForm();
        $this->showCashTransactionModal = true;
    }

    public function openTransferModal()
    {
        $this->resetTransferForm();
        $this->showTransferModal = true;
    }

    // Add cash transaction
    public function addCashTransaction()
    {
        $this->validate([
            'newTransaction.date' => 'required|date',
            'newTransaction.type' => 'required|in:receipt,payment',
            'newTransaction.description' => 'required|string|max:255',
            'newTransaction.amount' => 'required|numeric|min:0.01',
            'newTransaction.reference' => 'nullable|string|max:100',
            'newTransaction.category' => 'required|string',
        ]);

        try {
            DB::transaction(function () {
                $ledger = $this->cashLedger;

                $debitAmount = 0;
                $creditAmount = 0;

                if ($this->newTransaction['type'] === 'receipt') {
                    $debitAmount = $this->newTransaction['amount'];
                } else {
                    $creditAmount = $this->newTransaction['amount'];
                }

                $ledger->transactions()->create([
                    'date' => $this->newTransaction['date'],
                    'type' => $this->newTransaction['type'],
                    'description' => $this->newTransaction['description'],
                    'debit_amount' => $debitAmount,
                    'credit_amount' => $creditAmount,
                    'reference' => $this->newTransaction['reference'],
                ]);

                $ledger->current_balance += ($debitAmount - $creditAmount);
                $ledger->save();
            });

            $this->success('Cash transaction added successfully!');
            $this->showCashTransactionModal = false;
            $this->resetTransactionForm();
            $this->calculateCashFlow();
        } catch (\Exception $e) {
            Log::error('Error adding cash transaction: ' . $e->getMessage());
            $this->error('Error adding transaction: ' . $e->getMessage());
        }
    }

    // Transfer between cash and bank
    public function transferFunds()
    {
        $this->validate([
            'transfer.date' => 'required|date',
            'transfer.amount' => 'required|numeric|min:0.01',
            'transfer.from_type' => 'required|in:cash,bank',
            'transfer.to_type' => 'required|in:cash,bank',
            'transfer.bank_account_id' => 'required_if:transfer.from_type,bank|required_if:transfer.to_type,bank|nullable|exists:company_bank_accounts,id',
            'transfer.description' => 'required|string|max:255',
            'transfer.reference' => 'nullable|string|max:100',
        ]);

        if ($this->transfer['from_type'] === $this->transfer['to_type']) {
            $this->error('Cannot transfer between same account types');
            return;
        }

        try {
            DB::transaction(function () {
                $cashLedger = $this->cashLedger;

                if ($this->transfer['from_type'] === 'cash') {
                    // Cash to Bank
                    $cashTransaction = $cashLedger->transactions()->create([
                        'date' => $this->transfer['date'],
                        'type' => 'payment',
                        'description' => $this->transfer['description'],
                        'debit_amount' => 0,
                        'credit_amount' => $this->transfer['amount'],
                        'reference' => $this->transfer['reference'],
                    ]);

                    $cashLedger->current_balance -= $this->transfer['amount'];
                    $cashLedger->save();

                    $bankAccount = CompanyBankAccount::find($this->transfer['bank_account_id']);
                    if ($bankAccount) {
                        $bankAccount->recordTransaction(
                            'credit',
                            $this->transfer['amount'],
                            $this->transfer['description'],
                            [
                                'transaction_date' => $this->transfer['date'],
                                'category' => 'transfer',
                                'reference_number' => $this->transfer['reference'],
                                'transactionable_type' => LedgerTransaction::class,
                                'transactionable_id' => $cashTransaction->id,
                            ]
                        );
                    }
                } else {
                    // Bank to Cash
                    $cashTransaction = $cashLedger->transactions()->create([
                        'date' => $this->transfer['date'],
                        'type' => 'receipt',
                        'description' => $this->transfer['description'],
                        'debit_amount' => $this->transfer['amount'],
                        'credit_amount' => 0,
                        'reference' => $this->transfer['reference'],
                    ]);

                    $cashLedger->current_balance += $this->transfer['amount'];
                    $cashLedger->save();

                    $bankAccount = CompanyBankAccount::find($this->transfer['bank_account_id']);
                    if ($bankAccount) {
                        $bankAccount->recordTransaction(
                            'debit',
                            $this->transfer['amount'],
                            $this->transfer['description'],
                            [
                                'transaction_date' => $this->transfer['date'],
                                'category' => 'transfer',
                                'reference_number' => $this->transfer['reference'],
                                'transactionable_type' => LedgerTransaction::class,
                                'transactionable_id' => $cashTransaction->id,
                            ]
                        );
                    }
                }
            });

            $this->success('Funds transferred successfully!');
            $this->showTransferModal = false;
            $this->resetTransferForm();
            $this->calculateCashFlow();
        } catch (\Exception $e) {
            Log::error('Error transferring funds: ' . $e->getMessage());
            $this->error('Error transferring funds: ' . $e->getMessage());
        }
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

    public function updatedSelectedAccount()
    {
        $this->calculateCashFlow();
    }

    public function calculateCashFlow()
    {
        // Get current balances
        $this->cashBalance = $this->cashLedger->current_balance;
        $this->bankBalance = CompanyBankAccount::active()->sum('current_balance');

        // Get opening balance
        $this->openingBalance = $this->getOpeningBalance();

        // Get combined inflow and outflow data
        $this->calculateInflowOutflow();

        // Calculate closing balance
        $this->closingBalance = $this->openingBalance + $this->totalInflow - $this->totalOutflow;

        // Get daily cash flow for chart
        $this->calculateDailyCashFlow();
    }

    private function getOpeningBalance()
    {
        $balance = 0;

        if ($this->selectedAccount === 'all' || $this->selectedAccount === 'cash') {
            // Get cash opening balance
            $lastCashTransaction = LedgerTransaction::where('ledger_id', $this->cashLedger->id)
                ->where('date', '<', $this->dateFrom)
                ->orderBy('date', 'desc')
                ->first();

            if ($lastCashTransaction) {
                $balance += $this->cashLedger->opening_balance 
                    + LedgerTransaction::where('ledger_id', $this->cashLedger->id)
                        ->where('date', '<', $this->dateFrom)
                        ->sum(DB::raw('debit_amount - credit_amount'));
            } else {
                $balance += $this->cashLedger->opening_balance;
            }
        }

        if ($this->selectedAccount === 'all') {
            // Get bank opening balances
            $accounts = CompanyBankAccount::active()->get();
            foreach ($accounts as $account) {
                $lastTransaction = BankTransaction::where('bank_account_id', $account->id)
                    ->where('transaction_date', '<', $this->dateFrom)
                    ->orderBy('transaction_date', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                $balance += $lastTransaction ? $lastTransaction->balance_after : $account->opening_balance;
            }
        } elseif ($this->selectedAccount !== 'cash') {
            // Specific bank account
            $account = CompanyBankAccount::find($this->selectedAccount);
            if ($account) {
                $lastTransaction = BankTransaction::where('bank_account_id', $account->id)
                    ->where('transaction_date', '<', $this->dateFrom)
                    ->orderBy('transaction_date', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                $balance += $lastTransaction ? $lastTransaction->balance_after : $account->opening_balance;
            }
        }

        return $balance;
    }

    private function calculateInflowOutflow()
    {
        $this->allTransactions = collect();

        // Get cash transactions
        if ($this->selectedAccount === 'all' || $this->selectedAccount === 'cash') {
            $cashTransactions = LedgerTransaction::where('ledger_id', $this->cashLedger->id)
                ->whereBetween('date', [$this->dateFrom, $this->dateTo])
                ->orderBy('date', 'desc')
                ->get()
                ->map(function ($transaction) {
                    return [
                        'date' => Carbon::parse($transaction->date),
                        'account' => 'Cash in Hand',
                        'account_type' => 'cash',
                        'category' => ucfirst($transaction->type),
                        'description' => $transaction->description,
                        'reference' => $transaction->reference,
                        'type' => $transaction->debit_amount > 0 ? 'credit' : 'debit',
                        'amount' => $transaction->debit_amount > 0 ? $transaction->debit_amount : $transaction->credit_amount,
                        'balance_after' => null,
                    ];
                });

            $this->allTransactions = $this->allTransactions->concat($cashTransactions);
        }

        // Get bank transactions
        $bankQuery = BankTransaction::whereBetween('transaction_date', [$this->dateFrom, $this->dateTo]);

        if ($this->selectedAccount !== 'all' && $this->selectedAccount !== 'cash') {
            $bankQuery->where('bank_account_id', $this->selectedAccount);
        } elseif ($this->selectedAccount === 'cash') {
            // Skip bank transactions if only cash is selected
            $bankQuery->whereRaw('1 = 0');
        }

        $bankTransactions = $bankQuery->with('bankAccount')
            ->orderBy('transaction_date', 'desc')
            ->get()
            ->map(function ($transaction) {
                return [
                    'date' => $transaction->transaction_date,
                    'account' => $transaction->bankAccount->account_name,
                    'account_type' => 'bank',
                    'category' => $transaction->category ?: 'Uncategorized',
                    'description' => $transaction->description,
                    'reference' => $transaction->reference_number,
                    'type' => $transaction->type,
                    'amount' => $transaction->amount,
                    'balance_after' => $transaction->balance_after,
                ];
            });

        $this->allTransactions = $this->allTransactions->concat($bankTransactions)->sortByDesc('date')->values();

        // Calculate totals
        $inflows = $this->allTransactions->where('type', 'credit');
        $outflows = $this->allTransactions->where('type', 'debit');

        $this->totalInflow = $inflows->sum('amount');
        $this->totalOutflow = $outflows->sum('amount');

        // Group by category for inflow
        $this->inflowCategories = $inflows->groupBy('category')
            ->map(function ($transactions, $category) {
                $amount = $transactions->sum('amount');
                return [
                    'category' => $category,
                    'amount' => $amount,
                    'count' => $transactions->count(),
                    'percentage' => $this->totalInflow > 0 ? ($amount / $this->totalInflow) * 100 : 0,
                ];
            })->sortByDesc('amount')->values()->toArray();

        // Group by category for outflow
        $this->outflowCategories = $outflows->groupBy('category')
            ->map(function ($transactions, $category) {
                $amount = $transactions->sum('amount');
                return [
                    'category' => $category,
                    'amount' => $amount,
                    'count' => $transactions->count(),
                    'percentage' => $this->totalOutflow > 0 ? ($amount / $this->totalOutflow) * 100 : 0,
                ];
            })->sortByDesc('amount')->values()->toArray();
    }

    private function calculateDailyCashFlow()
    {
        // This is a simplified version - you may want to enhance this
        $dailyData = $this->allTransactions->groupBy(function ($transaction) {
            return $transaction['date']->format('Y-m-d');
        })->map(function ($transactions, $date) {
            $inflow = $transactions->where('type', 'credit')->sum('amount');
            $outflow = $transactions->where('type', 'debit')->sum('amount');

            return [
                'date' => Carbon::parse($date)->format('d M'),
                'inflow' => $inflow,
                'outflow' => $outflow,
                'net' => $inflow - $outflow,
            ];
        })->sortKeys();

        $runningBalance = $this->openingBalance;
        $this->dailyCashFlow = $dailyData->map(function ($day) use (&$runningBalance) {
            $runningBalance += $day['net'];
            $day['balance'] = $runningBalance;
            return $day;
        })->values()->toArray();
    }

    public function exportCashFlow()
    {
        $this->info('Export functionality coming soon!');
    }

    public function render()
    {
        $bankAccounts = CompanyBankAccount::active()->get();

        // Add "All Accounts" and "Cash" options
        $accountOptions = collect([
            (object)['id' => 'all', 'account_name' => 'All Accounts'],
            (object)['id' => 'cash', 'account_name' => '💵 Cash in Hand'],
        ])->concat($bankAccounts);

        return view('livewire.cash-flow', [
            'bankAccounts' => $bankAccounts,
            'accountOptions' => $accountOptions,
        ]);
    }
}
