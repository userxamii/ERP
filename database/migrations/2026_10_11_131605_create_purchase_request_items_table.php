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
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('material_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')
                ->constrained('units_of_measure')
                ->restrictOnDelete();
            $table->decimal('requested_qty', 14, 3);
            $table->text('justification')->nullable();
            $table->timestamps();

            $table->unique(['purchase_request_id', 'material_id']);
            $table->index('material_id');
            $table->index('unit_of_measure_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
