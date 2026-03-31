<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    /**
     * Seed one user per role with predictable credentials.
     *
     * Email pattern: {role}@ecmtrack.test
     * Password: password (all users)
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@ecmtrack.test',
                'role' => UserRole::Admin,
            ],
            [
                'name' => 'Care Manager',
                'email' => 'caremanager@ecmtrack.test',
                'role' => UserRole::CareManager,
            ],
            [
                'name' => 'Supervisor User',
                'email' => 'supervisor@ecmtrack.test',
                'role' => UserRole::Supervisor,
            ],
            [
                'name' => 'Authorized Clinician',
                'email' => 'clinician@ecmtrack.test',
                'role' => UserRole::AuthorizedClinician,
            ],
            [
                'name' => 'Community Health Worker',
                'email' => 'chw@ecmtrack.test',
                'role' => UserRole::CommunityHealthWorker,
            ],
            [
                'name' => 'Compliance Officer',
                'email' => 'compliance@ecmtrack.test',
                'role' => UserRole::ComplianceOfficer,
            ],
        ];

        foreach ($users as $userData) {
            User::factory()->create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('password'),
                'role' => $userData['role'],
            ]);
        }
    }
}
