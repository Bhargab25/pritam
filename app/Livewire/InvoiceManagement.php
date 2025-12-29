<?php
// app/Livewire/InvoiceManagement.php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Client;
use App\Models\StockMovement;
use App\Models\LedgerTransaction;
use App\Models\MonthlyBill;
use App\Models\BankTransaction;
use App\Services\InvoicePdfService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\InvoicePayment;
use Livewire\Attributes\Computed;
use App\Models\CompanyBankAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\AccountLedger;

class InvoiceManagement extends Component
{
    use WithPagination, Toast;

    // Tab management
    public $activeTab = 'invoices';

    // Invoice form properties
    public $showInvoiceModal = false;
    public $editingInvoice = null;
    public $invoiceType = 'cash';
    public $invoiceDate;
    public $dueDate;
    public $clientId = '';
    public $clientName = '';
    public $clientPhone = '';
    public $clientAddress = '';
    public $isGstInvoice = false;
    public $clientGstin = '';
    public $placeOfSupply = '';
    public $gstType = 'cgst_sgst';
    public $notes = '';
    public $termsConditions = '';

    // Invoice items
    public $invoiceItems = [];

    // Monthly billing
    public $showMonthlyBillModal = false;
    public $selectedClient = null;
    public $monthlyBillPeriodFrom;
    public $monthlyBillPeriodTo;
    public $unbilledInvoices = [];
    public $selectedInvoicesForBilling = [];

    // Payment modal
    public $showPaymentModal = false;
    public $paymentInvoice = null;
    public $paymentAmount = '';
    public $paymentMethod = 'cash';
    public $paymentReference = '';
    public $paymentNotes = '';

    // Filters and search
    public $search = '';
    public $statusFilter = '';
    public $typeFilter = '';
    public $clientFilter = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $perPage = 15;
    public $showAllFinancialYear = false;

    // Statistics
    public $totalInvoices = 0;
    public $totalAmount = 0;
    public $paidAmount = 0;
    public $pendingAmount = 0;

    // Add these properties to the class
    public $showViewModal = false;
    public $viewingInvoice = null;

    public $coolieExpense = 0;
    public $invoicePaymentMethod = 'cash';  // Renamed to avoid conflict
    public $invoiceBankAccountId = null;

    // Also add method to view monthly bills
    // public $showMonthlyBillsModal = false;
    // public $monthlyBills = [];

    protected $rules = [
        'invoiceType' => 'required|in:cash,client',
        'invoiceDate' => 'required|date',
        'dueDate' => 'nullable|date|after_or_equal:invoiceDate',
        'clientId' => 'required_if:invoiceType,client|nullable|exists:clients,id',
        'clientName' => 'required_if:invoiceType,cash|nullable|string|max:255',
        'clientPhone' => 'nullable|string|max:20',
        'clientAddress' => 'nullable|string',
        'isGstInvoice' => 'boolean',
        'clientGstin' => 'nullable|string|size:15',
        'placeOfSupply' => 'nullable|string|max:100',
        'gstType' => 'nullable|in:cgst_sgst,igst',

        // Add these validation rules
        'coolieExpense' => 'nullable|numeric|min:0',
        'invoicePaymentMethod' => 'required_if:invoiceType,cash|in:cash,bank',
        'invoiceBankAccountId' => 'required_if:invoicePaymentMethod,bank|nullable|exists:company_bank_accounts,id',

        'invoiceItems.*.product_id' => 'required|exists:products,id',
        'invoiceItems.*.quantity' => 'required|numeric|min:0.01',
        'invoiceItems.*.unit_price' => 'required|numeric|min:0',
        'invoiceItems.*.invoice_unit' => 'nullable|string',
        'invoiceItems.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
        'invoiceItems.*.cgst_rate' => 'nullable|numeric|min:0|max:50',
        'invoiceItems.*.sgst_rate' => 'nullable|numeric|min:0|max:50',
        'invoiceItems.*.igst_rate' => 'nullable|numeric|min:0|max:50',
    ];

    public function updatedShowAllFinancialYear($value)
    {
        if ($value) {
            $today = now();

            $financialYearStart = $today->month < 4
                ? $today->copy()->subYear()->startOfYear()->addMonths(3)  // 1 Apr last FY
                : $today->copy()->startOfYear()->addMonths(3);            // 1 Apr this FY

            $this->dateFrom = $financialYearStart->format('Y-m-d');
            $this->dateTo   = $today->format('Y-m-d');
        }
    }

