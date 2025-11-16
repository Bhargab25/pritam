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
        Schema::create('company_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_profile_id')->constrained('company_profiles')->onDelete('cascade');
            $table->string('account_name'); // e.g., "Main Operating Account"
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('ifsc_code');
            $table->string('branch_name')->nullable();
            $table->enum('account_type', ['savings', 'current', 'overdraft', 'cash_credit'])->default('current');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Bank transactions table
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('company_bank_accounts')->onDelete('cascade');
            $table->date('transaction_date');
            $table->enum('type', ['debit', 'credit', 'transfer']);
            $table->decimal('amount', 15, 2);
            $table->string('category')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('description');
            
            // Polymorphic relation to link to invoices, payments, etc.
            $table->nullableMorphs('transactionable');
            
            // For transfers between accounts
            $table->foreignId('transfer_to_account_id')->nullable()->constrained('company_bank_accounts');
            
            $table->decimal('balance_after', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('company_bank_accounts');
    }
};
