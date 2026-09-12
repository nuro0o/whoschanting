<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('terms_version', 40)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
        });
        Schema::table('paid_orders', function (Blueprint $table): void {
            $table->json('legal_acceptance')->nullable();
        });
        Schema::create('withdrawal_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('order_reference');
            $table->text('message')->nullable();
            $table->text('declaration');
            $table->timestamp('acknowledgment_queued_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
        Schema::table('paid_orders', fn (Blueprint $table) => $table->dropColumn('legal_acceptance'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['terms_version', 'terms_accepted_at']));
    }
};
