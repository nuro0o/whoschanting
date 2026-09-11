<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('xp')->default(0);
            $table->unsignedInteger('matches')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('town_wins')->default(0);
            $table->unsignedInteger('cult_wins')->default(0);
            $table->json('roles_played');
            $table->json('achievements');
            $table->json('customization');
            $table->timestamps();
        });
        Schema::create('player_seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('season_id', 16);
            $table->unsignedBigInteger('xp')->default(0);
            $table->unsignedInteger('matches')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'season_id']);
        });
        Schema::create('match_rewards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('match_id')->constrained('game_matches')->cascadeOnDelete();
            $table->json('data');
            $table->timestamps();
            $table->unique(['user_id', 'match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_rewards');
        Schema::dropIfExists('player_seasons');
        Schema::dropIfExists('player_profiles');
    }
};
