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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number', 64)->unique();
            $table->foreignId('purchase_order_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('supplier_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('supplier_delivery_reference', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->date('dispatch_date')->nullable();
            $table->date('expected_arrival_date')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('actual_arrival_at')->nullable();
            $table->string('status', 24)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'expected_arrival_date']);
            $table->index(['purchase_order_id', 'status']);
            $table->index(['supplier_id', 'dispatch_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
