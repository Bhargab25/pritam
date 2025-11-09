<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('challans', function (Blueprint $table) {
            $table->id();
            $table->string('challan_number')->unique();
            $table->date('challan_date');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('challan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challan_id')->constrained('challans')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('quantity', 10, 2);
            $table->decimal('price', 10, 2)->nullable(); // optional
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->enum('type', ['in', 'out']); // stock in / stock out
            $table->decimal('quantity', 10, 2);
            $table->string('reason')->nullable(); // purchase, sale, defect, adjustment, etc.
            $table->morphs('reference');
            // This links to challan_items, sales_items, or defect_records (polymorphic)
            $table->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->enum('adjustment_type', ['defect', 'expiry', 'manual']);
            $table->decimal('quantity', 10, 2);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('challan_items');
        Schema::dropIfExists('challans');
    }
};
