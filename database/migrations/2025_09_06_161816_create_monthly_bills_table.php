<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->date('bill_date');
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('total_amount', 15, 2);
            $table->integer('invoice_count');
            $table->enum('status', ['draft', 'sent', 'paid'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_bills');
    }
};
