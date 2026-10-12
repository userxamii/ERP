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
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('delivery_item_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')
                ->constrained()
                ->restrictOnDelete();
            $table->decimal('accepted_qty', 14, 3)->default(0);
            $table->decimal('rejected_qty', 14, 3)->default(0);
            $table->string('lot_code', 100)->nullable();
            $table->date('expires_at')->nullable();
            $table->text('discrepancy_notes')->nullable();
            $table->timestamps();

            $table->unique(['goods_receipt_id', 'delivery_item_id']);
            $table->index(['purchase_order_item_id', 'goods_receipt_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};
