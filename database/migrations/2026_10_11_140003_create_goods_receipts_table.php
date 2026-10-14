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
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 64)->unique();
            $table->foreignId('delivery_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('purchase_order_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('received_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->string('status', 16)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'received_at']);
            $table->index(['delivery_id', 'status']);
            $table->index(['purchase_order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
