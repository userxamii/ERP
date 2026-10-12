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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('inventory_batch_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('material_stock_reservation_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('movement_type', 32);
            $table->decimal('quantity', 14, 3);
            $table->decimal('on_hand_delta', 14, 3)->default(0);
            $table->decimal('reserved_delta', 14, 3)->default(0);
            $table->string('source_reference', 120)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['goods_receipt_item_id', 'movement_type']);
            $table->unique(['material_stock_reservation_id', 'movement_type']);
            $table->index(['material_id', 'occurred_at']);
            $table->index(['inventory_batch_id', 'occurred_at']);
            $table->index(['movement_type', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
