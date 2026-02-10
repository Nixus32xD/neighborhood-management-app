<?php

namespace Database\Seeders;

use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Neighborhood::create(
            ['name' => 'CC1'],
        );
        Neighborhood::create(
            ['name' => 'CC2'],
        );

        $this->call([
            OwnersSeeder::class,
        ]);
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
