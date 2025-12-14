<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Add coolie expense and payment method fields
            $table->decimal('coolie_expense', 10, 2)->default(0)->after('total_amount');
            $table->decimal('final_amount', 15, 2)->default(0)->after('coolie_expense'); // total_amount + coolie_expense
            $table->enum('payment_method', ['cash', 'bank', 'upi', 'card', 'cheque'])->nullable()->after('final_amount');
            $table->foreignId('bank_account_id')->nullable()->constrained('company_bank_accounts')->onDelete('set null')->after('payment_method');
        });
        
        // Update existing invoices to set final_amount = total_amount
        DB::statement('UPDATE invoices SET final_amount = total_amount WHERE final_amount = 0');
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn(['coolie_expense', 'final_amount', 'payment_method', 'bank_account_id']);
        });
    }
};
