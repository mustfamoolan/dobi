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
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('opening_balance_iqd', 15, 3)->default(0)->after('address');
            $table->decimal('opening_balance_usd', 15, 3)->default(0)->after('opening_balance_iqd');
            $table->dropColumn(['opening_balance', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            //
        });
    }
};
