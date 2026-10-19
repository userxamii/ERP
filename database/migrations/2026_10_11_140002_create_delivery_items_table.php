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
        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')
                ->constrained()
                ->restrictOnDelete();
            $table->decimal('dispatched_qty', 14, 3);
            $table->timestamps();

            $table->unique(['delivery_id', 'purchase_order_item_id']);
            $table->index('purchase_order_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
    }
};
