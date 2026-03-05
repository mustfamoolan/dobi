<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->enum('type', ['receipt', 'payment']); // receipt = money in, payment = money out
            $table->string('account_type'); // customer, supplier, employee
            $table->unsignedBigInteger('account_id');
            $table->decimal('amount', 15, 3);
            $table->string('currency', 10)->default('IQD');
            $table->decimal('exchange_rate', 15, 3)->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
