<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY status ENUM('pending', 'accepted', 'in_progress', 'rejected', 'completed', 'cancelled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('bookings')
            ->where('status', 'in_progress')
            ->update(['status' => 'accepted']);

        DB::statement("ALTER TABLE bookings MODIFY status ENUM('pending', 'accepted', 'rejected', 'completed', 'cancelled') DEFAULT 'pending'");
    }
};
