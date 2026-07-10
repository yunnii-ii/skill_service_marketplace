<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('order_note');
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid')->after('payment_method');
            $table->timestamp('buyer_accepted_at')->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('buyer_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_status',
                'buyer_accepted_at',
                'paid_at',
            ]);
        });
    }
};
