<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paid_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bundle_id', 80);
            $table->string('status', 20)->default('pending');
            $table->string('price_id');
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->json('cosmetics');
            $table->json('checkout_parameters');
            $table->string('stripe_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'bundle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_orders');
    }
};
