<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Member;
use App\Models\Note;
use App\Models\Problem;
use App\Models\Resource;
use App\Models\Task;
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
        // Seed all role-based users first
        $this->call(RoleSeeder::class);

        // Also keep the legacy test user for backward compatibility
        $testUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'role' => UserRole::Admin,
            ]
        );

        // Retrieve the seeded role users for realistic data relationships
        $admin = User::where('email', 'admin@ecmtrack.test')->first();
        $careManager = User::where('email', 'caremanager@ecmtrack.test')->first();
        $supervisor = User::where('email', 'supervisor@ecmtrack.test')->first();
        $clinician = User::where('email', 'clinician@ecmtrack.test')->first();
        $chw = User::where('email', 'chw@ecmtrack.test')->first();

        $careStaff = collect([$careManager, $supervisor, $clinician, $chw])->filter();

        // Create members with assigned lead care managers
        $members = Member::factory(10)
            ->sequence(fn ($sequence) => [
                'lead_care_manager' => $careStaff->random()->id,
            ])
            ->create();

        // One JI-blocked member
        Member::factory()->jiBlocked()->create([
            'lead_care_manager' => $careManager->id,
        ]);

        foreach ($members as $member) {
            // Each member gets 1-3 problems in various states
            $addedProblems = Problem::factory(rand(1, 2))
                ->for($member)
                ->create(['submitted_by' => $careManager->id]);

            $confirmedProblems = Problem::factory(rand(1, 2))
                ->confirmed()
                ->for($member)
                ->create([
                    'submitted_by' => $careManager->id,
                    'confirmed_by' => $supervisor->id,
                ]);

            // Some members have resolved problems
            if (rand(0, 1)) {
                Problem::factory()
                    ->resolved()
                    ->for($member)
                    ->create([
                        'submitted_by' => $careManager->id,
                        'confirmed_by' => $supervisor->id,
                        'resolved_by' => $clinician->id,
                    ]);
            }

            // Add tasks to confirmed problems
            foreach ($confirmedProblems as $problem) {
                Task::factory(rand(1, 3))
                    ->for($problem)
                    ->create(['submitted_by' => $careManager->id]);

                // Some started tasks with resources
                $startedTasks = Task::factory(rand(0, 2))
                    ->started()
                    ->for($problem)
                    ->create([
                        'submitted_by' => $careManager->id,
                        'started_by' => $supervisor->id,
                    ]);

                foreach ($startedTasks as $task) {
                    Resource::factory(rand(1, 2))->create([
                        'task_id' => $task->id,
                        'submitted_by' => $careStaff->random()->id,
                    ]);
                }

                // Some completed tasks
                if (rand(0, 1)) {
                    Task::factory()
                        ->completed()
                        ->for($problem)
                        ->create([
                            'submitted_by' => $careManager->id,
                            'started_by' => $supervisor->id,
                            'completed_by' => $clinician->id,
                        ]);
                }

                // A goal task per problem
                Task::factory()
                    ->goal()
                    ->for($problem)
                    ->create(['submitted_by' => $careManager->id]);

                // Add notes to problems
                Note::factory(rand(1, 3))->create([
                    'notable_type' => Problem::class,
                    'notable_id' => $problem->id,
                    'created_by' => $careStaff->random()->id,
                ]);
            }
        }
    }
}
