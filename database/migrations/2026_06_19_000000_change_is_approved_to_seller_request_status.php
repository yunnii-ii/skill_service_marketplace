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
        DB::statement('ALTER TABLE users MODIFY is_approved TINYINT DEFAULT 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->where('is_approved', 2)
            ->update(['is_approved' => 0]);

        DB::statement('ALTER TABLE users MODIFY is_approved BOOLEAN DEFAULT false');
    }
};
