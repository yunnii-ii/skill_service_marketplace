<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('email');
            $table->string('company_name')->nullable()->after('phone_number');
            $table->string('position')->nullable()->after('company_name');

            // Admin Approval
            $table->boolean('is_approved')->default(false)->after('is_banned');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'company_name', 'position', 'is_approved']);
        });
    }
};
