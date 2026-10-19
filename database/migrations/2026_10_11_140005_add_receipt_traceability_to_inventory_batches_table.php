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
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->string('lot_code', 100)->nullable()->change();
            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('purchase_order_item_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('received_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropForeign(['goods_receipt_item_id']);
            $table->dropUnique(['goods_receipt_item_id']);
            $table->dropForeign(['purchase_order_item_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['received_by_user_id']);
            $table->dropColumn([
                'goods_receipt_item_id',
                'purchase_order_item_id',
                'supplier_id',
                'received_by_user_id',
            ]);
            $table->string('lot_code', 100)->nullable(false)->change();
        });
    }
};
