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
        Schema::create('purchase_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('approver_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('decision', 20);
            $table->text('comments')->nullable();
            $table->timestamp('decided_at')->useCurrent();
            $table->timestamps();

            $table->index(['purchase_request_id', 'decided_at']);
            $table->index(['approver_user_id', 'decided_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_request_approvals');
    }
};
