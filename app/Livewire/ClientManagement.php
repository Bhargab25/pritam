<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use App\Models\Client;
use App\Models\AccountLedger;
use App\Models\CompanyBankAccount;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class ClientManagement extends Component
{
    use WithPagination, Toast;

    public $myModal = false;
    public $showDrawer = false;
    public $isEdit = false;
    public $search = '';
    public $statusFilter = [];
    public $appliedStatusFilter = [];
    public $locationFilter = [];
    public $appliedLocationFilter = [];
    public $showDetailsModal = false;
    public $selectedClient = null;
    public $showConfirmDeleteModal = false;
    public $confirmingDeleteId = null;

    // Basic Details
    public $name;
    public $company;
    public $contact_person;
    public $phone;
    public $email;

    // Address
    public $address;
    public $city;
    public $state;
    public $country;
    public $pincode;

    // Tax / Business Info
    public $gstin;
    public $pan;
    public $tin;

    // Banking Details
    public $bank_name;
    public $account_number;
    public $ifsc_code;
    public $branch;

    // Status
    public $is_active = true;

    public $clientId;
    public $sortBy = ['column' => 'name', 'direction' => 'asc'];
    public $perPage = 10;
    public $selected = [];
    public $filter = 'all';

    // Options for filters
    public $statusOptions = [
        ['id' => 'active', 'name' => 'Active'],
        ['id' => 'inactive', 'name' => 'Inactive'],
    ];

    public $activeTab = 'details';

    // Statistics properties
    public $totalClients = 0;
    public $activeClients = 0;
    public $totalOutstanding = 0;
    public $totalSales = 0;

    public $locationOptions = [];

    protected $listeners = [
        'refreshClients' => '$refresh',
        'pdfReady' => 'handlePdfReady'
    ];


    // Add these new properties for transaction management
    public $transactionFilter = '10';
    public $newTransaction = [
        'date' => '',
        'type' => 'sale',
        'description' => '',
        'amount' => 0,
        'reference' => '',
        'payment_method' => 'cash',
        'bank_account_id' => null,
    ];

    public $transactionFilterOptions = [
        '10' => 'Last 10 Transactions',
        '100' => 'Last 100 Transactions',
        'month' => 'Last Month',
        '3month' => 'Last 3 Months',
    ];

    public function mount()
    {
        // Load unique cities for location filter
        $this->locationOptions = Client::whereNotNull('city')
            ->distinct()
            ->pluck('city')
            ->filter()
            ->map(function ($city) {
                return ['id' => $city, 'name' => $city];
            })
            ->values()
            ->toArray();
        $this->calculateStatistics();

        // Initialize new transaction
        $this->newTransaction = [
            'date' => '',
            'type' => 'sale',
            'description' => '',
            'amount' => 0,
            'reference' => '',
            'payment_method' => 'cash',
            'bank_account_id' => null,
        ];
    }

    public function calculateStatistics()
    {
        $this->totalClients = Client::count();
        $this->activeClients = Client::where('is_active', true)->count();

        // Calculate totals from polymorphic ledgers
        $clientLedgers = AccountLedger::where('ledger_type', 'client')->get();
        $this->totalOutstanding = $clientLedgers->where('current_balance', '>', 0)->sum('current_balance');
        $this->totalSales = LedgerTransaction::whereIn('ledger_id', $clientLedgers->pluck('id'))
            ->where('type', 'sale')
            ->sum('debit_amount');
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
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
        $this->reset(['statusFilter', 'appliedStatusFilter', 'locationFilter', 'appliedLocationFilter']);
        $this->resetPage();
    }

    public function applyFilters()
    {
        $this->appliedStatusFilter = $this->statusFilter;
        $this->appliedLocationFilter = $this->locationFilter;
        $this->showDrawer = false;
        $this->resetPage();
        $this->success('Filters Applied!', 'Clients filtered successfully.');
    }

    public function newClient()
    {
        $this->reset([
            'clientId',
            'name',
            'company',
            'contact_person',
            'phone',
            'email',
            'address',
            'city',
            'state',
            'country',
            'pincode',
            'gstin',
            'pan',
            'tin',
            'bank_name',
            'account_number',
            'ifsc_code',
            'branch',
            'is_active'
        ]);
        $this->is_active = true;
        $this->isEdit = false;
        $this->myModal = true;
    }

    public function editClient($id)
    {
        $client = Client::find($id);
        if ($client) {
            $this->clientId = $client->id;
            $this->name = $client->name;
            $this->company = $client->company;
            $this->contact_person = $client->contact_person;
            $this->phone = $client->phone;
            $this->email = $client->email;
            $this->address = $client->address;
            $this->city = $client->city;
            $this->state = $client->state;
            $this->country = $client->country;
            $this->pincode = $client->pincode;
            $this->gstin = $client->gstin;
            $this->pan = $client->pan;
            $this->tin = $client->tin;
            $this->bank_name = $client->bank_name;
            $this->account_number = $client->account_number;
            $this->ifsc_code = $client->ifsc_code;
            $this->branch = $client->branch;
            $this->is_active = $client->is_active;
            $this->isEdit = true;
            $this->myModal = true;
        }
    }

    public function saveClient()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255|unique:clients,email,' . $this->clientId,
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'pincode' => 'nullable|string|max:10',
            'gstin' => 'nullable|string|max:15',
            'pan' => 'nullable|string|max:10',
            'tin' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:50',
            'ifsc_code' => 'nullable|string|max:11',
            'branch' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        if ($this->isEdit && $this->clientId) {
            // Update
            $client = Client::find($this->clientId);
            if ($client) {
                $client->update([
                    'name' => $this->name,
                    'company' => $this->company,
                    'contact_person' => $this->contact_person,
                    'phone' => $this->phone,
                    'email' => $this->email,
                    'address' => $this->address,
                    'city' => $this->city,
                    'state' => $this->state,
                    'country' => $this->country,
                    'pincode' => $this->pincode,
                    'gstin' => $this->gstin,
                    'pan' => $this->pan,
                    'tin' => $this->tin,
                    'bank_name' => $this->bank_name,
                    'account_number' => $this->account_number,
                    'ifsc_code' => $this->ifsc_code,
                    'branch' => $this->branch,
                    'is_active' => $this->is_active,
                ]);
                $this->success('Client Updated!', 'The client has been updated successfully.');
            }
        } else {
            // Create
            Client::create([
                'name' => $this->name,
                'company' => $this->company,
                'contact_person' => $this->contact_person,
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
                'city' => $this->city,
                'state' => $this->state,
                'country' => $this->country,
                'pincode' => $this->pincode,
                'gstin' => $this->gstin,
                'pan' => $this->pan,
                'tin' => $this->tin,
                'bank_name' => $this->bank_name,
                'account_number' => $this->account_number,
                'ifsc_code' => $this->ifsc_code,
                'branch' => $this->branch,
                'is_active' => $this->is_active,
            ]);
            $this->success('Client Created!', 'The client has been added successfully.');
        }

        $this->reset([
            'name',
            'company',
            'contact_person',
            'phone',
            'email',
            'address',
            'city',
            'state',
            'country',
            'pincode',
            'gstin',
            'pan',
            'tin',
            'bank_name',
            'account_number',
            'ifsc_code',
            'branch',
            'is_active',
            'clientId'
        ]);
        $this->myModal = false;
        $this->isEdit = false;
        $this->dispatch('refreshClients');
        $this->calculateStatistics();
    }

    public function showDetails($id)
    {
        $client = Client::with(['ledger.transactions'])->find($id);

        if ($client) {
            // Create ledger if it doesn't exist
            if (!$client->ledger) {
                $client->ledger()->create([
                    'ledger_name' => $client->name,
                    'ledger_type' => 'client',
                    'opening_balance' => 0,
                    'opening_balance_type' => 'credit',
                    'current_balance' => 0,
                    'is_active' => true,
                ]);

                // Reload the client with the new ledger and transactions
                $client = Client::with(['ledger.transactions'])->find($id);
            }

            $this->selectedClient = $client;
            $this->activeTab = 'details';
            $this->showDetailsModal = true;
        }
    }

    public function confirmDeletion($id)
    {
        $this->confirmingDeleteId = $id;
        $this->showConfirmDeleteModal = true;
    }

    public function deleteClient()
    {
        if ($this->confirmingDeleteId) {
            $client = Client::find($this->confirmingDeleteId);
            if ($client) {
                $client->delete();
                $this->success('Client Deleted!', 'The client has been removed successfully.');
                $this->dispatch('refreshClients');
                $this->calculateStatistics();
            }
            $this->showConfirmDeleteModal = false;
            $this->confirmingDeleteId = null;
        }
    }

    public function cancelDeletion()
    {
        $this->showConfirmDeleteModal = false;
        $this->confirmingDeleteId = null;
    }

    public function toggleStatus()
    {
        foreach ($this->selected as $id) {
            $this->toggleActive($id);
        }
        $this->success('Clients Updated!', count($this->selected) . ' clients status toggled.');
        $this->dispatch('refreshClients');
        $this->reset('selected');
        $this->calculateStatistics();
    }

    public function toggleActive($id)
    {
        $client = Client::find($id);
        if ($client) {
            $client->is_active = !$client->is_active;
            $client->save();
        }
    }

    public function cancel()
    {
        $this->reset([
            'name',
            'company',
            'contact_person',
            'phone',
            'email',
            'address',
            'city',
            'state',
            'country',
            'pincode',
            'gstin',
            'pan',
            'tin',
            'bank_name',
            'account_number',
            'ifsc_code',
            'branch',
            'is_active'
        ]);
        $this->isEdit = false;
        $this->myModal = false;
    }

    public function clearSearch()
    {
        $this->reset('search');
    }


    // Add this computed property for filtered transactions
    public function getFilteredTransactionsProperty()
    {
        if (!$this->selectedClient || !$this->selectedClient->ledger) {
            return collect();
        }

        $query = $this->selectedClient->ledger->transactions()->orderBy('date', 'desc');

        switch ($this->transactionFilter) {
            case '10':
                return $query->take(10)->get();
            case '100':
                return $query->take(100)->get();
            case 'month':
                return $query->where('date', '>=', now()->subMonth())->get();
            case '3month':
                return $query->where('date', '>=', now()->subMonths(3))->get();
            default:
                return $query->take(10)->get();
        }
    }

    // Method to download client ledger PDF
    public function downloadLedgerPdf()
    {
        // Dispatch background job for client ledger
        \App\Jobs\GenerateClientLedgerPdf::dispatch($this->selectedClient->id, Auth::id());

        $this->success(
            'PDF Generation Started!',
            'Your client ledger PDF is being prepared. You will receive a notification when ready.'
        );
    }

    // Method to handle PDF ready notification
    public function handlePdfReady($data)
    {
        $this->success(
            'PDF Ready!',
            'Your client ledger PDF has been generated and is ready for download.'
        );

        $this->dispatch('download-ready', [
            'url' => $data['download_url'],
            'filename' => $data['filename']
        ]);
    }

    // Add transaction method
    public function addTransaction()
    {
        $rules = [
            'newTransaction.date' => 'required|date',
            'newTransaction.type' => 'required|in:sale,payment,return,adjustment',
            'newTransaction.description' => 'required|string|max:255',
            'newTransaction.amount' => 'required|numeric|min:0',
            'newTransaction.reference' => 'nullable|string|max:100',
            'newTransaction.payment_method' => 'required|in:cash,bank',
        ];

        // Add bank account validation only if payment method is bank
        if ($this->newTransaction['payment_method'] === 'bank') {
            $rules['newTransaction.bank_account_id'] = 'required|exists:company_bank_accounts,id';
        }

        $this->validate($rules);

        if ($this->selectedClient && $this->selectedClient->ledger) {
            try {
                DB::transaction(function () {
                    $ledger = $this->selectedClient->ledger;

                    // Determine debit/credit based on transaction type
                    $debitAmount = 0;
                    $creditAmount = 0;

                    switch ($this->newTransaction['type']) {
                        case 'sale':
                            $debitAmount = $this->newTransaction['amount']; // Increase client balance (what they owe)
                            break;
                        case 'payment':
                            $creditAmount = $this->newTransaction['amount']; // Decrease client balance (payment received)

                            // Allocate payment to unpaid invoices (oldest first)
                            $allocationResult = Invoice::allocatePaymentToInvoices(
                                $this->selectedClient->id,
                                $this->newTransaction['amount'],
                                $this->newTransaction['date'],
                                $this->newTransaction['reference']
                            );

                            // Store allocation details for display
                            $allocationSummary = collect($allocationResult['allocations'])
                                ->map(fn($a) => "{$a['invoice_number']}: ₹" . number_format($a['amount_allocated'], 2))
                                ->join(', ');

                            // Update description with allocation details
                            if (!empty($allocationSummary)) {
                                $this->newTransaction['description'] .= " | Allocated to: " . $allocationSummary;
                            }

                            // Handle remaining amount (advance payment/credit)
                            if ($allocationResult['remaining_amount'] > 0) {
                                $this->newTransaction['description'] .= " | Advance: ₹" . number_format($allocationResult['remaining_amount'], 2);
                            }

                            break;
                        case 'return':
                            $creditAmount = $this->newTransaction['amount']; // Decrease client balance
                            break;
                        case 'adjustment':
                            if ($this->newTransaction['amount'] >= 0) {
                                $debitAmount = $this->newTransaction['amount'];
                            } else {
                                $creditAmount = abs($this->newTransaction['amount']);
                            }
                            break;
                    }

                    // Create ledger transaction
                    $ledgerTransaction = $ledger->transactions()->create([
                        'date' => $this->newTransaction['date'],
                        'type' => $this->newTransaction['type'],
                        'description' => $this->newTransaction['description'],
                        'debit_amount' => $debitAmount,
                        'credit_amount' => $creditAmount,
                        'reference' => $this->newTransaction['reference'],
                        'referenceable_type' => null,
                        'referenceable_id' => null,
                    ]);

                    // Update ledger current balance
                    $ledger->current_balance += ($debitAmount - $creditAmount);
                    $ledger->save();

                    // Record bank transaction if payment method is bank
                    if ($this->newTransaction['payment_method'] === 'bank' && $this->newTransaction['bank_account_id']) {
                        $bankAccount = CompanyBankAccount::find($this->newTransaction['bank_account_id']);

                        if ($bankAccount) {
                            // For payment: money comes in (credit to bank)
                            // For sale/return/adjustment: depends on the transaction type
                            $bankTransactionType = ($this->newTransaction['type'] === 'payment') ? 'credit' : 'debit';
                            $bankAmount = $this->newTransaction['amount'];

                            $bankAccount->recordTransaction(
                                $bankTransactionType,
                                $bankAmount,
                                "{$this->newTransaction['type']} transaction for client: {$this->selectedClient->name}",
                                [
                                    'transaction_date' => $this->newTransaction['date'],
                                    'category' => $this->newTransaction['type'],
                                    'reference_number' => $this->newTransaction['reference'],
                                    'transactionable_type' => LedgerTransaction::class,
                                    'transactionable_id' => $ledgerTransaction->id,
                                ]
                            );
                        }
                    }
                });

                // Reset form
                $this->reset('newTransaction');
                $this->newTransaction = [
                    'date' => now()->format('Y-m-d'),
                    'type' => 'sale',
                    'description' => '',
                    'amount' => 0,
                    'reference' => '',
                    'payment_method' => 'cash',
                    'bank_account_id' => null,
                ];

                // Refresh data
                $this->selectedClient = Client::with(['ledger.transactions'])->find($this->selectedClient->id);
                $this->success('Transaction added and payments allocated successfully!');
            } catch (\Exception $e) {
                Log::error('Error adding client transaction: ' . $e->getMessage());
                $this->error('Error adding transaction: ' . $e->getMessage());
            }
        }
    }



    public function getPaymentAllocationPreview()
    {
        if (!$this->selectedClient || $this->newTransaction['type'] !== 'payment') {
            return [];
        }

        $amount = (float)($this->newTransaction['amount'] ?? 0);
        if ($amount <= 0) {
            return [];
        }

        $invoices = Invoice::where('client_id', $this->selectedClient->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('balance_amount', '>', 0)
            ->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $remainingAmount = $amount;
        $preview = [];

        foreach ($invoices as $invoice) {
            if ($remainingAmount <= 0) break;

            $allocateAmount = min($remainingAmount, $invoice->balance_amount);
            $preview[] = [
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date->format('d M Y'),
                'balance_before' => $invoice->balance_amount,
                'will_allocate' => $allocateAmount,
                'balance_after' => $invoice->balance_amount - $allocateAmount,
            ];

            $remainingAmount -= $allocateAmount;
        }

        return [
            'allocations' => $preview,
            'total_allocated' => $amount - $remainingAmount,
            'remaining' => $remainingAmount,
        ];
    }



    public function render()
    {
        $clients = Client::query()
            // Search functionality
            ->when($this->search, function ($query) {
                return $query->where(function ($subQuery) {
                    $subQuery->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('company', 'like', '%' . $this->search . '%')
                        ->orWhere('contact_person', 'like', '%' . $this->search . '%')
                        ->orWhere('phone', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhere('city', 'like', '%' . $this->search . '%')
                        ->orWhere('gstin', 'like', '%' . $this->search . '%');
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
            // Location filter
            ->when(!empty($this->appliedLocationFilter), function ($query) {
                return $query->whereIn('city', $this->appliedLocationFilter);
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
            ['label' => 'Sl No.', 'key' => 'sl_no', 'sortable' => false],
            ['label' => 'Name', 'key' => 'name', 'sortable' => true],
            ['label' => 'Company', 'key' => 'company', 'sortable' => true],
            ['label' => 'Contact Person', 'key' => 'contact_person', 'sortable' => false],
            ['label' => 'Phone', 'key' => 'phone', 'sortable' => false],
            ['label' => 'Status', 'key' => 'is_active', 'sortable' => false],
            ['label' => 'Actions', 'key' => 'actions', 'type' => 'button', 'sortable' => false],
        ];

        $row_decoration = [
            'bg-warning/20' => fn($client) => !$client->is_active,
            'text-error' => fn($client) => $client->is_active === 0,
        ];

        return view('livewire.client-management', [
            'clients' => $clients,
            'headers' => $headers,
            'row_decoration' => $row_decoration,
            'selectedClient' => $this->selectedClient,
            'activeTab' => $this->activeTab,
            'bankAccounts' => CompanyBankAccount::active()->get(),
        ]);
    }
}
