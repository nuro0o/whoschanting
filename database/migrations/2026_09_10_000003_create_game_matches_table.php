<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('game_room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rules_version');
            $table->unsignedTinyInteger('player_count');
            $table->string('mission');
            $table->string('winner');
            $table->string('win_reason');
            $table->unsignedInteger('nights');
            $table->unsignedInteger('ritual_goal');
            $table->unsignedInteger('ritual_steps');
            $table->unsignedInteger('missed_night_actions');
            $table->unsignedInteger('missed_votes');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('recap_complete');
            $table->json('recap');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at');
            $table->index(['rules_version', 'player_count', 'mission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_matches');
    }
};
