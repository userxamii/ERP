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
        Schema::create('purchase_order_item_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_item_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('purchase_request_item_id')
                ->constrained()
                ->restrictOnDelete();
            $table->decimal('allocated_qty', 14, 3);
            $table->timestamps();

            $table->unique(['purchase_order_item_id', 'purchase_request_item_id']);
            $table->index('purchase_request_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_item_allocations');
    }
};
