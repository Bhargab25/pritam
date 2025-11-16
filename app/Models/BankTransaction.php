<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_id',  // ✅ Keep this
        'transaction_date',
        'type',
        'amount',
        'category',
        'reference_number',
        'description',
        'transactionable_type',
        'transactionable_id',
        'transfer_to_account_id',
        'balance_after',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    // Relationships
    public function bankAccount()
    {
        // ✅ Explicitly specify the foreign key and owner key
        return $this->belongsTo(CompanyBankAccount::class, 'bank_account_id', 'id');
    }

    public function transactionable()
    {
        return $this->morphTo();
    }

    public function transferToAccount()
    {
        // ✅ Also specify for this relationship
        return $this->belongsTo(CompanyBankAccount::class, 'transfer_to_account_id', 'id');
    }

    // Accessors
    public function getTypeBadgeClassAttribute()
    {
        return match($this->type) {
            'credit' => 'badge-success',
            'debit' => 'badge-error',
            'transfer' => 'badge-info',
            default => 'badge-ghost'
        };
    }
}