    public function mount()
    {
        $this->invoiceDate = now()->format('Y-m-d');
        $this->dueDate = now()->addDays(30)->format('Y-m-d');
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->calculateStats();
        $this->resetInvoiceItems();
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function openInvoiceModal($type = 'cash')
    {
        $this->invoiceType = $type;
        $this->showInvoiceModal = true;
        $this->resetInvoiceForm();
    }

    public function closeInvoiceModal()
    {
        $this->showInvoiceModal = false;
        $this->editingInvoice = null;
        $this->resetValidation();
        $this->resetInvoiceForm();
    }

    public function resetInvoiceForm()
    {
        $this->invoiceDate = now()->format('Y-m-d');
        $this->dueDate = $this->invoiceType === 'client' ? now()->addDays(30)->format('Y-m-d') : null;
        $this->clientId = '';
        $this->clientName = '';
        $this->clientPhone = '';
        $this->clientAddress = '';
        $this->isGstInvoice = false;
        $this->clientGstin = '';
        $this->placeOfSupply = '';
        $this->gstType = 'cgst_sgst';
        $this->notes = '';
        $this->termsConditions = 'Payment due within 30 days. Late payments subject to 1.5% monthly service charge.';

        // Add these lines
        $this->coolieExpense = 0;
        $this->invoicePaymentMethod = 'cash';
        $this->invoiceBankAccountId = null;

        $this->resetInvoiceItems();
    }

    public function resetInvoiceItems()
    {
        $this->invoiceItems = [
            [
                'product_id' => '',
                'quantity' => '',
                'unit_price' => '',
                'invoice_unit' => '',
                'discount_percentage' => 0,
                'cgst_rate' => 0,
                'sgst_rate' => 0,
                'igst_rate' => 0,
            ]
        ];
    }

    public function addInvoiceItem()
    {
        $this->invoiceItems[] = [
            'product_id' => '',
            'quantity' => 0,
            'unit_price' => 0,
            'invoice_unit' => '',
            'discount_percentage' => 0,
            'cgst_rate' => 0,
            'sgst_rate' => 0,
            'igst_rate' => 0,
        ];
    }

    public function removeInvoiceItem($index)
    {
        if (count($this->invoiceItems) > 1) {
            unset($this->invoiceItems[$index]);
            $this->invoiceItems = array_values($this->invoiceItems);
        }
    }

    #[Computed]
    public function invoiceFinalTotal()
    {
        return $this->invoiceGrandTotal + (float)($this->coolieExpense ?? 0);
    }

    public function updatedClientId()
    {
        if ($this->clientId) {
            $client = Client::find($this->clientId);
            if ($client) {
                $this->clientGstin = $client->gstin;
                $this->clientPhone = $client->phone;
                $this->clientAddress = $client->address . ', ' . $client->city . ', ' . $client->state;
            }
        }
    }

    public function updatedInvoiceItems($value, $name)
    {
        // Auto-populate product details when product is selected
        $pathParts = explode('.', $name);
        if (count($pathParts) === 3 && $pathParts[2] === 'product_id') {
            $index = $pathParts[1];
            $productId = $value;

            if ($productId) {
                $product = Product::find($productId);
                if ($product) {
                    $this->invoiceItems[$index]['unit_price'] = $product->unit_price ?? 0;
                    // Set default GST rates based on product category or settings
                    if ($this->isGstInvoice) {
                        $this->invoiceItems[$index]['cgst_rate'] = 9; // Default 9%
                        $this->invoiceItems[$index]['sgst_rate'] = 9; // Default 9%
                        $this->invoiceItems[$index]['igst_rate'] = 0;
                    }
                }
            }
        }
    }


    #[Computed]
    public function invoiceSubtotal()
    {
        return collect($this->invoiceItems)->sum(function ($item) {
            $qty = (float)($item['quantity'] ?? 0);
            $price = (float)($item['unit_price'] ?? 0);
            $discount = (float)($item['discount_percentage'] ?? 0);

            $lineTotal = $qty * $price;
            $discountAmount = ($lineTotal * $discount) / 100;
            return $lineTotal - $discountAmount;
        });
    }

    #[Computed]
    public function invoiceCgst()
    {
        return collect($this->invoiceItems)->sum(function ($item) {
            $qty = (float)($item['quantity'] ?? 0);
            $price = (float)($item['unit_price'] ?? 0);
            $discount = (float)($item['discount_percentage'] ?? 0);
            $cgst = (float)($item['cgst_rate'] ?? 0);

            $lineTotal = $qty * $price;
            $discountAmount = ($lineTotal * $discount) / 100;
            $taxableAmount = $lineTotal - $discountAmount;
            return ($taxableAmount * $cgst) / 100;
        });
    }

    #[Computed]
    public function invoiceSgst()
    {
        return collect($this->invoiceItems)->sum(function ($item) {
            $qty = (float)($item['quantity'] ?? 0);
            $price = (float)($item['unit_price'] ?? 0);
            $discount = (float)($item['discount_percentage'] ?? 0);
            $sgst = (float)($item['sgst_rate'] ?? 0);

            $lineTotal = $qty * $price;
            $discountAmount = ($lineTotal * $discount) / 100;
            $taxableAmount = $lineTotal - $discountAmount;
            return ($taxableAmount * $sgst) / 100;
        });
    }

    #[Computed]
    public function invoiceIgst()
    {
        return collect($this->invoiceItems)->sum(function ($item) {
            $qty = (float)($item['quantity'] ?? 0);
            $price = (float)($item['unit_price'] ?? 0);
            $discount = (float)($item['discount_percentage'] ?? 0);
            $igst = (float)($item['igst_rate'] ?? 0);

            $lineTotal = $qty * $price;
            $discountAmount = ($lineTotal * $discount) / 100;
            $taxableAmount = $lineTotal - $discountAmount;
            return ($taxableAmount * $igst) / 100;
        });
    }

    #[Computed]
    public function invoiceTotalTax()
    {
        return $this->invoiceCgst + $this->invoiceSgst + $this->invoiceIgst;
    }

    #[Computed]
    public function invoiceGrandTotal()
    {
        return $this->invoiceSubtotal + $this->invoiceTotalTax;
    }

    public $newTransaction = [
        'date' => '',
        'type' => 'purchase',
        'description' => '',
        'amount' => 0,
        'reference' => '',
        'payment_method' => 'cash',
        'bank_account_id' => null,
    ];

    // public function saveInvoice()
    // {
    //     $this->validate();

    //     // Additional validation
    //     if (empty($this->invoiceItems) || !$this->invoiceItems[0]['product_id']) {
    //         $this->error('At least one product is required');
    //         return;
    //     }

    //     try {
    //         DB::transaction(function () {
    //             // Create invoice
    //             $invoice = Invoice::create([
    //                 'invoice_type' => $this->invoiceType,
    //                 'invoice_date' => $this->invoiceDate,
    //                 'due_date' => $this->dueDate,
    //                 'client_id' => $this->invoiceType === 'client' ? $this->clientId : null,
    //                 'client_name' => $this->invoiceType === 'cash' ? $this->clientName : null,
    //                 'client_phone' => $this->clientPhone,
    //                 'client_address' => $this->clientAddress,
    //                 'is_gst_invoice' => $this->isGstInvoice,
    //                 'client_gstin' => $this->clientGstin,
    //                 'place_of_supply' => $this->placeOfSupply,
    //                 'gst_type' => $this->gstType,
    //                 'notes' => $this->notes,
    //                 'terms_conditions' => $this->termsConditions,
    //                 'created_by' => auth()->user()->name,
    //             ]);

    //             $subtotal = 0;
    //             $totalTax = 0;

    //             // Create invoice items
    //             foreach ($this->invoiceItems as $itemData) {
    //                 if (empty($itemData['product_id']) || empty($itemData['quantity'])) continue;

    //                 $product = Product::find($itemData['product_id']);

    //                 // Check stock availability
    //                 if ($product->stock_quantity < $itemData['quantity']) {
    //                     throw new \Exception("Insufficient stock for product: {$product->name}");
    //                 }

    //                 // Calculate amounts
    //                 $unitPrice = $itemData['unit_price'];
    //                 $quantity = $itemData['quantity'];
    //                 $discountPercent = $itemData['discount_percentage'] ?? 0;

    //                 $lineTotal = $unitPrice * $quantity;
    //                 $discountAmount = ($lineTotal * $discountPercent) / 100;
    //                 $taxableAmount = $lineTotal - $discountAmount;

    //                 // Calculate GST
    //                 $cgstAmount = ($taxableAmount * ($itemData['cgst_rate'] ?? 0)) / 100;
    //                 $sgstAmount = ($taxableAmount * ($itemData['sgst_rate'] ?? 0)) / 100;
    //                 $igstAmount = ($taxableAmount * ($itemData['igst_rate'] ?? 0)) / 100;
    //                 $totalItemAmount = $taxableAmount + $cgstAmount + $sgstAmount + $igstAmount;

    //                 // Create invoice item
    //                 InvoiceItem::create([
    //                     'invoice_id' => $invoice->id,
    //                     'product_id' => $product->id,
    //                     'product_name' => $product->name,
    //                     'product_unit' => $product->unit,
    //                     'invoice_unit' => $itemData['invoice_unit'],
    //                     'unit_conversion_factor' => 1, // Can be extended for unit conversions
    //                     'quantity' => $quantity,
    //                     'unit_price' => $unitPrice,
    //                     'discount_percentage' => $discountPercent,
    //                     'discount_amount' => $discountAmount,
    //                     'taxable_amount' => $taxableAmount,
    //                     'cgst_rate' => $itemData['cgst_rate'] ?? 0,
    //                     'sgst_rate' => $itemData['sgst_rate'] ?? 0,
    //                     'igst_rate' => $itemData['igst_rate'] ?? 0,
    //                     'cgst_amount' => $cgstAmount,
    //                     'sgst_amount' => $sgstAmount,
    //                     'igst_amount' => $igstAmount,
    //                     'total_amount' => $totalItemAmount,
    //                 ]);

    //                 // Update product stock
    //                 $product->stock_quantity -= $quantity;
    //                 $product->save();

    //                 // Create stock movement
    //                 StockMovement::create([
    //                     'product_id' => $product->id,
    //                     'type' => 'out',
    //                     'quantity' => $quantity,
    //                     'reason' => 'sale',
    //                     'reference_type' => Invoice::class,
    //                     'reference_id' => $invoice->id,
    //                 ]);

    //                 $subtotal += $taxableAmount;
    //                 $totalTax += ($cgstAmount + $sgstAmount + $igstAmount);
    //             }

    //             // Update invoice totals
    //             $invoice->update([
    //                 'subtotal' => $subtotal,
    //                 'cgst_amount' => $invoice->items->sum('cgst_amount'),
    //                 'sgst_amount' => $invoice->items->sum('sgst_amount'),
    //                 'igst_amount' => $invoice->items->sum('igst_amount'),
    //                 'total_tax' => $totalTax,
    //                 'total_amount' => $subtotal + $totalTax,
    //                 'balance_amount' => $subtotal + $totalTax,
    //             ]);

    //             // Create ledger entries for client invoices
    //             if ($this->invoiceType === 'client' && $this->clientId) {
    //                 $client = Client::find($this->clientId);
    //                 if ($client->ledger) {
    //                     LedgerTransaction::create([
    //                         'ledger_id' => $client->ledger->id,
    //                         'date' => $this->invoiceDate,
    //                         'type' => 'sale',
    //                         'description' => "Sales invoice - {$invoice->invoice_number}",
    //                         'debit_amount' => $invoice->total_amount,
    //                         'credit_amount' => 0,
    //                         'reference' => $invoice->invoice_number,
    //                     ]);
    //                 }
    //             }

    //             $this->success('Invoice created successfully!');
    //             $this->closeInvoiceModal();
    //             $this->calculateStats();

    //             // Generate PDF for client invoices
    //             if ($this->invoiceType === 'client') {
    //                 $this->dispatch('invoice-created', ['invoiceId' => $invoice->id]);
    //             }
    //         });
    //     } catch (\Exception $e) {
    //         Log::error('Error creating invoice: ' . $e->getMessage());
    //         $this->error('Error creating invoice: ' . $e->getMessage());
    //     }
    // }

    public function editInvoice($invoiceId)
    {
        try {
            $invoice = Invoice::with(['items', 'client', 'payments'])->find($invoiceId);

            if (!$invoice) {
                $this->error('Invoice not found');
                return;
            }

            // Load all invoice data into form
            $this->editingInvoice = $invoice->id;
            $this->invoiceType = $invoice->invoice_type;
            $this->invoiceDate = $invoice->invoice_date->format('Y-m-d');
            $this->dueDate = $invoice->due_date ? $invoice->due_date->format('Y-m-d') : null;
            $this->clientId = $invoice->client_id;
            $this->clientName = $invoice->client_name;
            $this->clientPhone = $invoice->client_phone;
            $this->clientAddress = $invoice->client_address;
            $this->isGstInvoice = $invoice->is_gst_invoice;
            $this->clientGstin = $invoice->client_gstin;
            $this->placeOfSupply = $invoice->place_of_supply;
            $this->gstType = $invoice->gst_type ?? 'cgst+sgst';
            $this->notes = $invoice->notes;
            $this->termsConditions = $invoice->terms_conditions;
            $this->coolieExpense = $invoice->coolie_expense ?? 0;
            $this->invoicePaymentMethod = $invoice->payment_method ?? 'cash';
            $this->invoiceBankAccountId = $invoice->bank_account_id;

            // Load invoice items
            $this->invoiceItems = [];
            foreach ($invoice->items as $item) {
                $this->invoiceItems[] = [
                    'productid' => $item->product_id,
                    'productname' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unitprice' => $item->unit_price,
                    'invoiceunit' => $item->invoice_unit,
                    'discountpercentage' => $item->discount_percentage ?? 0,
                    'cgstrate' => $item->cgst_rate ?? 0,
                    'sgstrate' => $item->sgst_rate ?? 0,
                    'igstrate' => $item->igst_rate ?? 0,
                ];
            }

            $this->showInvoiceModal = true;

            // Warn if there are payments
            if ($invoice->paidamount > 0) {
                $this->warning('This invoice has payments of ₹' . number_format($invoice->paidamount, 2) . '. Please review carefully.');
            }
        } catch (\Exception $e) {
            Log::error('Error loading invoice for edit: ' . $e->getMessage());
            $this->error('Error loading invoice: ' . $e->getMessage());
        }
    }

    // Modify existing saveInvoice
    public function saveInvoice()
    {
        $this->validate();

        if (empty($this->invoiceItems) || !$this->invoiceItems[0]['productid']) {
            $this->error('At least one product is required');
            return;
        }

        try {
            if ($this->editingInvoice) {
                $this->updateInvoice();
            } else {
                $this->createNewInvoice();
            }
        } catch (\Exception $e) {
            Log::error('Error saving invoice: ' . $e->getMessage());
            $this->error('Error saving invoice: ' . $e->getMessage());
        }
    }

    // Extract existing invoice creation into this method
    private function createNewInvoice()
    {
        DB::transaction(function () {
            // Create invoice
            $invoice = Invoice::create([
                'invoice_number' => Invoice::generateInvoiceNumber($this->invoiceType),
                'invoice_type' => $this->invoiceType,
                'invoice_date' => $this->invoiceDate,
                'due_date' => $this->dueDate,
                'client_id' => $this->invoiceType === 'client' ? $this->clientId : null,
                'client_name' => $this->invoiceType === 'cash' ? $this->clientName : null,
                'client_phone' => $this->clientPhone,
                'client_address' => $this->clientAddress,
                'is_gst_invoice' => $this->isGstInvoice,
                'client_gstin' => $this->clientGstin,
                'place_of_supply' => $this->placeOfSupply,
                'gst_type' => $this->gstType,
                'notes' => $this->notes,
                'terms_conditions' => $this->termsConditions,
                'created_by' => auth()->user()->name,
                'coolie_expense' => $this->coolieExpense ?? 0,
                'payment_method' => $this->invoiceType === 'cash' ? $this->paymentMethod : null,
                'bank_account_id' => $this->invoiceType === 'cash' && $this->paymentMethod === 'bank' ? $this->bankAccountId : null,
            ]);

            $subtotal = 0;
            $totalTax = 0;

            // Create invoice items and update stock
            foreach ($this->invoiceItems as $itemData) {
                if (empty($itemData['product_id']) || empty($itemData['quantity'])) continue;

                $product = Product::find($itemData['product_id']);

                // Check stock availability
                if ($product->stock_quantity < $itemData['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                // Calculate amounts
                $unitPrice = $itemData['unit_price'];
                $quantity = $itemData['quantity'];
                $discountPercent = $itemData['discount_percentage'] ?? 0;

                $lineTotal = $unitPrice * $quantity;
                $discountAmount = ($lineTotal * $discountPercent) / 100;
                $taxableAmount = $lineTotal - $discountAmount;

                // Calculate GST
                $cgstAmount = ($taxableAmount * ($itemData['cgst_rate'] ?? 0)) / 100;
                $sgstAmount = ($taxableAmount * ($itemData['sgst_rate'] ?? 0)) / 100;
                $igstAmount = ($taxableAmount * ($itemData['igst_rate'] ?? 0)) / 100;
                $totalItemAmount = $taxableAmount + $cgstAmount + $sgstAmount + $igstAmount;

                // Create invoice item
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_unit' => $product->unit,
                    'invoice_unit' => $itemData['invoice_unit'],
                    'unit_conversion_factor' => 1,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percentage' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'taxable_amount' => $taxableAmount,
                    'cgst_rate' => $itemData['cgst_rate'] ?? 0,
                    'sgst_rate' => $itemData['sgst_rate'] ?? 0,
                    'igst_rate' => $itemData['igst_rate'] ?? 0,
                    'cgst_amount' => $cgstAmount,
                    'sgst_amount' => $sgstAmount,
                    'igst_amount' => $igstAmount,
                    'total_amount' => $totalItemAmount,
                ]);

                // Update product stock
                $product->stock_quantity -= $quantity;
                $product->save();

                // Create stock movement
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'out',
                    'quantity' => $quantity,
                    'reason' => 'sale',
                    'reference_type' => Invoice::class,
                    'reference_id' => $invoice->id,
                ]);

                $subtotal += $taxableAmount;
                $totalTax += ($cgstAmount + $sgstAmount + $igstAmount);
            }

            $totalAmount = $subtotal + $totalTax;
            $finalAmount = $totalAmount + ($this->coolieExpense ?? 0);

            // Update invoice totals
            $invoice->update([
                'subtotal' => $subtotal,
                'cgst_amount' => $invoice->items->sum('cgst_amount'),
                'sgst_amount' => $invoice->items->sum('sgst_amount'),
                'igst_amount' => $invoice->items->sum('igst_amount'),
                'total_tax' => $totalTax,
                'total_amount' => $totalAmount,
                'final_amount' => $finalAmount,
                'balance_amount' => $finalAmount,
            ]);

            // Get cash ledger
            $cashLedger = AccountLedger::firstOrCreate(
                ['ledger_type' => 'cash', 'ledger_name' => 'Cash in Hand'],
                [
                    'opening_balance' => 0,
                    'opening_balance_type' => 'debit',
                    'current_balance' => 0,
                    'is_active' => true,
                ]
            );

            // Handle payment recording based on invoice type
            if ($this->invoiceType === 'cash') {
                // Cash invoice - record immediate payment
                if ($this->paymentMethod === 'cash') {
                    // Add full amount (including coolie) to cash ledger
                    $cashLedger->transactions()->create([
                        'date' => $this->invoiceDate,
                        'type' => 'sale',
                        'description' => "Cash sale - Invoice {$invoice->invoice_number}",
                        'debit_amount' => $finalAmount,
                        'credit_amount' => 0,
                        'reference' => $invoice->invoice_number,
                        'referenceable_type' => Invoice::class,
                        'referenceable_id' => $invoice->id,
                    ]);

                    $cashLedger->current_balance += $finalAmount;
                    $cashLedger->save();
                } elseif ($this->paymentMethod === 'bank') {
                    // Add invoice amount to bank account
                    $bankAccount = CompanyBankAccount::find($this->bankAccountId);
                    if ($bankAccount) {
                        $bankAccount->recordTransaction(
                            'credit',
                            $finalAmount,
                            "Invoice payment - {$invoice->invoice_number}",
                            [
                                'transaction_date' => $this->invoiceDate,
                                'category' => 'sales',
                                'reference_number' => $invoice->invoice_number,
                                'transactionable_type' => Invoice::class,
                                'transactionable_id' => $invoice->id,
                            ]
                        );
                    }
                }

                // Mark cash invoice as paid
                $invoice->update([
                    'paid_amount' => $finalAmount,
                    'balance_amount' => 0,
                    'payment_status' => 'paid',
                ]);
            } elseif ($this->invoiceType === 'client') {
                // Client invoice - create ledger entry (unpaid)
                $client = Client::find($this->clientId);
                if ($client) {
                    // Ensure client has a ledger
                    if (!$client->ledger) {
                        $client->ledger()->create([
                            'ledger_name' => $client->name,
                            'ledger_type' => 'client',
                            'opening_balance' => 0,
                            'opening_balance_type' => 'debit',
                            'current_balance' => 0,
                            'is_active' => true,
                        ]);
                        $client->refresh();
                    }

                    // Create debit entry (client owes money)
                    $client->ledger->transactions()->create([
                        'date' => $this->invoiceDate,
                        'type' => 'sale',
                        'description' => "Sales invoice - {$invoice->invoice_number}",
                        'debit_amount' => $finalAmount,
                        'credit_amount' => 0,
                        'reference' => $invoice->invoice_number,
                        'referenceable_type' => Invoice::class,
                        'referenceable_id' => $invoice->id,
                    ]);

                    // Update client ledger balance
                    $client->ledger->current_balance += $finalAmount;
                    $client->ledger->save();
                }
            }
            // Record and deduct coolie expense from cash
            if ($this->coolieExpense > 0) {
                $this->recordCoolieExpense($invoice, $cashLedger);
            }

            $this->success('Invoice created successfully!');
            $this->closeInvoiceModal();
            $this->calculateStats();

            // Generate PDF for client invoices
            if ($this->invoiceType === 'client') {
                $this->dispatch('invoice-created', ['invoiceId' => $invoice->id]);
            }
        });
    }

    // MAIN UPDATE METHOD - Handles ALL payment scenarios
    private function updateInvoice()
    {
        DB::transaction(function () {
            $invoice = Invoice::with(['items', 'client', 'payments'])->findOrFail($this->editingInvoice);

            // Calculate amounts
            $newFinalAmount = $this->invoiceGrandTotal + ($this->coolieExpense ?? 0);
            $oldFinalAmount = $invoice->finalamount;
            $paidAmount = $invoice->paidamount;

            Log::info("Invoice Edit: Old={$oldFinalAmount}, New={$newFinalAmount}, Paid={$paidAmount}");

            // STEP 1: Reverse all old transactions
            $this->reverseInvoiceTransactions($invoice);

            // STEP 2: Update invoice header
            $invoice->update([
                'invoicedate' => $this->invoiceDate,
                'duedate' => $this->dueDate,
                'clientid' => $this->invoiceType === 'client' ? $this->clientId : null,
                'clientname' => $this->invoiceType === 'cash' ? $this->clientName : null,
                'clientphone' => $this->clientPhone,
                'clientaddress' => $this->clientAddress,
                'isgstinvoice' => $this->isGstInvoice,
                'clientgstin' => $this->clientGstin,
                'placeofsupply' => $this->placeOfSupply,
                'gsttype' => $this->gstType,
                'notes' => $this->notes,
                'termsconditions' => $this->termsConditions,
                'coolieexpense' => $this->coolieExpense ?? 0,
                'paymentmethod' => $this->invoiceType === 'cash' ? $this->invoicePaymentMethod : null,
                'bankaccountid' => ($this->invoiceType === 'cash' && $this->invoicePaymentMethod === 'bank')
                    ? $this->invoiceBankAccountId : null,
            ]);

            // STEP 3: Delete old items
            $invoice->items()->delete();

            // STEP 4: Create new items (same calculation logic as create)
            $subtotal = 0;
            $totalTax = 0;

            foreach ($this->invoiceItems as $itemData) {
                if (empty($itemData['productid']) || empty($itemData['quantity'])) continue;

                $product = Product::find($itemData['productid']);

                if ($product->stockquantity < $itemData['quantity']) {
                    throw new \Exception('Insufficient stock for ' . $product->name);
                }

                $unitPrice = $itemData['unitprice'];
                $quantity = $itemData['quantity'];
                $discountPercent = $itemData['discountpercentage'] ?? 0;

                $lineTotal = $unitPrice * $quantity;
                $discountAmount = $lineTotal * ($discountPercent / 100);
                $taxableAmount = $lineTotal - $discountAmount;

                $cgstAmount = $taxableAmount * (($itemData['cgstrate'] ?? 0) / 100);
                $sgstAmount = $taxableAmount * (($itemData['sgstrate'] ?? 0) / 100);
                $igstAmount = $taxableAmount * (($itemData['igstrate'] ?? 0) / 100);
                $totalItemAmount = $taxableAmount + $cgstAmount + $sgstAmount + $igstAmount;

                InvoiceItem::create([
                    'invoiceid' => $invoice->id,
                    'productid' => $product->id,
                    'productname' => $product->name,
                    'productunit' => $product->unit,
                    'invoiceunit' => $itemData['invoiceunit'],
                    'unitconversionfactor' => 1,
                    'quantity' => $quantity,
                    'unitprice' => $unitPrice,
                    'discountpercentage' => $discountPercent,
                    'discountamount' => $discountAmount,
                    'taxableamount' => $taxableAmount,
                    'cgstrate' => $itemData['cgstrate'] ?? 0,
                    'sgstrate' => $itemData['sgstrate'] ?? 0,
                    'igstrate' => $itemData['igstrate'] ?? 0,
                    'cgstamount' => $cgstAmount,
                    'sgstamount' => $sgstAmount,
                    'igstamount' => $igstAmount,
                    'totalamount' => $totalItemAmount,
                ]);

                $product->stockquantity -= $quantity;
                $product->save();

                StockMovement::create([
                    'productid' => $product->id,
                    'type' => 'out',
                    'quantity' => $quantity,
                    'reason' => 'sale',
                    'referencetype' => Invoice::class,
                    'referenceid' => $invoice->id,
                ]);

                $subtotal += $taxableAmount;
                $totalTax += $cgstAmount + $sgstAmount + $igstAmount;
            }

            // STEP 5: Calculate totals with CRITICAL payment logic
            $totalAmount = $subtotal + $totalTax;
            $finalAmount = $totalAmount + ($this->coolieExpense ?? 0);

            // *** KEY: Calculate balance based on existing payments ***
            $newBalanceAmount = $finalAmount - $paidAmount;

            // Determine payment status
            if ($paidAmount <= 0) {
                $paymentStatus = 'unpaid';
            } elseif ($newBalanceAmount <= 0) {
                $paymentStatus = 'paid';
                $newBalanceAmount = 0; // Set exactly to 0 when overpaid
            } else {
                $paymentStatus = 'partial';
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'cgstamount' => $invoice->items()->sum('cgstamount'),
                'sgstamount' => $invoice->items()->sum('sgstamount'),
                'igstamount' => $invoice->items()->sum('igstamount'),
                'totaltax' => $totalTax,
                'totalamount' => $totalAmount,
                'finalamount' => $finalAmount,
                'balanceamount' => $newBalanceAmount,
                'paymentstatus' => $paymentStatus,
            ]);

            // STEP 6: Recreate ledger entries
            $this->recreateInvoiceLedgerEntries($invoice);

            // STEP 7: Handle excess payment (when new amount < paid amount)
            if ($paidAmount > $finalAmount && $invoice->invoicetype === 'client') {
                $this->handleExcessPayment($invoice, $paidAmount - $finalAmount);
            }

            Log::info("Invoice {$invoice->invoicenumber} updated successfully");

            $message = 'Invoice updated successfully!';
            if ($paidAmount > $finalAmount) {
                $excess = $paidAmount - $finalAmount;
                $message .= ' Excess payment of ₹' . number_format($excess, 2) . ' kept as advance credit.';
            }

            $this->success($message);
            $this->closeInvoiceModal();
            $this->calculateStats();
        });
    }

    // Handle excess payment - creates advance credit
    private function handleExcessPayment($invoice, $excessAmount)
    {
        if ($excessAmount <= 0) return;

        $client = Client::with('ledger')->find($invoice->clientid);
        if (!$client || !$client->ledger) return;

        // Create adjustment entry - customer has advance credit
        $client->ledger->transactions()->create([
            'date' => now(),
            'type' => 'adjustment',
            'description' => "Excess payment adjustment for invoice {$invoice->invoicenumber} edit - Customer advance credit",
            'debitamount' => 0,
            'creditamount' => $excessAmount,
            'reference' => "ADJ-{$invoice->invoicenumber}",
            'referenceabletype' => Invoice::class,
            'referenceableid' => $invoice->id,
        ]);

        // Reduce client's outstanding
        $client->ledger->currentbalance -= $excessAmount;
        $client->ledger->save();

        Log::info("Created advance credit of ₹{$excessAmount} for client {$client->name}");
    }

    // Reverse all old transactions
    private function reverseInvoiceTransactions($invoice)
    {
        // 1. Reverse stock
        foreach ($invoice->items as $item) {
            $product = $item->product;
            if ($product) {
                $product->stockquantity += $item->quantity;
                $product->save();

                StockMovement::create([
                    'productid' => $product->id,
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'reason' => 'invoice_edit_reversal',
                    'referencetype' => Invoice::class,
                    'referenceid' => $invoice->id,
                ]);
            }
        }

        // 2. Reverse client ledger (only sale entry, not payments)
        if ($invoice->clientid && $invoice->client && $invoice->client->ledger) {
            $ledger = $invoice->client->ledger;

            $invoiceTransactions = LedgerTransaction::where('referenceabletype', Invoice::class)
                ->where('referenceableid', $invoice->id)
                ->where('type', 'sale') // Only reverse sale, not payments
                ->get();

            foreach ($invoiceTransactions as $transaction) {
                $ledger->currentbalance -= $transaction->debitamount;
                $ledger->currentbalance += $transaction->creditamount;
                $transaction->delete();
            }

            $ledger->save();
        }

        // 3. Reverse cash ledger
        if ($invoice->invoicetype === 'cash') {
            $cashLedger = AccountLedger::where('ledgertype', 'cash')
                ->where('ledgername', 'Cash in Hand')
                ->first();

            if ($cashLedger) {
                $cashTransactions = LedgerTransaction::where('referenceabletype', Invoice::class)
                    ->where('referenceableid', $invoice->id)
                    ->get();

                foreach ($cashTransactions as $transaction) {
                    $cashLedger->currentbalance -= $transaction->debitamount;
                    $cashLedger->currentbalance += $transaction->creditamount;
                    $transaction->delete();
                }

                $cashLedger->save();
            }
        }

        // 4. Reverse bank transactions
        if ($invoice->invoicetype === 'cash' && $invoice->paymentmethod === 'bank' && $invoice->bankaccountid) {
            $bankAccount = CompanyBankAccount::find($invoice->bankaccountid);
            if ($bankAccount) {
                $bankTransactions = BankTransaction::where('transactionabletype', Invoice::class)
                    ->where('transactionableid', $invoice->id)
                    ->get();

                foreach ($bankTransactions as $transaction) {
                    if ($transaction->type === 'credit') {
                        $bankAccount->currentbalance -= $transaction->amount;
                    } else {
                        $bankAccount->currentbalance += $transaction->amount;
                    }
                    $transaction->delete();
                }

                $bankAccount->save();
            }
        }

        // 5. Reverse coolie expense
        if ($invoice->coolieexpense > 0) {
            $expense = Expense::where('referencenumber', $invoice->invoicenumber)
                ->whereIn('expensetitle', [
                    'Coolie Charges for invoice ' . $invoice->invoicenumber,
                    'Delivery Charges for invoice ' . $invoice->invoicenumber
                ])
                ->first();

            if ($expense) {
                $cashLedger = AccountLedger::where('ledgertype', 'cash')
                    ->where('ledgername', 'Cash in Hand')
                    ->first();

                if ($cashLedger) {
                    $expenseTransaction = LedgerTransaction::where('referenceabletype', Expense::class)
                        ->where('referenceableid', $expense->id)
                        ->first();

                    if ($expenseTransaction) {
                        $cashLedger->currentbalance += $invoice->coolieexpense;
                        $cashLedger->save();
                        $expenseTransaction->delete();
                    }
                }

                $expense->delete();
            }
        }
    }

    // Recreate ledger entries with new amounts
    private function recreateInvoiceLedgerEntries($invoice)
    {
        $cashLedger = AccountLedger::firstOrCreate(
            ['ledgertype' => 'cash', 'ledgername' => 'Cash in Hand'],
            ['openingbalance' => 0, 'openingbalancetype' => 'debit', 'currentbalance' => 0, 'isactive' => true]
        );

        if ($invoice->invoicetype === 'cash') {
            if ($invoice->paymentmethod === 'cash') {
                $cashLedger->transactions()->create([
                    'date' => $invoice->invoicedate,
                    'type' => 'sale',
                    'description' => 'Cash sale - Invoice ' . $invoice->invoicenumber,
                    'debitamount' => $invoice->finalamount,
                    'creditamount' => 0,
                    'reference' => $invoice->invoicenumber,
                    'referenceabletype' => Invoice::class,
                    'referenceableid' => $invoice->id,
                ]);

                $cashLedger->currentbalance += $invoice->finalamount;
                $cashLedger->save();
            } elseif ($invoice->paymentmethod === 'bank' && $invoice->bankaccountid) {
                $bankAccount = CompanyBankAccount::find($invoice->bankaccountid);
                if ($bankAccount) {
                    $bankAccount->recordTransaction(
                        'credit',
                        $invoice->finalamount,
                        'Invoice payment - ' . $invoice->invoicenumber,
                        transactiondate: $invoice->invoicedate,
                        category: 'sales',
                        referencenumber: $invoice->invoicenumber,
                        transactionabletype: Invoice::class,
                        transactionableid: $invoice->id
                    );
                }
            }
        } elseif ($invoice->invoicetype === 'client' && $invoice->clientid) {
            $client = Client::find($invoice->clientid);

            if ($client) {
                if (!$client->ledger) {
                    $client->ledger()->create([
                        'ledgername' => $client->name,
                        'ledgertype' => 'client',
                        'openingbalance' => 0,
                        'openingbalancetype' => 'debit',
                        'currentbalance' => 0,
                        'isactive' => true,
                    ]);
                    $client->refresh();
                }

                $client->ledger->transactions()->create([
                    'date' => $invoice->invoicedate,
                    'type' => 'sale',
                    'description' => 'Sales invoice - ' . $invoice->invoicenumber . ' (Updated)',
                    'debitamount' => $invoice->finalamount,
                    'creditamount' => 0,
                    'reference' => $invoice->invoicenumber,
                    'referenceabletype' => Invoice::class,
                    'referenceableid' => $invoice->id,
                ]);

                $client->ledger->currentbalance += $invoice->finalamount;
                $client->ledger->save();
            }
        }

        if ($invoice->coolieexpense > 0) {
            $this->recordCoolieExpense($invoice, $cashLedger);
        }
    }


    // before editing saveInvoice
    // public function saveInvoice()
    // {
    //     $this->validate();

    //     // Additional validation
    //     if (empty($this->invoiceItems) || !$this->invoiceItems[0]['product_id']) {
    //         $this->error('At least one product is required');
    //         return;
    //     }

    //     try {
    //         DB::transaction(function () {
    //             // Create invoice
    //             $invoice = Invoice::create([
    //                 'invoice_number' => Invoice::generateInvoiceNumber($this->invoiceType),
    //                 'invoice_type' => $this->invoiceType,
    //                 'invoice_date' => $this->invoiceDate,
    //                 'due_date' => $this->dueDate,
    //                 'client_id' => $this->invoiceType === 'client' ? $this->clientId : null,
    //                 'client_name' => $this->invoiceType === 'cash' ? $this->clientName : null,
    //                 'client_phone' => $this->clientPhone,
    //                 'client_address' => $this->clientAddress,
    //                 'is_gst_invoice' => $this->isGstInvoice,
    //                 'client_gstin' => $this->clientGstin,
    //                 'place_of_supply' => $this->placeOfSupply,
    //                 'gst_type' => $this->gstType,
    //                 'notes' => $this->notes,
    //                 'terms_conditions' => $this->termsConditions,
    //                 'created_by' => auth()->user()->name,
    //                 'coolie_expense' => $this->coolieExpense ?? 0,
    //                 'payment_method' => $this->invoiceType === 'cash' ? $this->paymentMethod : null,
    //                 'bank_account_id' => $this->invoiceType === 'cash' && $this->paymentMethod === 'bank' ? $this->bankAccountId : null,
    //             ]);

    //             $subtotal = 0;
    //             $totalTax = 0;

    //             // Create invoice items and update stock
    //             foreach ($this->invoiceItems as $itemData) {
    //                 if (empty($itemData['product_id']) || empty($itemData['quantity'])) continue;

    //                 $product = Product::find($itemData['product_id']);

    //                 // Check stock availability
    //                 if ($product->stock_quantity < $itemData['quantity']) {
    //                     throw new \Exception("Insufficient stock for product: {$product->name}");
    //                 }

    //                 // Calculate amounts
    //                 $unitPrice = $itemData['unit_price'];
    //                 $quantity = $itemData['quantity'];
    //                 $discountPercent = $itemData['discount_percentage'] ?? 0;

    //                 $lineTotal = $unitPrice * $quantity;
    //                 $discountAmount = ($lineTotal * $discountPercent) / 100;
    //                 $taxableAmount = $lineTotal - $discountAmount;

    //                 // Calculate GST
    //                 $cgstAmount = ($taxableAmount * ($itemData['cgst_rate'] ?? 0)) / 100;
    //                 $sgstAmount = ($taxableAmount * ($itemData['sgst_rate'] ?? 0)) / 100;
    //                 $igstAmount = ($taxableAmount * ($itemData['igst_rate'] ?? 0)) / 100;
    //                 $totalItemAmount = $taxableAmount + $cgstAmount + $sgstAmount + $igstAmount;

    //                 // Create invoice item
    //                 InvoiceItem::create([
    //                     'invoice_id' => $invoice->id,
    //                     'product_id' => $product->id,
    //                     'product_name' => $product->name,
    //                     'product_unit' => $product->unit,
    //                     'invoice_unit' => $itemData['invoice_unit'],
    //                     'unit_conversion_factor' => 1,
    //                     'quantity' => $quantity,
    //                     'unit_price' => $unitPrice,
    //                     'discount_percentage' => $discountPercent,
    //                     'discount_amount' => $discountAmount,
    //                     'taxable_amount' => $taxableAmount,
    //                     'cgst_rate' => $itemData['cgst_rate'] ?? 0,
    //                     'sgst_rate' => $itemData['sgst_rate'] ?? 0,
    //                     'igst_rate' => $itemData['igst_rate'] ?? 0,
    //                     'cgst_amount' => $cgstAmount,
    //                     'sgst_amount' => $sgstAmount,
    //                     'igst_amount' => $igstAmount,
    //                     'total_amount' => $totalItemAmount,
    //                 ]);

    //                 // Update product stock
    //                 $product->stock_quantity -= $quantity;
    //                 $product->save();

    //                 // Create stock movement
    //                 StockMovement::create([
    //                     'product_id' => $product->id,
    //                     'type' => 'out',
    //                     'quantity' => $quantity,
    //                     'reason' => 'sale',
    //                     'reference_type' => Invoice::class,
    //                     'reference_id' => $invoice->id,
    //                 ]);

    //                 $subtotal += $taxableAmount;
    //                 $totalTax += ($cgstAmount + $sgstAmount + $igstAmount);
    //             }

    //             $totalAmount = $subtotal + $totalTax;
    //             $finalAmount = $totalAmount + ($this->coolieExpense ?? 0);

    //             // Update invoice totals
    //             $invoice->update([
    //                 'subtotal' => $subtotal,
    //                 'cgst_amount' => $invoice->items->sum('cgst_amount'),
    //                 'sgst_amount' => $invoice->items->sum('sgst_amount'),
    //                 'igst_amount' => $invoice->items->sum('igst_amount'),
    //                 'total_tax' => $totalTax,
    //                 'total_amount' => $totalAmount,
    //                 'final_amount' => $finalAmount,
    //                 'balance_amount' => $finalAmount,
    //             ]);

    //             // Get cash ledger
    //             $cashLedger = AccountLedger::firstOrCreate(
    //                 ['ledger_type' => 'cash', 'ledger_name' => 'Cash in Hand'],
    //                 [
    //                     'opening_balance' => 0,
    //                     'opening_balance_type' => 'debit',
    //                     'current_balance' => 0,
    //                     'is_active' => true,
    //                 ]
    //             );

    //             // Handle payment recording based on invoice type
    //             if ($this->invoiceType === 'cash') {
    //                 // Cash invoice - record immediate payment
    //                 if ($this->paymentMethod === 'cash') {
    //                     // Add full amount (including coolie) to cash ledger
    //                     $cashLedger->transactions()->create([
    //                         'date' => $this->invoiceDate,
    //                         'type' => 'sale',
    //                         'description' => "Cash sale - Invoice {$invoice->invoice_number}",
    //                         'debit_amount' => $finalAmount,
    //                         'credit_amount' => 0,
    //                         'reference' => $invoice->invoice_number,
    //                         'referenceable_type' => Invoice::class,
    //                         'referenceable_id' => $invoice->id,
    //                     ]);

    //                     $cashLedger->current_balance += $finalAmount;
    //                     $cashLedger->save();
    //                 } elseif ($this->paymentMethod === 'bank') {
    //                     // Add invoice amount to bank account
    //                     $bankAccount = CompanyBankAccount::find($this->bankAccountId);
    //                     if ($bankAccount) {
    //                         $bankAccount->recordTransaction(
    //                             'credit',
    //                             $finalAmount,
    //                             "Invoice payment - {$invoice->invoice_number}",
    //                             [
    //                                 'transaction_date' => $this->invoiceDate,
    //                                 'category' => 'sales',
    //                                 'reference_number' => $invoice->invoice_number,
    //                                 'transactionable_type' => Invoice::class,
    //                                 'transactionable_id' => $invoice->id,
    //                             ]
    //                         );
    //                     }
    //                 }

    //                 // Mark cash invoice as paid
    //                 $invoice->update([
    //                     'paid_amount' => $finalAmount,
    //                     'balance_amount' => 0,
    //                     'payment_status' => 'paid',
    //                 ]);
    //             } elseif ($this->invoiceType === 'client') {
    //                 // Client invoice - create ledger entry (unpaid)
    //                 $client = Client::find($this->clientId);
    //                 if ($client) {
    //                     // Ensure client has a ledger
    //                     if (!$client->ledger) {
    //                         $client->ledger()->create([
    //                             'ledger_name' => $client->name,
    //                             'ledger_type' => 'client',
    //                             'opening_balance' => 0,
    //                             'opening_balance_type' => 'debit',
    //                             'current_balance' => 0,
    //                             'is_active' => true,
    //                         ]);
    //                         $client->refresh();
    //                     }

    //                     // Create debit entry (client owes money)
    //                     $client->ledger->transactions()->create([
    //                         'date' => $this->invoiceDate,
    //                         'type' => 'sale',
    //                         'description' => "Sales invoice - {$invoice->invoice_number}",
    //                         'debit_amount' => $finalAmount,
    //                         'credit_amount' => 0,
    //                         'reference' => $invoice->invoice_number,
    //                         'referenceable_type' => Invoice::class,
    //                         'referenceable_id' => $invoice->id,
    //                     ]);

    //                     // Update client ledger balance
    //                     $client->ledger->current_balance += $finalAmount;
    //                     $client->ledger->save();
    //                 }
    //             }
    //             // Record and deduct coolie expense from cash
    //             if ($this->coolieExpense > 0) {
    //                 $this->recordCoolieExpense($invoice, $cashLedger);
    //             }

    //             $this->success('Invoice created successfully!');
    //             $this->closeInvoiceModal();
    //             $this->calculateStats();

    //             // Generate PDF for client invoices
    //             if ($this->invoiceType === 'client') {
    //                 $this->dispatch('invoice-created', ['invoiceId' => $invoice->id]);
    //             }
    //         });
    //     } catch (\Exception $e) {
    //         Log::error('Error creating invoice: ' . $e->getMessage());
    //         $this->error('Error creating invoice: ' . $e->getMessage());
    //     }
    // }

    // Helper method to record coolie expense
    private function recordCoolieExpense(Invoice $invoice, AccountLedger $cashLedger): void
    {
        if ($this->coolieExpense <= 0) {
            return;
        }

        // Decide label only
        $categoryName = $this->invoiceType === 'client'
            ? 'Delivery Charges'
            : 'Coolie Charges';

        // One firstOrCreate, no duplicated arrays
        $coolieCategory = ExpenseCategory::firstOrCreate(
            ['name' => $categoryName],
            [
                'description' => 'Delivery and coolie charges',
                'is_active' => true,
            ]
        );

        // Expense row
        $expense = Expense::create([
            'expense_title'      => "{$categoryName} for invoice {$invoice->invoice_number}",
            'category_id'        => $coolieCategory->id,
            'amount'             => $this->coolieExpense,
            'description'        => "{$categoryName} paid for invoice {$invoice->invoice_number}",
            'expense_date'       => $invoice->invoice_date,
            'payment_method'     => 'cash',
            'bank_account_id'    => null,
            'reference_number'   => $invoice->invoice_number,
            'is_business_expense' => true,
            'is_reimbursable'    => false,
            'approval_status'    => 'approved',
            'approved_by'        => auth()->user()->name,
            'approved_at'        => now(),
            'created_by'         => auth()->id(),
        ]);

        // Cash out
        $cashLedger->transactions()->create([
            'date'               => $invoice->invoice_date,
            'type'               => 'payment',
            'description'        => "{$categoryName} for invoice {$invoice->invoice_number}",
            'debit_amount'       => 0,
            'credit_amount'      => $this->coolieExpense,
            'reference'          => $expense->expense_ref,
            'referenceable_type' => Expense::class,
            'referenceable_id'   => $expense->id,
        ]);

        $cashLedger->current_balance -= $this->coolieExpense;
        $cashLedger->save();
    }


    public function downloadInvoicePdf($invoiceId)
    {
        try {
            $invoice = Invoice::with(['client', 'items.product'])->findOrFail($invoiceId);

            $pdfService = new InvoicePdfService();
            $pdfPath = $pdfService->generateInvoicePdf($invoice);

            return response()->download($pdfPath)->deleteFileAfterSend();
        } catch (\Exception $e) {
            Log::error('Error generating PDF: ' . $e->getMessage());
            $this->error('Error generating PDF');
        }
    }

    public function downloadMonthlyBillPdf($billId)
    {
        try {
            $monthlyBill = MonthlyBill::with(['client', 'invoices'])->findOrFail($billId);

            $pdfService = new InvoicePdfService();
            $pdfPath = $pdfService->generateMonthlyBillPdf($monthlyBill);

            return response()->download($pdfPath)->deleteFileAfterSend();
        } catch (\Exception $e) {
            Log::error('Error generating Monthly Bill PDF: ' . $e->getMessage());
            $this->error('Error generating Monthly Bill PDF');
        }
    }

    public function openMonthlyBillModal($clientId)
    {
        $this->selectedClient = Client::find($clientId);
        $this->showMonthlyBillModal = true;
        $this->monthlyBillPeriodFrom = now()->startOfMonth()->format('Y-m-d');
        $this->monthlyBillPeriodTo = now()->endOfMonth()->format('Y-m-d');
        $this->loadUnbilledInvoices();
    }

    public function closeMonthlyBillModal()
    {
        $this->showMonthlyBillModal = false;
        $this->selectedClient = null;
        $this->unbilledInvoices = [];
        $this->selectedInvoicesForBilling = [];
    }

    public function loadUnbilledInvoices()
    {
        if (!$this->selectedClient) return;

        $this->unbilledInvoices = Invoice::where('client_id', $this->selectedClient->id)
            ->where('is_monthly_billed', false)
            ->whereBetween('invoice_date', [$this->monthlyBillPeriodFrom, $this->monthlyBillPeriodTo])
            ->get();
    }

    public function generateMonthlyBill()
    {
        if (empty($this->selectedInvoicesForBilling)) {
            $this->error('Please select at least one invoice');
            return;
        }

        try {
            DB::transaction(function () {
                $invoices = Invoice::whereIn('id', $this->selectedInvoicesForBilling)->get();

                $monthlyBill = MonthlyBill::create([
                    'client_id' => $this->selectedClient->id,
                    'bill_date' => now(),
                    'period_from' => $this->monthlyBillPeriodFrom,
                    'period_to' => $this->monthlyBillPeriodTo,
                    'total_amount' => $invoices->sum('total_amount'),
                    'invoice_count' => $invoices->count(),
                ]);

                // Update invoices
                Invoice::whereIn('id', $this->selectedInvoicesForBilling)
                    ->update([
                        'is_monthly_billed' => true,
                        'monthly_bill_id' => $monthlyBill->id,
                    ]);

                $this->success('Monthly bill generated successfully!');
                $this->closeMonthlyBillModal();

                // Generate PDF
                $this->dispatch('monthly-bill-generated', ['billId' => $monthlyBill->id]);
            });
        } catch (\Exception $e) {
            Log::error('Error generating monthly bill: ' . $e->getMessage());
            $this->error('Error generating monthly bill');
        }
    }

    // public function openMonthlyBillsModal()
    // {
    //     $this->showMonthlyBillsModal = true;
    //     $this->loadMonthlyBills();
    // }

    // public function closeMonthlyBillsModal()
    // {
    //     $this->showMonthlyBillsModal = false;
    //     $this->monthlyBills = [];
    // }

    // private function loadMonthlyBills()
    // {
    //     $this->monthlyBills = MonthlyBill::with(['client'])
    //         ->when($this->search, function ($q) {
    //             return $q->where('bill_number', 'like', '%' . $this->search . '%')
    //                 ->orWhereHas('client', function ($query) {
    //                     $query->where('name', 'like', '%' . $this->search . '%');
    //                 });
    //         })
    //         ->when($this->dateFrom, function ($q) {
    //             return $q->where('bill_date', '>=', $this->dateFrom);
    //         })
    //         ->when($this->dateTo, function ($q) {
    //             return $q->where('bill_date', '<=', $this->dateTo);
    //         })
    //         ->orderBy('bill_date', 'desc')
    //         ->paginate(10);
    // }

    public function calculateStats()
    {
        $invoices = Invoice::query();

        if ($this->dateFrom) {
            $invoices->where('invoice_date', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $invoices->where('invoice_date', '<=', $this->dateTo);
        }

        $stats = $invoices->selectRaw('
            COUNT(*) as total_invoices,
            SUM(total_amount) as total_amount,
            SUM(paid_amount) as paid_amount,
            SUM(balance_amount) as pending_amount
        ')->first();

        $this->totalInvoices = $stats->total_invoices ?? 0;
        $this->totalAmount = $stats->total_amount ?? 0;
        $this->paidAmount = $stats->paid_amount ?? 0;
        $this->pendingAmount = $stats->pending_amount ?? 0;
    }

    public function viewInvoice($invoiceId)
    {
        $this->viewingInvoice = Invoice::with(['client', 'items.product', 'payments'])->find($invoiceId);
        $this->showViewModal = true;
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewingInvoice = null;
    }

    public function openPaymentModal($invoiceId)
    {
        $this->paymentInvoice = Invoice::find($invoiceId);
        $this->showPaymentModal = true;
        $this->paymentAmount = '';
        $this->paymentMethod = 'cash';
        $this->paymentReference = '';
        $this->paymentNotes = '';
        $this->resetValidation();
    }

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->paymentInvoice = null;
        $this->paymentAmount = '';
        $this->paymentMethod = 'cash';
        $this->paymentReference = '';
        $this->paymentNotes = '';
        $this->resetValidation();
    }

    public function savePayment()
    {
        $this->validate([
            'paymentAmount' => 'required|numeric|min:0.01|max:' . $this->paymentInvoice->balance_amount,
            'paymentMethod' => 'required|in:cash,bank,upi,card,cheque',
            'paymentReference' => 'nullable|string|max:255',
            'paymentNotes' => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () {
                // Create payment record
                $payment = InvoicePayment::create([
                    'invoice_id' => $this->paymentInvoice->id,
                    'payment_date' => now(),
                    'amount' => $this->paymentAmount,
                    'payment_method' => $this->paymentMethod,
                    'reference_number' => $this->paymentReference,
                    'notes' => $this->paymentNotes,
                ]);

                // Update invoice payment status
                $this->paymentInvoice->paid_amount += $this->paymentAmount;
                $this->paymentInvoice->balance_amount -= $this->paymentAmount;

                // Update payment status
                if ($this->paymentInvoice->balance_amount <= 0) {
                    $this->paymentInvoice->payment_status = 'paid';
                } elseif ($this->paymentInvoice->paid_amount > 0) {
                    $this->paymentInvoice->payment_status = 'partial';
                }

                $this->paymentInvoice->save();

                // Create ledger entry if client invoice
                if ($this->paymentInvoice->client_id && $this->paymentInvoice->client->ledger) {
                    $ledgerTransaction = LedgerTransaction::create([
                        'ledger_id' => $this->paymentInvoice->client->ledger->id,
                        'date' => now(),
                        'type' => 'payment',
                        'description' => "Payment received for invoice {$this->paymentInvoice->invoice_number}",
                        'debit_amount' => 0,
                        'credit_amount' => $this->paymentAmount,
                        'reference' => $this->paymentReference ?: "PAY-{$payment->id}",
                    ]);

                    // Update client ledger balance
                    $this->paymentInvoice->client->ledger->current_balance -= $this->paymentAmount;
                    $this->paymentInvoice->client->ledger->save();

                    // ✅ NEW: Record cash/bank transaction
                    if ($this->paymentMethod === 'cash') {
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
                            'date' => now(),
                            'type' => 'payment',
                            'description' => "Invoice payment - {$this->paymentInvoice->invoice_number} from {$this->paymentInvoice->client->name}",
                            'debit_amount' => $this->paymentAmount,
                            'credit_amount' => 0,
                            'reference' => $this->paymentReference ?: "PAY-{$payment->id}",
                            'referenceable_type' => InvoicePayment::class,
                            'referenceable_id' => $payment->id,
                        ]);

                        // Update cash balance
                        $cashLedger->current_balance += $this->paymentAmount;
                        $cashLedger->save();
                    }
                }
            });

            $this->success('Payment recorded successfully!');
            $this->closePaymentModal();
            $this->calculateStats();
        } catch (\Exception $e) {
            Log::error('Error recording payment: ' . $e->getMessage());
            $this->error('Error recording payment: ' . $e->getMessage());
        }
    }


    public function deleteInvoice($invoiceId)
    {
        try {
            $invoice = Invoice::find($invoiceId);

            if ($invoice->paid_amount > 0) {
                $this->error('Cannot delete invoice with payments. Please refund payments first.');
                return;
            }

            DB::transaction(function () use ($invoice) {
                // Mark as cancelled
                $invoice->update([
                    'is_cancelled' => true,
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Invoice deleted by user',
                ]);

                // The boot method will handle stock restoration and ledger adjustments
                $invoice->delete();
            });

            $this->success('Invoice deleted and stock restored successfully!');
            $this->calculateStats();
        } catch (\Exception $e) {
            Log::error('Error deleting invoice: ' . $e->getMessage());
            $this->error('Error deleting invoice: ' . $e->getMessage());
        }
    }

    public function duplicateInvoice($invoiceId)
    {
        try {
            $originalInvoice = Invoice::with('items')->find($invoiceId);

            $this->invoiceType = $originalInvoice->invoice_type;
            $this->clientId = $originalInvoice->client_id;
            $this->clientName = $originalInvoice->client_name;
            $this->clientPhone = $originalInvoice->client_phone;
            $this->clientAddress = $originalInvoice->client_address;
            $this->isGstInvoice = $originalInvoice->is_gst_invoice;
            $this->clientGstin = $originalInvoice->client_gstin;
            $this->placeOfSupply = $originalInvoice->place_of_supply;
            $this->gstType = $originalInvoice->gst_type;
            $this->notes = $originalInvoice->notes;
            $this->termsConditions = $originalInvoice->terms_conditions;

            // Copy items
            $this->invoiceItems = [];
            foreach ($originalInvoice->items as $item) {
                $this->invoiceItems[] = [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'invoice_unit' => $item->invoice_unit,
                    'discount_percentage' => $item->discount_percentage,
                    'cgst_rate' => $item->cgst_rate,
                    'sgst_rate' => $item->sgst_rate,
                    'igst_rate' => $item->igst_rate,
                ];
            }

            $this->showInvoiceModal = true;
            $this->success('Invoice duplicated. You can now modify and save.');
        } catch (\Exception $e) {
            Log::error('Error duplicating invoice: ' . $e->getMessage());
            $this->error('Error duplicating invoice');
        }
    }

    public function render()
    {
        if ($this->activeTab === 'monthly_bills') {
            $monthlyBills = MonthlyBill::with(['client'])
                ->when($this->search, function ($q) {
                    return $q->where('bill_number', 'like', '%' . $this->search . '%')
                        ->orWhereHas('client', function ($query) {
                            $query->where('name', 'like', '%' . $this->search . '%');
                        });
                })
                ->when($this->dateFrom, function ($q) {
                    return $q->where('bill_date', '>=', $this->dateFrom);
                })
                ->when($this->dateTo, function ($q) {
                    return $q->where('bill_date', '<=', $this->dateTo);
                })
                ->orderBy('bill_date', 'desc')
                ->paginate($this->perPage);

            return view('livewire.invoice-management', [
                'invoices' => collect(), // Empty collection for invoices tab
                'monthlyBills' => $monthlyBills, // Properly paginated
                'clients' => Client::where('is_active', true)->get(),
                'products' => Product::where('is_active', true)->get(),
            ]);
        } else {
            // Original invoice query
            $invoices = Invoice::with(['client', 'items'])
                ->when($this->search, function ($q) {
                    return $q->where(function ($query) {
                        $query->where('invoice_number', 'like', '%' . $this->search . '%')
                            ->orWhere('client_name', 'like', '%' . $this->search . '%')
                            ->orWhereHas('client', function ($q) {
                                $q->where('name', 'like', '%' . $this->search . '%');
                            });
                    });
                })
                ->when($this->statusFilter, function ($q) {
                    return $q->where('payment_status', $this->statusFilter);
                })
                ->when($this->typeFilter, function ($q) {
                    return $q->where('invoice_type', $this->typeFilter);
                })
                ->when($this->clientFilter, function ($q) {
                    return $q->where('client_id', $this->clientFilter);
                })
                ->when($this->dateFrom, function ($q) {
                    return $q->where('invoice_date', '>=', $this->dateFrom);
                })
                ->when($this->dateTo, function ($q) {
                    return $q->where('invoice_date', '<=', $this->dateTo);
                })
                ->orderBy('invoice_date', 'desc')
                ->paginate($this->perPage);

            return view('livewire.invoice-management', [
                'invoices' => $invoices,
                'monthlyBills' => collect(), // Empty collection for monthly bills tab
                'clients' => Client::where('is_active', true)->get(),
                'products' => Product::where('is_active', true)->get(),
                'bankAccounts' => CompanyBankAccount::active()->get(),
            ]);
        }
    }
}
