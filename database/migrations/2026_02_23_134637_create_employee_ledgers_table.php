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
        Schema::create('employee_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->string('type'); // salary, commission, payment
            $table->text('description')->nullable();
            $table->decimal('debit', 15, 3)->default(0);  // Payments to employee
            $table->decimal('credit', 15, 3)->default(0); // Earnings (salary/commission)
            $table->decimal('balance', 15, 3)->default(0);
            $table->string('ref_type')->nullable(); // sale, payment, etc
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_ledgers');
    }
};
