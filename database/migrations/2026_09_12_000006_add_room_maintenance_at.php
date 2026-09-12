<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_rooms', function (Blueprint $table): void {
            $table->timestamp('maintenance_at')->nullable()->index();
        });
        // Schedule existing rooms without overwriting any live match state.
        DB::table('game_rooms')->update(['maintenance_at' => now()->addSeconds(45)]);
    }

    public function down(): void
    {
        Schema::table('game_rooms', function (Blueprint $table): void {
            $table->dropIndex(['maintenance_at']);
            $table->dropColumn('maintenance_at');
        });
    }
};
