<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_profiles', function (Blueprint $table): void {
            $table->timestamp('crown_cooldown_until')->nullable();
            $table->json('rapid_crown_matches')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('player_profiles', function (Blueprint $table): void {
            $table->dropColumn(['crown_cooldown_until', 'rapid_crown_matches']);
        });
    }
};
