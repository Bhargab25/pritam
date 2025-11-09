<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
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
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function ledger()
    {
        return $this->morphOne(AccountLedger::class, 'ledgerable');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    // Accessors
    public function getStatusAttribute()
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }
}
