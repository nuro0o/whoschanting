<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_profiles', function (Blueprint $table): void {
            $table->unsignedBigInteger('coins')->default(0);
            $table->unsignedBigInteger('coins_earned')->default(0);
        });

        Schema::create('store_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_id', 80);
            $table->unsignedBigInteger('price');
            $table->timestamps();
            $table->unique(['user_id', 'item_id']);
        });

        Schema::create('coin_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('reference', 120);
            $table->string('item_id', 80)->nullable();
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_after');
            $table->timestamps();
            $table->unique(['user_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_transactions');
        Schema::dropIfExists('store_purchases');
        Schema::table('player_profiles', function (Blueprint $table): void {
            $table->dropColumn(['coins', 'coins_earned']);
        });
    }
};
