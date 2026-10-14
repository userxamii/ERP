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
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('lot_code', 100);
            $table->timestamp('received_at');
            $table->date('expires_at')->nullable();
            $table->decimal('quantity_received', 14, 3);
            $table->decimal('quantity_on_hand', 14, 3);
            $table->string('quality_status', 20)->default('available');
            $table->timestamps();

            $table->unique(['material_id', 'lot_code']);
            $table->index(['material_id', 'expires_at']);
            $table->index(['quality_status', 'expires_at']);
        });

        Schema::create('material_stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')
                ->constrained()
                ->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('status', 20)->default('active');
            $table->string('source_reference')->nullable();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('released_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['material_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_stock_reservations');
        Schema::dropIfExists('inventory_batches');
    }
};
