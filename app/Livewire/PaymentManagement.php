<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\LedgerTransaction;
use App\Models\CompanyBankAccount;
use App\Models\AccountLedger;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Mary\Traits\Toast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

#[Title('Payment Management')]
class PaymentManagement extends Component
{
    use WithPagination, Toast;

    // Modal states
    public $showReceivePaymentModal = false;
    public $showMakePaymentModal = false;

    // Search and filters
    public $search = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $paymentTypeFilter = 'all'; // all, receive, pay
    public $paymentMethodFilter = 'all'; // all, cash, bank
    public $entityFilter = 'all'; // all, client, supplier
    public $perPage = 15;

    // Receive Payment (from clients)
    public $receivePayment = [
        'client_id' => null,
        'date' => '',
        'amount' => 0,
        'payment_method' => 'cash',
        'bank_account_id' => null,
        'reference' => '',
        'description' => '',
    ];

    // Make Payment (to suppliers)
    public $makePayment = [
        'supplier_id' => null,
        'date' => '',
        'amount' => 0,
        'payment_method' => 'cash',
        'bank_account_id' => null,
        'reference' => '',
        'description' => '',
    ];

    public $bankAccounts ;
    public $formattedBankAccounts = [];

    // Statistics
    public $totalReceived = 0;
    public $totalPaid = 0;
    public $todayReceived = 0;
    public $todayPaid = 0;

    protected $listeners = ['refreshPayments' => '$refresh'];

    public function mount()
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');

        $this->receivePayment['date'] = now()->format('Y-m-d');
        $this->makePayment['date'] = now()->format('Y-m-d');

