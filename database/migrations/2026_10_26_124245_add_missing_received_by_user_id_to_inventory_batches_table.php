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
        if (! Schema::hasColumn('inventory_batches', 'received_by_user_id')) {
            Schema::table('inventory_batches', function (Blueprint $table) {
                $table->foreignId('received_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('inventory_batches', 'received_by_user_id')) {
            Schema::table('inventory_batches', function (Blueprint $table) {
                $table->dropConstrainedForeignId('received_by_user_id');
            });
        }
    }
};
