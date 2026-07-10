<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'transaction_id')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('transaction_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'transaction_id')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('transaction_id')->nullable()->after('payment_proof');
        });
    }
};
