<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Challan extends Model
{
    protected $fillable = ['challan_number', 'challan_date', 'supplier_id', 'remarks'];

    protected $casts = [
        'challan_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(ChallanItem::class);
    }

    protected static function booted()
    {
        static::deleting(function ($challan) {
            // 1. Reverse Stock Movements for each item
            foreach ($challan->items as $item) {
                // Find original in-movement
                $originalMovement = StockMovement::where('reference_type', ChallanItem::class)
                    ->where('reference_id', $item->id)
                    ->where('type', 'in')
                    ->first();

                if ($originalMovement) {
                    // Reduce product stock
                    $product = $item->product;
                    if ($product) {
                        $product->stock_quantity -= $item->quantity;
                        $product->save();
                        
                        // Create reversing "out" movement
                        StockMovement::create([
                            'product_id' => $product->id,
                            'type' => 'out',
                            'quantity' => $item->quantity,
                            'reason' => 'purchase_reversed',
                            'reference_type' => ChallanItem::class,
                            'reference_id' => $item->id, // Maintain reference to origin
                        ]);
                    }
                }
                
                // Delete the item
                $item->delete();
            }

            // 2. Reverse Ledger Transactions
            $challanTransactions = LedgerTransaction::where('referenceable_type', static::class)
                ->where('referenceable_id', $challan->id)
                ->get();

            foreach ($challanTransactions as $transaction) {
                $transaction->ledger->transactions()->create([
                    'date' => now(),
                    'type' => 'adjustment',
                    'description' => "Reversal of Purchase: Challan #" . $challan->challan_number,
                    'debit_amount' => $transaction->credit_amount,
                    'credit_amount' => $transaction->debit_amount,
                    'reference' => "REV-" . ($transaction->reference ?? $challan->challan_number),
                    'referenceable_type' => static::class,
                    'referenceable_id' => $challan->id,
                ]);

                // Adjust balance
                $transaction->ledger->current_balance += ($transaction->credit_amount - $transaction->debit_amount);
                $transaction->ledger->save();
            }

            Log::info("Challan {$challan->challan_number} and associated impacts reversed.");
        });
    }
}
