<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Pre-provisions staff by email. The firebase_uid is filled in automatically
 * on first successful Firebase sign-in (see FirebaseAuthService). This keeps
 * the panel invite-only: only seeded emails can log in unless
 * FIREBASE_AUTO_PROVISION=true.
 *
 * Create the matching accounts in the Firebase console (Auth > Users) with the
 * same emails, or let them sign in with Google using these addresses.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $staff = [
            ['name' => 'Administrador', 'email' => 'admin@humaredaprime.com', 'role' => 'admin'],
            // Add real staff emails here:
            // ['name' => 'Recepción', 'email' => 'recepcion@humaredaprime.com', 'role' => 'staff'],
        ];

        foreach ($staff as $s) {
            User::updateOrCreate(
                ['email' => $s['email']],
                [
                    'name'         => $s['name'],
                    'role'         => $s['role'],
                    'firebase_uid' => 'pending-'.\Illuminate\Support\Str::uuid(), // replaced on first login
                    'is_active'    => true,
                ],
            );
        }
    }
}
