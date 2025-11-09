<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->enum('invoice_type', ['cash', 'client'])->default('cash');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            
            // Client Information
            $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('set null');
            $table->string('client_name')->nullable();
            $table->string('client_phone')->nullable();
            $table->text('client_address')->nullable();
            
            // GST Information
            $table->boolean('is_gst_invoice')->default(false);
            $table->string('client_gstin', 15)->nullable();
            $table->string('place_of_supply')->nullable();
            $table->enum('gst_type', ['cgst_sgst', 'igst'])->nullable();
            
            // Financial Details
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_amount', 15, 2)->default(0);
            
            // Status and Tracking
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'overdue'])->default('unpaid');
            $table->boolean('is_monthly_billed')->default(false);
            $table->unsignedBigInteger('monthly_bill_id')->nullable(); // Will add FK later
            $table->boolean('is_cancelled')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            // Additional Information
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->string('created_by')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['invoice_date', 'client_id']);
            $table->index(['payment_status', 'is_monthly_billed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
