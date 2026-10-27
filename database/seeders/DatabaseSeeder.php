<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ProcurementProductSeeder::class);

        if (User::query()->exists()) {
            $this->call(SampleErpDataSeeder::class);

            return;
        }

        $this->command?->warn(
            'Sample ERP transactions were not seeded because there are no existing users. Create a real user and run db:seed again.',
        );
    }
}
