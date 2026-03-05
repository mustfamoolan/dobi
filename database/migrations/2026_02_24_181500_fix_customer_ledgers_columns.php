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
        Schema::table('customer_ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('customer_ledgers', 'transaction_id')) {
                $table->renameColumn('transaction_id', 'ref_id');
            }
            if (Schema::hasColumn('customer_ledgers', 'transaction_type')) {
                $table->renameColumn('transaction_type', 'ref_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_ledgers', function (Blueprint $table) {
            if (Schema::hasColumn('customer_ledgers', 'ref_id')) {
                $table->renameColumn('ref_id', 'transaction_id');
            }
            if (Schema::hasColumn('customer_ledgers', 'ref_type')) {
                $table->renameColumn('ref_type', 'transaction_type');
            }
        });
    }
};
