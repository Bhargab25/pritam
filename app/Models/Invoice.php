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
                    $client = $invoice->client;

                    if ($client && $client->ledger) {
                        $ledger = $client->ledger;

                        // Find all ledger transactions related to this invoice
                        $invoiceTransactions = LedgerTransaction::where('referenceable_type', Invoice::class)
                            ->where('referenceable_id', $invoice->id)
                            ->get();

                        foreach ($invoiceTransactions as $transaction) {
                            // Reverse the transaction effect on ledger balance
                            $ledger->current_balance -= ($transaction->debit_amount - $transaction->credit_amount);

                            // Soft delete the transaction
                            $transaction->delete();
                        }

                        $ledger->save();

                        Log::info("Ledger transactions reversed for invoice {$invoice->invoice_number}");
                    }

                    // ========================================
                    // 3. DELETE COOLIE/DELIVERY EXPENSE ENTRY (✅ NEW)
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
                                // Find and delete the expense's ledger transaction
                                $expenseTransaction = LedgerTransaction::where('referenceable_type', Expense::class)
                                    ->where('referenceable_id', $expense->id)
                                    ->first();

                                if ($expenseTransaction) {
                                    // Reverse the cash deduction (add money back)
                                    $cashLedger->current_balance += $invoice->coolie_expense;
                                    $cashLedger->save();

                                    // Delete the transaction
                                    $expenseTransaction->delete();

                                    Log::info("Expense ledger transaction reversed for invoice {$invoice->invoice_number}");
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
                    $client = $invoice->client;

                    if ($client && $client->ledger) {
                        $ledger = $client->ledger;

                        // Restore soft-deleted ledger transactions
                        $invoiceTransactions = LedgerTransaction::withTrashed()
                            ->where('referenceable_type', Invoice::class)
                            ->where('referenceable_id', $invoice->id)
                            ->get();

                        foreach ($invoiceTransactions as $transaction) {
                            // Restore the transaction
                            $transaction->restore();

                            // Re-apply effect on ledger balance
                            $ledger->current_balance += ($transaction->debit_amount - $transaction->credit_amount);
                        }

                        $ledger->save();

                        Log::info("Ledger transactions restored for invoice {$invoice->invoice_number}");
                    }

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

                            // Find cash ledger
                            $cashLedger = AccountLedger::where('ledger_type', 'cash')
                                ->where('ledger_name', 'Cash in Hand')
                                ->first();

                            if ($cashLedger) {
                                // Find and restore the expense's ledger transaction
                                $expenseTransaction = LedgerTransaction::withTrashed()
                                    ->where('referenceable_type', Expense::class)
                                    ->where('referenceable_id', $expense->id)
                                    ->first();

                                if ($expenseTransaction) {
                                    // Restore transaction
                                    $expenseTransaction->restore();

                                    // Re-deduct from cash
                                    $cashLedger->current_balance -= $invoice->coolie_expense;
                                    $cashLedger->save();

                                    Log::info("Expense ledger transaction restored for invoice {$invoice->invoice_number}");
                                }
                            }

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
