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
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->foreignId('material_category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('unit_of_measure_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();
            $table->decimal('minimum_stock_qty', 14, 3);
            $table->decimal('reorder_to_qty', 14, 3);
            $table->unsignedInteger('shelf_life_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('material_category_id');
            $table->index('unit_of_measure_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
