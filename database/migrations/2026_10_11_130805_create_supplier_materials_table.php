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
        Schema::create('supplier_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('material_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('supplier_sku', 100)->nullable();
            $table->unsignedInteger('lead_time_days');
            $table->decimal('minimum_order_qty', 14, 3);
            $table->decimal('order_multiple_qty', 14, 3)->nullable();
            $table->decimal('quoted_unit_price', 14, 4)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['supplier_id', 'material_id']);
            $table->index(['material_id', 'is_active', 'is_preferred']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_materials');
    }
};
