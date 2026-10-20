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
        Schema::create('production_material_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 64)->unique();
            $table->foreignId('material_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('requested_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('production_order', 120)->nullable();
            $table->text('purpose');
            $table->date('required_date')->nullable();
            $table->decimal('requested_qty', 14, 3);
            $table->decimal('fulfilled_qty', 14, 3)->default('0.000');
            $table->string('status', 32)->default('pending_approval');
            $table->text('decision_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'required_date']);
            $table->index(['requested_by_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_material_requests');
    }
};
