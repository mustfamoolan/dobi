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
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('account_ledgers', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::table('account_ledgers', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreign('created_by')->references('id')->on('users');
        });
    }
};
