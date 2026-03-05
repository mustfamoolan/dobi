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
        $tables = ['sales', 'purchases', 'customer_ledgers', 'supplier_ledgers', 'employee_ledgers'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::hasColumn($tableName, 'currency')) {
                        $table->string('currency', 10)->default('IQD')->after('id');
                    }
                    if (!Schema::hasColumn($tableName, 'exchange_rate')) {
                        $table->decimal('exchange_rate', 15, 3)->default(1)->after('currency');
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['sales', 'purchases', 'customer_ledgers', 'supplier_ledgers', 'employee_ledgers'];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['currency', 'exchange_rate']);
            });
        }
    }
};
