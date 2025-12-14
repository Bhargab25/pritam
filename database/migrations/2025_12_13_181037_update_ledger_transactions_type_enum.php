<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we need to alter the enum
        DB::statement("ALTER TABLE ledger_transactions MODIFY COLUMN type ENUM('purchase', 'payment', 'return', 'adjustment', 'opening', 'sale', 'receipt') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE ledger_transactions MODIFY COLUMN type ENUM('purchase', 'payment', 'return', 'adjustment', 'opening', 'sale') NOT NULL");
    }
};
