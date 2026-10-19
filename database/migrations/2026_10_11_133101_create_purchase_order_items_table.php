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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('material_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();
            $table->foreignId('supplier_material_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->decimal('ordered_qty', 14, 3);
            $table->decimal('unit_price', 14, 4);
            $table->decimal('line_total', 28, 7);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'material_id']);
            $table->index(['supplier_material_id', 'purchase_order_id']);
            $table->index('unit_of_measure_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
