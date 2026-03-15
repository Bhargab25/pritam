<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'invoice_type',
        'invoice_date',
        'due_date',
        'client_id',
        'client_name',
        'client_phone',
        'client_address',
        'is_gst_invoice',
        'client_gstin',
        'place_of_supply',
        'gst_type',
        'subtotal',
        'discount_amount',
        'discount_percentage',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'total_tax',
        'total_amount',
        'coolie_expense',
        'final_amount',
        'payment_method',
        'bank_account_id',
        'paid_amount',
        'balance_amount',
        'payment_status',
        'is_monthly_billed',
        'monthly_bill_id',
        'is_cancelled',
        'cancelled_at',
        'cancellation_reason',
        'notes',
        'terms_conditions',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'is_gst_invoice' => 'boolean',
        'is_monthly_billed' => 'boolean',
        'is_cancelled' => 'boolean',
        'cancelled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'cgst_amount' => 'decimal:2',
        'sgst_amount' => 'decimal:2',
        'igst_amount' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'coolie_expense' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2'
    ];

    // Relationships
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function monthlyBill()
    {
        return $this->belongsTo(MonthlyBill::class);
    }

    public function ledgerTransactions()
    {
        return $this->morphMany(LedgerTransaction::class, 'reference');
    }

    public function bankAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'bank_account_id');
    }

    // Accessors & Mutators
    public function getDisplayClientNameAttribute()
    {
        return $this->client ? $this->client->name : $this->client_name;
    }



    public function getStatusBadgeClassAttribute()
    {
        return match ($this->payment_status) {
            'paid' => 'badge-success',
            'partial' => 'badge-warning',
            'overdue' => 'badge-error',
            default => 'badge-info'
        };
    }

    // Scopes
    public function scopeCashInvoices($query)
    {
        return $query->where('invoice_type', 'cash');
    }

    public function scopeClientInvoices($query)
    {
        return $query->where('invoice_type', 'client');
    }

    public function scopeUnbilled($query)
    {
        return $query->where('is_monthly_billed', false)
            ->where('invoice_type', 'client');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('payment_status', '!=', 'paid');
    }

    // Boot method for automatic invoice number generation
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            $year = date('Y');
            $prefix = 'INV-' . $year . '-';

            // Find last invoice for this year (including soft-deleted)
            /** @var \App\Models\Invoice|null $lastInvoice */
            $lastInvoice = static::withTrashed()
                ->where('invoice_number', 'like', $prefix . '%')
                ->orderBy('invoice_number', 'desc')
                ->first();

            if ($lastInvoice) {
                // Extract numeric part after last dash
                $lastNumber = (int) substr($lastInvoice->invoice_number, strrpos($lastInvoice->invoice_number, '-') + 1);
                $newNumber  = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }

            $invoice->invoice_number = $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
        });

        static::deleting(function ($invoice) {
            try {
                DB::transaction(function () use ($invoice) {

                    // ========================================
                    // 1. HANDLE STOCK REVERSAL
                    // ========================================
                    if ($invoice->is_cancelled) {
                        foreach ($invoice->items as $item) {
                            $product = $item->product;
                            if ($product) {
                                $product->increment('stock_quantity', $item->quantity);

                                StockMovement::create([
                                    'product_id' => $product->id,
                                    'type' => 'in',
                                    'quantity' => $item->quantity,
                                    'reason' => 'invoice_cancellation',
                                    'reference_type' => Invoice::class,
                                    'reference_id' => $invoice->id,
                                ]);
                            }
                        }
                    }

                    // ========================================
                    // 2. HANDLE LEDGER TRANSACTION REVERSAL
                    // ========================================
                    
                    // A. Reverse Ledger Transactions directly linked to the Invoice
                    $invoiceLedgerTransactions = LedgerTransaction::where('referenceable_type', Invoice::class)
                        ->where('referenceable_id', $invoice->id)
                        ->get();

                    foreach ($invoiceLedgerTransactions as $transaction) {
                        $transaction->ledger->transactions()->create([
                            'date' => now(),
                            'type' => 'adjustment',
                            'description' => "Reversal of: " . $transaction->description,
                            'debit_amount' => $transaction->credit_amount,
                            'credit_amount' => $transaction->debit_amount,
                            'reference' => "REV-" . ($transaction->reference ?? $invoice->invoice_number),
                            'referenceable_type' => Invoice::class,
                            'referenceable_id' => $invoice->id,
                        ]);
                    }

                    // B. Reverse Bank Transactions directly linked to the Invoice
                    $invoiceBankTransactions = BankTransaction::where('transactionable_type', Invoice::class)
                        ->where('transactionable_id', $invoice->id)
                        ->get();

                    foreach ($invoiceBankTransactions as $bankTx) {
                        $bankTx->bankAccount->recordTransaction(
                            $bankTx->type === 'credit' ? 'debit' : 'credit',
                            $bankTx->amount,
                            "Reversal of: " . $bankTx->description,
                            [
                                'transaction_date' => now(),
                                'category' => 'reversal',
                                'reference_number' => "REV-" . ($bankTx->reference_number ?? $invoice->invoice_number),
                                'transactionable_type' => Invoice::class,
                                'transactionable_id' => $invoice->id,
                            ]
                        );
                    }

                    Log::info("Invoice transactions reversed for invoice {$invoice->invoice_number}");

                    // C. Reverse any associated Invoice Payments
                    foreach ($invoice->payments as $payment) {
                        // Reverse Ledger Transactions for this payment
                        $paymentLedgerTransactions = LedgerTransaction::where(function($q) use ($payment) {
                                $q->where('referenceable_type', InvoicePayment::class)
                                  ->where('referenceable_id', $payment->id);
                            })->orWhere('reference', 'PAY-' . $payment->id)->get();

                        $processedLedgerTxIds = [];

                        foreach ($paymentLedgerTransactions as $transaction) {
                            if (in_array($transaction->id, $processedLedgerTxIds)) continue;
                            $processedLedgerTxIds[] = $transaction->id;

                            $transaction->ledger->transactions()->create([
                                'date' => now(),
                                'type' => 'adjustment',
                                'description' => "Reversal of payment: " . $transaction->description,
                                'debit_amount' => $transaction->credit_amount,
                                'credit_amount' => $transaction->debit_amount,
                                'reference' => "REV-" . ($transaction->reference ?? 'PAY-' . $payment->id),
                                'referenceable_type' => InvoicePayment::class,
                                'referenceable_id' => $payment->id,
                            ]);
                        }

                        // Reverse Bank Transactions for this payment
                        $paymentBankTransactions = BankTransaction::where(function($q) use ($payment) {
                                $q->where('transactionable_type', InvoicePayment::class)
                                  ->where('transactionable_id', $payment->id);
                            })->orWhere('reference_number', 'PAY-' . $payment->id)->get();
                            
                        $processedBankTxIds = [];
                        
                        foreach ($paymentBankTransactions as $bankTx) {
                            if (in_array($bankTx->id, $processedBankTxIds)) continue;
                            $processedBankTxIds[] = $bankTx->id;

                            $bankTx->bankAccount->recordTransaction(
                                $bankTx->type === 'credit' ? 'debit' : 'credit',
                                $bankTx->amount,
                                "Reversal of payment: " . $bankTx->description,
                                [
                                    'transaction_date' => now(),
                                    'category' => 'reversal',
                                    'reference_number' => "REV-" . ($bankTx->reference_number ?? 'PAY-' . $payment->id),
                                    'transactionable_type' => InvoicePayment::class,
                                    'transactionable_id' => $payment->id,
                                ]
                            );
                        }

                        // Delete payment record
                        $payment->delete();
                    }
                    Log::info("Payment transactions reversed for invoice {$invoice->invoice_number}");

                    // ========================================
                    // 3. REVERSE COOLIE/DELIVERY EXPENSE ENTRY (✅ UPDATED)
                    // ========================================
                    if ($invoice->coolie_expense > 0) {
                        // Find the expense entry created for this invoice
                        $expense = Expense::where('reference_number', $invoice->invoice_number)
                            ->whereIn('expense_title', [
                                "Coolie Charges for invoice {$invoice->invoice_number}",
                                "Delivery Charges for invoice {$invoice->invoice_number}"
                            ])
                            ->first();

                        if ($expense) {
                            // Find cash ledger
                            $cashLedger = AccountLedger::where('ledger_type', 'cash')
                                ->where('ledger_name', 'Cash in Hand')
                                ->first();

                            if ($cashLedger) {
                                // Find the expense's ledger transaction
                                $expenseTransaction = LedgerTransaction::where('referenceable_type', Expense::class)
                                    ->where('referenceable_id', $expense->id)
                                    ->first();

                                if ($expenseTransaction) {
                                    // Reverse the cash deduction by creating a new debit transaction
                                    $cashLedger->transactions()->create([
                                        'date' => now(),
                                        'type' => 'adjustment',
                                        'description' => "Reversal of: " . $expenseTransaction->description,
                                        'debit_amount' => $expenseTransaction->credit_amount,
                                        'credit_amount' => $expenseTransaction->debit_amount,
                                        'reference' => "REV-" . ($expenseTransaction->reference ?? 'EXP-' . $expense->id),
                                        'referenceable_type' => Expense::class,
                                        'referenceable_id' => $expense->id,
                                    ]);

                                    Log::info("Expense ledger transaction reversing entry created for invoice {$invoice->invoice_number}");
                                }
                            }

                            // Delete the expense record
                            $expense->delete();

                            Log::info("Expense entry deleted for invoice {$invoice->invoice_number}");
                        }
                    }

                    // ========================================
                    // 4. DELETE PAYMENT ALLOCATIONS
                    // ========================================
                    // DB::table('invoice_payment_allocations')
                    //     ->where('invoice_id', $invoice->id)
                    //     ->delete();
                });

                Log::info("Invoice {$invoice->invoice_number} deleted successfully with all reversals.");
            } catch (\Exception $e) {
                Log::error("Error deleting invoice {$invoice->invoice_number}: " . $e->getMessage());
                throw $e; // Re-throw to prevent deletion if something fails
            }
        });


        // ✅ WHEN INVOICE IS BEING RESTORED
        static::restoring(function ($invoice) {
            try {
                DB::transaction(function () use ($invoice) {

                    // ========================================
                    // 1. RESTORE STOCK
                    // ========================================
                    if ($invoice->is_cancelled) {
                        foreach ($invoice->items as $item) {
                            $product = $item->product;
                            if ($product) {
                                // Deduct stock again
                                $product->decrement('stock_quantity', $item->quantity);

                                StockMovement::create([
                                    'product_id' => $product->id,
                                    'type' => 'out',
                                    'quantity' => $item->quantity,
                                    'reason' => 'invoice_restoration',
                                    'reference_type' => Invoice::class,
                                    'reference_id' => $invoice->id,
                                ]);
                            }
                        }
                    }

                    // ========================================
                    // 2. RESTORE LEDGER TRANSACTIONS
                    // ========================================
                    
                    Log::info("Invoice {$invoice->invoice_number} restored. Note: previously created reversal ledger entries were kept. It is recommended to duplicate the invoice instead of restoring.");

                    // ========================================
                    // 3. RESTORE COOLIE/DELIVERY EXPENSE (✅ NEW)
                    // ========================================
                    if ($invoice->coolie_expense > 0) {
                        // Find the soft-deleted expense
                        $expense = Expense::withTrashed()
                            ->where('reference_number', $invoice->invoice_number)
                            ->whereIn('expense_title', [
                                "Coolie Charges for invoice {$invoice->invoice_number}",
                                "Delivery Charges for invoice {$invoice->invoice_number}"
                            ])
                            ->first();

                        if ($expense) {
                            // Restore the expense
                            $expense->restore();

                            Log::info("Expense entry restored for invoice {$invoice->invoice_number}");
                        }
                    }
                });

                Log::info("Invoice {$invoice->invoice_number} restored successfully.");
            } catch (\Exception $e) {
                Log::error("Error restoring invoice {$invoice->invoice_number}: " . $e->getMessage());
                throw $e;
            }
        });
    }

    public static function generateInvoiceNumber($type = 'cash')
    {
        $prefix = $type === 'cash' ? 'CASH' : 'INV';
        $year = date('Y');
        $month = date('m');

        $lastInvoice = static::where('invoice_number', 'like', "$prefix-$year$month-%")
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = intval(substr($lastInvoice->invoice_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return "$prefix-$year$month-" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function restoreStock()
    {
        foreach ($this->items as $item) {
            // Restore stock
            $product = $item->product;
            $product->stock_quantity += $item->quantity;
            $product->save();

            // Create stock movement record
            StockMovement::create([
                'product_id' => $item->product_id,
                'type' => 'in',
                'quantity' => $item->quantity,
                'reason' => 'invoice_cancellation',
                'reference_type' => Invoice::class,
                'reference_id' => $this->id,
            ]);
        }
    }

    public function createCancellationLedgerEntries()
    {
        if ($this->client_id && $this->client->ledger) {
            // Create reversal entry in client ledger
            LedgerTransaction::create([
                'ledger_id' => $this->client->ledger->id,
                'date' => now(),
                'type' => 'adjustment',
                'description' => "Invoice cancellation - {$this->invoice_number}",
                'credit_amount' => $this->total_amount, // Reverse the debit
                'reference' => "CANCEL-{$this->invoice_number}",
            ]);
        }
    }

    /**
     * Allocate payment amount to client's unpaid invoices (oldest first)
     * Returns array of allocated payments
     */
    public static function allocatePaymentToInvoices($clientId, $paymentAmount, $paymentDate, $paymentReference = null)
    {
        $remainingAmount = $paymentAmount;
        $allocations = [];

        // Get unpaid/partially paid invoices ordered by date (oldest first)
        $invoices = static::where('client_id', $clientId)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('balance_amount', '>', 0)
            ->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($invoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            // Calculate how much to allocate to this invoice
            $amountToAllocate = min($remainingAmount, $invoice->balance_amount);

            // Create payment record
            $payment = InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'payment_date' => $paymentDate,
                'amount' => $amountToAllocate,
                'payment_method' => 'cheque',
                'reference_number' => $paymentReference,
                'notes' => 'Auto-allocated from ledger payment',
            ]);

            // Update invoice amounts
            $invoice->paid_amount += $amountToAllocate;
            $invoice->balance_amount -= $amountToAllocate;

            // Update payment status
            if ($invoice->balance_amount <= 0.01) { // Using 0.01 to handle floating point precision
                $invoice->payment_status = 'paid';
                $invoice->balance_amount = 0; // Ensure it's exactly 0
            } elseif ($invoice->paid_amount > 0) {
                $invoice->payment_status = 'partial';
            }

            $invoice->save();

            // Track allocation
            $allocations[] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount_allocated' => $amountToAllocate,
                'invoice_balance_before' => $invoice->balance_amount + $amountToAllocate,
                'invoice_balance_after' => $invoice->balance_amount,
            ];

            // Reduce remaining amount
            $remainingAmount -= $amountToAllocate;
        }

        return [
            'allocations' => $allocations,
            'amount_allocated' => $paymentAmount - $remainingAmount,
            'remaining_amount' => $remainingAmount,
        ];
    }


    public function calculateTotals()
    {
        $this->subtotal = $this->items->sum('taxable_amount');
        $this->cgst_amount = $this->items->sum('cgst_amount');
        $this->sgst_amount = $this->items->sum('sgst_amount');
        $this->igst_amount = $this->items->sum('igst_amount');
        $this->total_tax = $this->cgst_amount + $this->sgst_amount + $this->igst_amount;
        $this->total_amount = $this->subtotal + $this->total_tax - $this->discount_amount;
        $this->balance_amount = $this->total_amount - $this->paid_amount;

        $this->save();
    }
}
