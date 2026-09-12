<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paid_orders', function (Blueprint $table): void {
            $table->string('bundle_name')->nullable();
            $table->unsignedInteger('total_amount')->nullable();
            $table->json('receipt_payload')->nullable();
            $table->timestamp('receipt_queued_at')->nullable();
            $table->timestamp('receipt_sent_at')->nullable();
            $table->timestamp('repurchase_reviewed_at')->nullable();
        });
        Schema::create('paid_pack_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('paid_order_id')->constrained('paid_orders')->cascadeOnDelete();
            $table->uuid('match_id');
            $table->json('cosmetics');
            $table->timestamp('used_at');
            $table->unique(['paid_order_id', 'match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_pack_usages');
        Schema::table('paid_orders', fn (Blueprint $table) => $table->dropColumn(['bundle_name', 'total_amount', 'receipt_payload', 'receipt_queued_at', 'receipt_sent_at', 'repurchase_reviewed_at']));
    }
};
