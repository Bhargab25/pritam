<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_profile_id',
        'account_name',
        'bank_name',
        'account_number',
        'ifsc_code',
        'branch_name',
        'account_type',
        'opening_balance',
        'current_balance',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function companyProfile()
    {
        return $this->belongsTo(CompanyProfile::class);
    }

    public function transactions()
    {
        // ✅ Explicitly specify the foreign key
        return $this->hasMany(BankTransaction::class, 'bank_account_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Methods
    public function getMaskedAccountNumberAttribute()
    {
        $accountNumber = $this->account_number;
        $length = strlen($accountNumber);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($accountNumber, -4);
    }

    public function getDisplayNameAttribute()
    {
        return "{$this->account_name} ({$this->masked_account_number})";
    }

    // Record transaction and update balance
    public function recordTransaction($type, $amount, $description, $data = [])
    {
        $balanceBefore = $this->current_balance;

        if ($type === 'debit') {
            $this->current_balance -= $amount;
        } else {
            $this->current_balance += $amount;
        }

        $this->save();

        return $this->transactions()->create([
            'bank_account_id' => $this->id, // ✅ Explicitly set this
            'transaction_date' => $data['transaction_date'] ?? now(),
            'type' => $type,
            'amount' => $amount,
            'category' => $data['category'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'description' => $description,
            'transactionable_type' => $data['transactionable_type'] ?? null,
            'transactionable_id' => $data['transactionable_id'] ?? null,
            'transfer_to_account_id' => $data['transfer_to_account_id'] ?? null,
            'balance_after' => $this->current_balance,
        ]);
    }
    
    // Boot method to ensure only one default account
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($account) {
            if ($account->is_default) {
                static::where('company_profile_id', $account->company_profile_id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        static::updating(function ($account) {
            if ($account->is_default && $account->isDirty('is_default')) {
                static::where('company_profile_id', $account->company_profile_id)
                    ->where('id', '!=', $account->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }
}
