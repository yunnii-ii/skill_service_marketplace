<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_proof')->nullable()->after('payment_status');
            // $table->string('transaction_id')->nullable()->after('payment_proof');
        });
    }

    public function down(): void
    {
        $columns = array_filter([
            Schema::hasColumn('bookings', 'payment_proof') ? 'payment_proof' : null,
            Schema::hasColumn('bookings', 'transaction_id') ? 'transaction_id' : null,
        ]);

        if ($columns === []) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn($columns);
        });
    }
};
