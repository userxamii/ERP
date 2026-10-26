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
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->date('manufactured_at')->nullable()->after('received_at');
            $table->string('storage_location', 120)->nullable()->after('expires_at');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('source_location', 120)->nullable()->after('source_reference');
            $table->string('destination_location', 120)->nullable()->after('source_location');
            $table->text('reason')->nullable()->after('destination_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn(['source_location', 'destination_location', 'reason']);
        });

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropColumn(['manufactured_at', 'storage_location']);
        });
    }
};
