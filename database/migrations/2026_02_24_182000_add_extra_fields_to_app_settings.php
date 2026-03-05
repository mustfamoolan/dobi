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
        Schema::table('app_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('app_settings', 'company_name_en')) {
                $table->string('company_name_en')->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('app_settings', 'description')) {
                $table->string('description')->nullable()->after('company_name_en');
            }
            if (!Schema::hasColumn('app_settings', 'address')) {
                $table->string('address')->nullable()->after('description');
            }
            if (!Schema::hasColumn('app_settings', 'phone')) {
                $table->string('phone')->nullable()->after('address');
            }
            if (!Schema::hasColumn('app_settings', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['company_name_en', 'description', 'address', 'phone', 'email']);
        });
    }
};
