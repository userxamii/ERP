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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 64)->unique();
            $table->foreignId('supplier_id')
                ->constrained()
                ->restrictOnDelete();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->char('currency_code', 3);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'order_date']);
            $table->index(['supplier_id', 'order_date']);
            $table->index('expected_delivery_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
