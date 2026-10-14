<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('procurement_activities', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 160)->unique();
            $table->string('activity_type', 48);
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('purchase_request_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('delivery_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('goods_receipt_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('inventory_movement_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->string('description', 500);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['activity_type', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['purchase_order_id', 'occurred_at']);
            $table->index(['delivery_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_activities');
    }
};