        $this->calculateStatistics();
        $this->loadBankAccounts();
    }

    public function loadBankAccounts()
    {
        $this->bankAccounts = CompanyBankAccount::active()->get();

        // ✅ Format for Mary UI Select (array format)
        $this->formattedBankAccounts = $this->bankAccounts->map(function ($account) {
            return [
                'value' => $account->id,
                'label' => $account->bank_name . ' - ' . $account->account_number
            ];
        })->toArray();
    }

    public function calculateStatistics()
    {
        // Total received from clients (credit to client ledgers)
        $clientLedgerIds = AccountLedger::where('ledger_type', 'client')->pluck('id');
        $this->totalReceived = LedgerTransaction::whereIn('ledger_id', $clientLedgerIds)
            ->where('type', 'payment')
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->sum('credit_amount');

        // Total paid to suppliers (credit to supplier ledgers)
        $supplierLedgerIds = AccountLedger::where('ledger_type', 'supplier')->pluck('id');
        $this->totalPaid = LedgerTransaction::whereIn('ledger_id', $supplierLedgerIds)
            ->where('type', 'payment')
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->sum('credit_amount');

        // Today's transactions
        $this->todayReceived = LedgerTransaction::whereIn('ledger_id', $clientLedgerIds)
            ->where('type', 'payment')
            ->whereDate('date', today())
            ->sum('credit_amount');

        $this->todayPaid = LedgerTransaction::whereIn('ledger_id', $supplierLedgerIds)
            ->where('type', 'payment')
            ->whereDate('date', today())
            ->sum('credit_amount');
    }

    public function updatedDateFrom()
    {
        $this->calculateStatistics();
        $this->resetPage();
    }

    public function updatedDateTo()
    {
        $this->calculateStatistics();
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedPaymentTypeFilter()
    {
        $this->resetPage();
    }

    public function updatedPaymentMethodFilter()
    {
        $this->resetPage();
    }

    public function updatedEntityFilter()
    {
        $this->resetPage();
    }

    public function openReceivePaymentModal()
    {
        $this->reset('receivePayment');
        $this->receivePayment = [
            'client_id' => null,
            'date' => now()->format('Y-m-d'),
            'amount' => 0,
            'payment_method' => 'cash',
            'bank_account_id' => null,
            'reference' => '',
            'description' => 'Payment received',
        ];
        $this->showReceivePaymentModal = true;
    }

    public function openMakePaymentModal()
    {
        $this->reset('makePayment');
        $this->makePayment = [
            'supplier_id' => null,
            'date' => now()->format('Y-m-d'),
            'amount' => 0,
            'payment_method' => 'cash',
            'bank_account_id' => null,
            'reference' => '',
            'description' => 'Payment made',
        ];
        $this->showMakePaymentModal = true;
    }

    public function getPaymentAllocationPreviewProperty()
    {
        if (!$this->receivePayment['client_id'] || !$this->receivePayment['amount']) {
            return null;
        }

        $amount = (float)$this->receivePayment['amount'];
        if ($amount <= 0) {
            return null;
        }

        $client = Client::find($this->receivePayment['client_id']);
        if (!$client) {
            return null;
        }

        // Get unpaid/partial invoices ordered by date (oldest first)
        $invoices = Invoice::where('client_id', $client->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('balance_amount', '>', 0)
            ->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $remainingAmount = $amount;
        $allocations = [];

        foreach ($invoices as $invoice) {
            if ($remainingAmount <= 0) break;

            $allocateAmount = min($remainingAmount, $invoice->balance_amount);
            $allocations[] = [
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date->format('d M Y'),
                'balance_before' => $invoice->balance_amount,
                'will_allocate' => $allocateAmount,
                'balance_after' => $invoice->balance_amount - $allocateAmount,
                'status' => ($invoice->balance_amount - $allocateAmount) <= 0 ? 'paid' : 'partial',
            ];

            $remainingAmount -= $allocateAmount;
        }

        return [
            'allocations' => $allocations,
            'total_allocated' => $amount - $remainingAmount,
            'remaining' => $remainingAmount,
            'has_allocations' => count($allocations) > 0,
        ];
    }

    // Add this method to get client outstanding info
    public function getClientOutstandingProperty()
    {
        if (!$this->receivePayment['client_id']) {
            return null;
        }

        $client = Client::with('ledger')->find($this->receivePayment['client_id']);
        if (!$client || !$client->ledger) {
            return null;
        }

        $totalOutstanding = Invoice::where('client_id', $client->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->sum('balance_amount');

        $unpaidInvoicesCount = Invoice::where('client_id', $client->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('balance_amount', '>', 0)
            ->count();

        return [
            'total_outstanding' => $totalOutstanding,
            'unpaid_invoices_count' => $unpaidInvoicesCount,
            'ledger_balance' => $client->ledger->current_balance,
        ];
    }

    public function saveReceivePayment()
    {
        $rules = [
            'receivePayment.client_id' => 'required|exists:clients,id',
            'receivePayment.date' => 'required|date',
            'receivePayment.amount' => 'required|numeric|min:0.01',
            'receivePayment.payment_method' => 'required|in:cash,bank',
            'receivePayment.reference' => 'nullable|string|max:100',
            'receivePayment.description' => 'required|string|max:255',
        ];

        if ($this->receivePayment['payment_method'] === 'bank') {
            $rules['receivePayment.bank_account_id'] = 'required|exists:company_bank_accounts,id';
        }

        $this->validate($rules);

        try {
            DB::transaction(function () {
                $client = Client::with('ledger')->find($this->receivePayment['client_id']);

                if (!$client->ledger) {
                    $client->ledger()->create([
                        'ledger_name' => $client->name,
                        'ledger_type' => 'client',
                        'opening_balance' => 0,
                        'opening_balance_type' => 'credit',
                        'current_balance' => 0,
                        'is_active' => true,
                    ]);
                    $client->load('ledger');
                }

                $ledger = $client->ledger;

                // Allocate payment to unpaid invoices
                $allocationResult = Invoice::allocatePaymentToInvoices(
                    $client->id,
                    $this->receivePayment['amount'],
                    $this->receivePayment['date'],
                    $this->receivePayment['reference']
                );

                $description = $this->receivePayment['description'];
                if (!empty($allocationResult['allocations'])) {
                    $allocationSummary = collect($allocationResult['allocations'])
                        ->map(fn($a) => "{$a['invoice_number']}: ₹" . number_format($a['amount_allocated'], 2))
                        ->join(', ');
                    $description .= " | Allocated to: " . $allocationSummary;
                }

                if ($allocationResult['remaining_amount'] > 0) {
                    $description .= " | Advance: ₹" . number_format($allocationResult['remaining_amount'], 2);
                }

                // Create ledger transaction (credit = payment received)
                $ledgerTransaction = $ledger->transactions()->create([
                    'date' => $this->receivePayment['date'],
                    'type' => 'payment',
                    'description' => $description,
                    'debit_amount' => 0,
                    'credit_amount' => $this->receivePayment['amount'],
                    'reference' => $this->receivePayment['reference'],
                ]);

                // Update ledger balance
                $ledger->current_balance -= $this->receivePayment['amount'];
                $ledger->save();

                // ✅ NEW: Record cash/bank transaction
                if ($this->receivePayment['payment_method'] === 'cash') {
                    // Get or create cash ledger
                    $cashLedger = AccountLedger::firstOrCreate(
                        ['ledger_type' => 'cash', 'ledger_name' => 'Cash in Hand'],
                        [
                            'opening_balance' => 0,
                            'opening_balance_type' => 'debit',
                            'current_balance' => 0,
                            'is_active' => true,
                        ]
                    );

                    // Payment received = money IN (debit to cash)
                    $cashLedger->transactions()->create([
                        'date' => $this->receivePayment['date'],
                        'type' => 'payment',
                        'description' => "Payment received from client: {$client->name} - {$description}",
                        'debit_amount' => $this->receivePayment['amount'],
                        'credit_amount' => 0,
                        'reference' => $this->receivePayment['reference'],
                        'referenceable_type' => LedgerTransaction::class,
                        'referenceable_id' => $ledgerTransaction->id,
                    ]);

                    // Update cash balance
                    $cashLedger->current_balance += $this->receivePayment['amount'];
                    $cashLedger->save();
                } elseif ($this->receivePayment['payment_method'] === 'bank') {
                    $bankAccount = CompanyBankAccount::find($this->receivePayment['bank_account_id']);

                    if ($bankAccount) {
                        $bankAccount->recordTransaction(
                            'credit',
                            $this->receivePayment['amount'],
                            "Payment received from client: {$client->name}",
                            [
                                'transaction_date' => $this->receivePayment['date'],
                                'category' => 'payment_received',
                                'reference_number' => $this->receivePayment['reference'],
                                'transactionable_type' => LedgerTransaction::class,
                                'transactionable_id' => $ledgerTransaction->id,
                            ]
                        );
                    }
                }
            });

            $this->success('Payment Received!', 'Client payment recorded successfully.');
            $this->showReceivePaymentModal = false;
            $this->calculateStatistics();
            $this->dispatch('refreshPayments');
        } catch (\Exception $e) {
            Log::error('Error receiving payment: ' . $e->getMessage());
            $this->error('Error', 'Failed to record payment: ' . $e->getMessage());
        }
    }


    public function saveMakePayment()
    {
        $rules = [
            'makePayment.supplier_id' => 'required|exists:suppliers,id',
            'makePayment.date' => 'required|date',
            'makePayment.amount' => 'required|numeric|min:0.01',
            'makePayment.payment_method' => 'required|in:cash,bank',
            'makePayment.reference' => 'nullable|string|max:100',
            'makePayment.description' => 'required|string|max:255',
        ];

        if ($this->makePayment['payment_method'] === 'bank') {
            $rules['makePayment.bank_account_id'] = 'required|exists:company_bank_accounts,id';
        }

        $this->validate($rules);

        try {
            DB::transaction(function () {
                $supplier = Supplier::with('ledger')->find($this->makePayment['supplier_id']);

                if (!$supplier->ledger) {
                    $supplier->ledger()->create([
                        'ledger_name' => $supplier->name,
                        'ledger_type' => 'supplier',
                        'opening_balance' => 0,
                        'opening_balance_type' => 'debit',
                        'current_balance' => 0,
                        'is_active' => true,
                    ]);
                    $supplier->load('ledger');
                }

                $ledger = $supplier->ledger;

                // Create ledger transaction (credit = payment made)
                $ledgerTransaction = $ledger->transactions()->create([
                    'date' => $this->makePayment['date'],
                    'type' => 'payment',
                    'description' => $this->makePayment['description'],
                    'debit_amount' => 0,
                    'credit_amount' => $this->makePayment['amount'],
                    'reference' => $this->makePayment['reference'],
                ]);

                // Update ledger balance
                $ledger->current_balance -= $this->makePayment['amount'];
                $ledger->save();

                // ✅ NEW: Record cash/bank transaction
                if ($this->makePayment['payment_method'] === 'cash') {
                    // Get or create cash ledger
                    $cashLedger = AccountLedger::firstOrCreate(
                        ['ledger_type' => 'cash', 'ledger_name' => 'Cash in Hand'],
                        [
                            'opening_balance' => 0,
                            'opening_balance_type' => 'debit',
                            'current_balance' => 0,
                            'is_active' => true,
                        ]
                    );

                    // Payment made = money OUT (credit from cash)
                    $cashLedger->transactions()->create([
                        'date' => $this->makePayment['date'],
                        'type' => 'payment',
                        'description' => "Payment made to supplier: {$supplier->name} - {$this->makePayment['description']}",
                        'debit_amount' => 0,
                        'credit_amount' => $this->makePayment['amount'],
                        'reference' => $this->makePayment['reference'],
                        'referenceable_type' => LedgerTransaction::class,
                        'referenceable_id' => $ledgerTransaction->id,
                    ]);

                    // Update cash balance
                    $cashLedger->current_balance -= $this->makePayment['amount'];
                    $cashLedger->save();
                } elseif ($this->makePayment['payment_method'] === 'bank') {
                    $bankAccount = CompanyBankAccount::find($this->makePayment['bank_account_id']);

                    if ($bankAccount) {
                        $bankAccount->recordTransaction(
                            'debit',
                            $this->makePayment['amount'],
                            "Payment made to supplier: {$supplier->name}",
                            [
                                'transaction_date' => $this->makePayment['date'],
                                'category' => 'payment_made',
                                'reference_number' => $this->makePayment['reference'],
                                'transactionable_type' => LedgerTransaction::class,
                                'transactionable_id' => $ledgerTransaction->id,
                            ]
                        );
                    }
                }
            });

            $this->success('Payment Made!', 'Supplier payment recorded successfully.');
            $this->showMakePaymentModal = false;
            $this->calculateStatistics();
            $this->dispatch('refreshPayments');
        } catch (\Exception $e) {
            Log::error('Error making payment: ' . $e->getMessage());
            $this->error('Error', 'Failed to record payment: ' . $e->getMessage());
        }
    }


    public function render()
    {
        // Get all payment transactions from both clients and suppliers
        $clientLedgerIds = AccountLedger::where('ledger_type', 'client')->pluck('id');
        $supplierLedgerIds = AccountLedger::where('ledger_type', 'supplier')->pluck('id');

        $query = LedgerTransaction::with(['ledger.ledgerable'])
            ->where('type', 'payment')
            ->whereBetween('date', [$this->dateFrom, $this->dateTo]);

        // Entity filter
        if ($this->entityFilter === 'client') {
            $query->whereIn('ledger_id', $clientLedgerIds);
        } elseif ($this->entityFilter === 'supplier') {
            $query->whereIn('ledger_id', $supplierLedgerIds);
        } else {
            $query->whereIn('ledger_id', $clientLedgerIds->merge($supplierLedgerIds));
        }

        // Search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('description', 'like', '%' . $this->search . '%')
                    ->orWhere('reference', 'like', '%' . $this->search . '%');
            });
        }

        $transactions = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($this->perPage);

        return view('livewire.payment-management', [
            'transactions' => $transactions,
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'bankAccounts' => CompanyBankAccount::active()->get(),
        ]);
    }
}
