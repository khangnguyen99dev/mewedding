<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
        ]);

        $admin = User::updateOrCreate(
            ['email' => 'admin@mewedding.test'],
            [
                'name' => 'meWedding Admin',
                'password' => Hash::make('password'),
            ],
        );
        $admin->syncRoles('admin');

        $editor = User::updateOrCreate(
            ['email' => 'editor@mewedding.test'],
            [
                'name' => 'meWedding Editor',
                'password' => Hash::make('password'),
            ],
        );
        $editor->syncRoles('editor');

        $this->call([
            DemoInvitationSeeder::class,
        ]);
    }
}
