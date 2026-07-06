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
            ['email' => 'admin@kanewedding.test'],
            [
                'name' => 'Kane Wedding Admin',
                'password' => Hash::make('KaneWedding@2026!#'),
            ],
        );
        $admin->syncRoles('admin');

        $editor = User::updateOrCreate(
            ['email' => 'editor@kanewedding.test'],
            [
                'name' => 'Kane Wedding Editor',
                'password' => Hash::make('EditorWedding@2026!!'),
            ],
        );
        $editor->syncRoles('editor');

        $this->call([
            DemoInvitationSeeder::class,
        ]);
    }
}
