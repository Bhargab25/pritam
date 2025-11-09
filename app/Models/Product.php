<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'category_id', 'unit', 'stock_quantity','min_stock_quantity', 'is_active'];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function challanItems()
    {
        return $this->hasMany(ChallanItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function getCurrentStockAttribute()
    {
        $in = $this->stockMovements()->where('type', 'in')->sum('quantity');
        $out = $this->stockMovements()->where('type', 'out')->sum('quantity');
        return $in - $out;
    }

    public function isLowStock()
    {
        return $this->stock_quantity <= $this->min_stock_quantity && $this->stock_quantity > 0;
    }
    public function getStockStatusAttribute()
    {
        if ($this->stock_quantity == 0) {
            return 'Out of Stock';
        } elseif ($this->isLowStock()) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }

    public function getStockStatusColorAttribute()
    {
        if ($this->stock_quantity == 0) {
            return 'badge-error';
        } elseif ($this->isLowStock()) {
            return 'badge-warning';
        } else {
            return 'badge-success';
        }
    }
}
