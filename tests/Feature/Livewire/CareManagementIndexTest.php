<?php

namespace Tests\Feature\Livewire;

use App\Enums\ProblemState;
use App\Enums\ProblemType;
use App\Enums\UserRole;
use App\Livewire\CareManagement\CareManagementIndex;
use App\Models\Member;
use App\Models\Problem;
use App\Models\StateChangeHistory;
use App\Models\Task;
use App\Models\User;
use App\Enums\TaskCompletionType;
use App\Enums\TaskState;
use App\Models\Note;
use App\Notifications\ProblemAddedNotification;
use App\Notifications\ProblemConfirmedNotification;
use App\Notifications\ProblemResolvedNotification;
use App\Notifications\ProblemUnconfirmedNotification;
use App\Notifications\ProblemUnresolvedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CareManagementIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->member = Member::factory()->create();
    }

    public function test_page_renders_for_authenticated_user(): void
    {
        $this->actingAs($this->user)
            ->get(route('care-management.index', $this->member))
            ->assertStatus(200);
    }

    public function test_page_redirects_unauthenticated_user(): void
    {
        $this->get(route('care-management.index', $this->member))
            ->assertRedirect('/login');
    }

    public function test_member_info_is_displayed(): void
    {
        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->assertSee($this->member->name)
            ->assertSee($this->member->member_id)
            ->assertSee($this->member->organization);
    }

    public function test_problems_are_displayed(): void
    {
        $problem = Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Test Headache Problem',
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->assertSee('Test Headache Problem');
    }

    public function test_filter_by_problem_type(): void
    {
        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Physical Problem',
            'type' => ProblemType::Physical,
        ]);

        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Behavioral Problem',
            'type' => ProblemType::Behavioral,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member]);

        // See both initially
        $component->assertSee('Physical Problem')
            ->assertSee('Behavioral Problem');

        // Filter to Physical only
        $component->call('setFilter', ProblemType::Physical->value)
            ->assertSee('Physical Problem')
            ->assertDontSee('Behavioral Problem');

        // Clear filter — see both
        $component->call('clearFilter')
            ->assertSee('Physical Problem')
            ->assertSee('Behavioral Problem');
    }

    public function test_tasks_displayed_under_problems(): void
    {
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        $task = Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'name' => 'Test Task Under Problem',
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->assertSee('Test Task Under Problem');
    }

    public function test_confirm_button_shown_for_added_problems(): void
    {
        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'state' => ProblemState::Added,
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->assertSee('Confirm');
    }

    public function test_resolve_button_shown_for_confirmed_problems(): void
    {
        Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->assertSee('Resolve');
    }

    public function test_add_problem_button_visible(): void
    {
        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->assertSee('Add Problem');
    }

    public function test_category_filters_displayed(): void
    {
        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->assertSee('Physical')
            ->assertSee('Behavioral')
            ->assertSee('SUD')
            ->assertSee('SDOH - Housing')
            ->assertSee('SDOH - Food')
            ->assertSee('SDOH - Transportation')
            ->assertSee('SDOH - Other')
            ->assertSee('All Categories');
    }

    public function test_save_problem_creates_and_refreshes(): void
    {
        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('problemType', \App\Enums\ProblemType::Physical->value)
            ->set('problemName', 'New Problem Via Save')
            ->call('saveProblem')
            ->assertSee('New Problem Via Save');
    }

    // ─── CM-PROB-001: Add a Problem ─────────────────────────────

    public function test_ji_consent_blocked_shows_restriction_message(): void
    {
        $blockedMember = Member::factory()->create([
            'ji_consent_status' => 'no_consent',
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $blockedMember])
            ->assertSee('Access Restricted')
            ->assertDontSee('Add Problem');
    }

    public function test_ji_consent_blocked_prevents_save_problem(): void
    {
        $blockedMember = Member::factory()->create([
            'ji_consent_status' => 'no_consent',
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $blockedMember])
            ->set('problemType', ProblemType::Physical->value)
            ->set('problemName', 'Should Not Save')
            ->call('saveProblem');

        $this->assertDatabaseMissing('problems', ['name' => 'Should Not Save']);
    }

    public function test_save_problem_creates_audit_event(): void
    {
        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('problemType', ProblemType::Physical->value)
            ->set('problemName', 'Audit Test Problem')
            ->call('saveProblem');

        $problem = Problem::where('name', 'Audit Test Problem')->first();
        $this->assertNotNull($problem);

        $this->assertDatabaseHas('state_change_histories', [
            'trackable_type' => Problem::class,
            'trackable_id' => $problem->id,
            'from_state' => null,
            'to_state' => 'added',
            'changed_by' => $this->user->id,
        ]);
    }

    public function test_save_problem_requires_problem_type(): void
    {
        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('problemType', '')
            ->set('problemName', 'Missing Type')
            ->call('saveProblem')
            ->assertHasErrors(['problemType' => 'required']);

        $this->assertDatabaseMissing('problems', ['name' => 'Missing Type']);
    }

    public function test_save_problem_requires_problem_name(): void
    {
        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('problemType', ProblemType::Physical->value)
            ->set('problemName', '')
            ->call('saveProblem')
            ->assertHasErrors(['problemName' => 'required']);
    }

    public function test_problem_is_immutable_no_delete(): void
    {
        $problem = Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
        ]);

        // Component has no deleteProblem method
        $component = Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member]);

        $this->assertFalse(method_exists($component->instance(), 'deleteProblem'));
    }

    public function test_save_problem_sets_submitted_by_and_at(): void
    {
        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('problemType', ProblemType::Behavioral->value)
            ->set('problemName', 'Submitted By Test')
            ->call('saveProblem');

        $problem = Problem::where('name', 'Submitted By Test')->first();
        $this->assertNotNull($problem);
        $this->assertEquals($this->user->id, $problem->submitted_by);
        $this->assertNotNull($problem->submitted_at);
        $this->assertEquals(ProblemState::Added, $problem->state);
    }

    public function test_save_problem_notifies_lead_care_manager(): void
    {
        Notification::fake();

        $leadCm = User::factory()->create();
        $memberWithLead = Member::factory()->create([
            'lead_care_manager' => $leadCm->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $memberWithLead])
            ->set('problemType', ProblemType::Physical->value)
            ->set('problemName', 'Notify Test Problem')
            ->call('saveProblem');

        Notification::assertSentTo($leadCm, ProblemAddedNotification::class);
    }

    public function test_save_problem_no_notification_without_lead_cm(): void
    {
        Notification::fake();

        $memberNoLead = Member::factory()->create([
            'lead_care_manager' => null,
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $memberNoLead])
            ->set('problemType', ProblemType::Physical->value)
            ->set('problemName', 'No Notify Problem')
            ->call('saveProblem');

        Notification::assertNothingSent();
    }

    public function test_save_problem_with_optional_fields_empty(): void
    {
        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('problemType', ProblemType::SDOHHousing->value)
            ->set('problemName', 'Optional Fields Test')
            ->set('problemCode', '')
            ->set('problemEncounterSetting', '')
            ->call('saveProblem');

        $problem = Problem::where('name', 'Optional Fields Test')->first();
        $this->assertNotNull($problem);
        $this->assertNull($problem->code);
        $this->assertNull($problem->encounter_setting);
    }

    public function test_add_problem_button_visible_for_normal_consent(): void
    {
        $normalMember = Member::factory()->create([
            'ji_consent_status' => 'consent',
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $normalMember])
            ->assertSee('Add Problem');
    }

    // ─── CM-PROB-002: Confirm a Problem ─────────────────────────

    public function test_authorized_user_can_confirm_added_problem(): void
    {
        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $problem = Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'state' => ProblemState::Added,
        ]);

        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('confirmProblem', $problem->id);

        $problem->refresh();
        $this->assertEquals(ProblemState::Confirmed, $problem->state);
        $this->assertEquals($careManager->id, $problem->confirmed_by);
        $this->assertNotNull($problem->confirmed_at);
    }

    public function test_chw_cannot_confirm_problem(): void
    {
        $chw = User::factory()->create(['role' => UserRole::CommunityHealthWorker]);
        $problem = Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'state' => ProblemState::Added,
        ]);

        Livewire::actingAs($chw)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('confirmProblem', $problem->id);

        $problem->refresh();
        $this->assertEquals(ProblemState::Added, $problem->state);
    }

    public function test_double_confirm_rejected(): void
    {
        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        // Should not change state — already confirmed, policy returns false
        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('confirmProblem', $problem->id);

        $problem->refresh();
        $this->assertEquals(ProblemState::Confirmed, $problem->state);
    }

    public function test_confirm_blocked_when_locked_by_another_user(): void
    {
        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $otherUser = User::factory()->create();
        $problem = Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'state' => ProblemState::Added,
            'locked_by' => $otherUser->id,
            'locked_at' => now(),
        ]);

        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('confirmProblem', $problem->id);

        $problem->refresh();
        $this->assertEquals(ProblemState::Added, $problem->state);
    }

    public function test_confirm_creates_audit_event(): void
    {
        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $problem = Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'state' => ProblemState::Added,
        ]);

        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('confirmProblem', $problem->id);

        $this->assertDatabaseHas('state_change_histories', [
            'trackable_type' => Problem::class,
            'trackable_id' => $problem->id,
            'from_state' => 'added',
            'to_state' => 'confirmed',
            'changed_by' => $careManager->id,
        ]);
    }

    public function test_confirm_notifies_lead_care_manager(): void
    {
        Notification::fake();

        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $leadCm = User::factory()->create();
        $memberWithLead = Member::factory()->create([
            'lead_care_manager' => $leadCm->id,
        ]);
        $problem = Problem::factory()->create([
            'member_id' => $memberWithLead->id,
            'submitted_by' => $this->user->id,
            'state' => ProblemState::Added,
        ]);

        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $memberWithLead])
            ->call('confirmProblem', $problem->id);

        Notification::assertSentTo($leadCm, ProblemConfirmedNotification::class);
    }

    public function test_supervisor_can_confirm_problem(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'state' => ProblemState::Added,
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('confirmProblem', $problem->id);

        $problem->refresh();
        $this->assertEquals(ProblemState::Confirmed, $problem->state);
    }

    public function test_authorized_clinician_can_confirm_problem(): void
    {
        $clinician = User::factory()->create(['role' => UserRole::AuthorizedClinician]);
        $problem = Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'state' => ProblemState::Added,
        ]);

        Livewire::actingAs($clinician)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('confirmProblem', $problem->id);

        $problem->refresh();
        $this->assertEquals(ProblemState::Confirmed, $problem->state);
    }

    // ─── CM-PROB-003: Unconfirm a Problem ────────────────────────

    public function test_supervisor_can_unconfirm_confirmed_problem(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unconfirmProblemId', $problem->id)
            ->set('unconfirmNote', 'Confirmed in error — wrong member')
            ->call('unconfirmProblem');

        $problem->refresh();
        $this->assertEquals(ProblemState::Added, $problem->state);
        $this->assertNull($problem->confirmed_by);
        $this->assertNull($problem->confirmed_at);
    }

    public function test_authorized_clinician_can_unconfirm_problem(): void
    {
        $clinician = User::factory()->create(['role' => UserRole::AuthorizedClinician]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        Livewire::actingAs($clinician)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unconfirmProblemId', $problem->id)
            ->set('unconfirmNote', 'Need more information')
            ->call('unconfirmProblem');

        $problem->refresh();
        $this->assertEquals(ProblemState::Added, $problem->state);
    }

    public function test_care_manager_cannot_unconfirm_problem(): void
    {
        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unconfirmProblemId', $problem->id)
            ->set('unconfirmNote', 'Should not work')
            ->call('unconfirmProblem');

        $problem->refresh();
        $this->assertEquals(ProblemState::Confirmed, $problem->state);
    }

    public function test_chw_cannot_unconfirm_problem(): void
    {
        $chw = User::factory()->create(['role' => UserRole::CommunityHealthWorker]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        Livewire::actingAs($chw)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unconfirmProblemId', $problem->id)
            ->set('unconfirmNote', 'Should not work')
            ->call('unconfirmProblem');

        $problem->refresh();
        $this->assertEquals(ProblemState::Confirmed, $problem->state);
    }

    public function test_unconfirm_requires_note(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unconfirmProblemId', $problem->id)
            ->set('unconfirmNote', '')
            ->call('unconfirmProblem')
            ->assertHasErrors(['unconfirmNote']);

        $problem->refresh();
        $this->assertEquals(ProblemState::Confirmed, $problem->state);
    }

    public function test_unconfirm_cascades_to_incomplete_tasks(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        $addedTask = Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Added,
        ]);

        $startedTask = Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Started,
            'started_by' => $this->user->id,
            'started_at' => now(),
        ]);

        // Already completed task should remain unchanged
        $completedTask = Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Completed,
            'completion_type' => TaskCompletionType::Completed,
            'completed_by' => $this->user->id,
            'completed_at' => now(),
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unconfirmProblemId', $problem->id)
            ->set('unconfirmNote', 'Unconfirming due to error')
            ->call('unconfirmProblem');

        $addedTask->refresh();
        $startedTask->refresh();
        $completedTask->refresh();

        // Incomplete tasks should be auto-completed
        $this->assertEquals(TaskState::Completed, $addedTask->state);
        $this->assertEquals(TaskCompletionType::ProblemUnconfirmed, $addedTask->completion_type);

        $this->assertEquals(TaskState::Completed, $startedTask->state);
        $this->assertEquals(TaskCompletionType::ProblemUnconfirmed, $startedTask->completion_type);

        // Already-completed task should be unchanged
        $this->assertEquals(TaskCompletionType::Completed, $completedTask->completion_type);
    }

    public function test_unconfirm_creates_audit_events(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        $task = Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Started,
            'started_by' => $this->user->id,
            'started_at' => now(),
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unconfirmProblemId', $problem->id)
            ->set('unconfirmNote', 'Audit test note')
            ->call('unconfirmProblem');

        // PROBLEM_UNCONFIRMED audit event
        $this->assertDatabaseHas('state_change_histories', [
            'trackable_type' => Problem::class,
            'trackable_id' => $problem->id,
            'from_state' => 'confirmed',
            'to_state' => 'added',
            'changed_by' => $supervisor->id,
        ]);

        // TASK_AUTO_COMPLETED audit event
        $this->assertDatabaseHas('state_change_histories', [
            'trackable_type' => Task::class,
            'trackable_id' => $task->id,
            'from_state' => 'started',
            'to_state' => 'completed',
            'changed_by' => $supervisor->id,
        ]);
    }

    public function test_unconfirm_creates_mandatory_note(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unconfirmProblemId', $problem->id)
            ->set('unconfirmNote', 'This was confirmed incorrectly')
            ->call('unconfirmProblem');

        $this->assertDatabaseHas('notes', [
            'notable_type' => Problem::class,
            'notable_id' => $problem->id,
            'content' => 'This was confirmed incorrectly',
            'created_by' => $supervisor->id,
        ]);
    }

    public function test_unconfirm_notifies_lead_care_manager(): void
    {
        Notification::fake();

        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $leadCm = User::factory()->create();
        $memberWithLead = Member::factory()->create([
            'lead_care_manager' => $leadCm->id,
        ]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $memberWithLead->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $memberWithLead])
            ->set('unconfirmProblemId', $problem->id)
            ->set('unconfirmNote', 'Notification test')
            ->call('unconfirmProblem');

        Notification::assertSentTo($leadCm, ProblemUnconfirmedNotification::class, function ($notification) {
            return $notification->note === 'Notification test';
        });
    }

    public function test_unconfirm_blocked_when_locked_by_another(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $otherUser = User::factory()->create();
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
            'locked_by' => $otherUser->id,
            'locked_at' => now(),
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unconfirmProblemId', $problem->id)
            ->set('unconfirmNote', 'Should be blocked')
            ->call('unconfirmProblem');

        $problem->refresh();
        $this->assertEquals(ProblemState::Confirmed, $problem->state);
    }

    public function test_reconfirm_shows_reactivation_dialog_for_cascaded_tasks(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'state' => ProblemState::Added,
        ]);

        // Create a task that was auto-completed via unconfirm
        Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Completed,
            'completion_type' => TaskCompletionType::ProblemUnconfirmed,
            'completed_by' => $supervisor->id,
            'completed_at' => now(),
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('confirmProblem', $problem->id)
            ->assertDispatched('show-reactivation-dialog');

        // Problem should NOT be confirmed yet (waiting for dialog response)
        $problem->refresh();
        $this->assertEquals(ProblemState::Added, $problem->state);
    }

    public function test_reconfirm_with_reactivation_restores_tasks(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'state' => ProblemState::Added,
        ]);

        $cascadedTask = Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Completed,
            'completion_type' => TaskCompletionType::ProblemUnconfirmed,
            'completed_by' => $supervisor->id,
            'completed_at' => now(),
            'started_by' => $this->user->id,
            'started_at' => now()->subHour(),
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('confirmProblem', $problem->id, true);

        $problem->refresh();
        $cascadedTask->refresh();

        $this->assertEquals(ProblemState::Confirmed, $problem->state);
        $this->assertEquals(TaskState::Started, $cascadedTask->state);
        $this->assertNull($cascadedTask->completion_type);
        $this->assertNull($cascadedTask->completed_by);
    }

    public function test_reconfirm_without_reactivation_leaves_tasks_completed(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'state' => ProblemState::Added,
        ]);

        $cascadedTask = Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Completed,
            'completion_type' => TaskCompletionType::ProblemUnconfirmed,
            'completed_by' => $supervisor->id,
            'completed_at' => now(),
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('confirmProblem', $problem->id, false);

        $problem->refresh();
        $cascadedTask->refresh();

        $this->assertEquals(ProblemState::Confirmed, $problem->state);
        $this->assertEquals(TaskState::Completed, $cascadedTask->state);
        $this->assertEquals(TaskCompletionType::ProblemUnconfirmed, $cascadedTask->completion_type);
    }

    public function test_cascaded_tasks_show_unconfirmed_status(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        $task = Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Started,
            'started_by' => $this->user->id,
            'started_at' => now(),
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unconfirmProblemId', $problem->id)
            ->set('unconfirmNote', 'Test cascade display')
            ->call('unconfirmProblem')
            ->assertSee('Complete – Problem Unconfirmed');
    }

    // ─── CM-PROB-004: Resolve a Problem ──────────────────────────

    public function test_authorized_user_can_resolve_confirmed_problem(): void
    {
        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('resolveProblem', $problem->id);

        $problem->refresh();
        $this->assertEquals(ProblemState::Resolved, $problem->state);
        $this->assertEquals($careManager->id, $problem->resolved_by);
        $this->assertNotNull($problem->resolved_at);
    }

    public function test_chw_cannot_resolve_problem(): void
    {
        $chw = User::factory()->create(['role' => UserRole::CommunityHealthWorker]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        Livewire::actingAs($chw)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('resolveProblem', $problem->id);

        $problem->refresh();
        $this->assertEquals(ProblemState::Confirmed, $problem->state);
    }

    public function test_resolve_cascades_incomplete_tasks(): void
    {
        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        $startedTask = Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Started,
            'started_by' => $this->user->id,
            'started_at' => now(),
        ]);

        $completedTask = Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Completed,
            'completion_type' => TaskCompletionType::Completed,
            'completed_by' => $this->user->id,
            'completed_at' => now(),
        ]);

        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('resolveProblem', $problem->id);

        $startedTask->refresh();
        $completedTask->refresh();

        $this->assertEquals(TaskState::Completed, $startedTask->state);
        $this->assertEquals(TaskCompletionType::ProblemResolved, $startedTask->completion_type);

        // Already-completed task unchanged
        $this->assertEquals(TaskCompletionType::Completed, $completedTask->completion_type);
    }

    public function test_resource_can_be_added_after_problem_resolved(): void
    {
        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $problem = Problem::factory()->resolved()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
        ]);

        $task = Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Completed,
            'completion_type' => TaskCompletionType::ProblemResolved,
            'completed_by' => $careManager->id,
            'completed_at' => now(),
            'started_by' => $this->user->id,
            'started_at' => now()->subHour(),
        ]);

        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('resourceTaskId', $task->id)
            ->set('surveyName', 'Post-Resolve Resource')
            ->set('atHome', 'same')
            ->set('atWork', 'better')
            ->set('atPlay', 'worse')
            ->call('saveResource');

        $this->assertDatabaseHas('resources', [
            'task_id' => $task->id,
            'survey_name' => 'Post-Resolve Resource',
        ]);
    }

    public function test_resolve_creates_audit_events(): void
    {
        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        $task = Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Started,
            'started_by' => $this->user->id,
            'started_at' => now(),
        ]);

        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('resolveProblem', $problem->id);

        $this->assertDatabaseHas('state_change_histories', [
            'trackable_type' => Problem::class,
            'trackable_id' => $problem->id,
            'from_state' => 'confirmed',
            'to_state' => 'resolved',
        ]);

        $this->assertDatabaseHas('state_change_histories', [
            'trackable_type' => Task::class,
            'trackable_id' => $task->id,
            'from_state' => 'started',
            'to_state' => 'completed',
        ]);
    }

    public function test_resolve_notifies_lead_care_manager(): void
    {
        Notification::fake();

        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $leadCm = User::factory()->create();
        $memberWithLead = Member::factory()->create(['lead_care_manager' => $leadCm->id]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $memberWithLead->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $memberWithLead])
            ->call('resolveProblem', $problem->id);

        Notification::assertSentTo($leadCm, ProblemResolvedNotification::class);
    }

    public function test_resolve_blocked_when_locked(): void
    {
        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $otherUser = User::factory()->create();
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
            'locked_by' => $otherUser->id,
            'locked_at' => now(),
        ]);

        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('resolveProblem', $problem->id);

        $problem->refresh();
        $this->assertEquals(ProblemState::Confirmed, $problem->state);
    }

    // ─── Unresolve ───

    public function test_supervisor_can_unresolve_with_note(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->resolved()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unresolveProblemId', $problem->id)
            ->set('unresolveNote', 'Problem was not actually resolved')
            ->call('unresolveProblem');

        $problem->refresh();
        $this->assertEquals(ProblemState::Confirmed, $problem->state);
        $this->assertNull($problem->resolved_by);
        $this->assertNull($problem->resolved_at);
    }

    public function test_unresolve_requires_note(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->resolved()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unresolveProblemId', $problem->id)
            ->set('unresolveNote', '')
            ->call('unresolveProblem')
            ->assertHasErrors(['unresolveNote']);

        $problem->refresh();
        $this->assertEquals(ProblemState::Resolved, $problem->state);
    }

    public function test_care_manager_cannot_unresolve(): void
    {
        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $problem = Problem::factory()->resolved()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
        ]);

        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unresolveProblemId', $problem->id)
            ->set('unresolveNote', 'Should not work')
            ->call('unresolveProblem');

        $problem->refresh();
        $this->assertEquals(ProblemState::Resolved, $problem->state);
    }

    public function test_unresolve_creates_note_and_audit(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->resolved()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unresolveProblemId', $problem->id)
            ->set('unresolveNote', 'Unresolve audit test')
            ->call('unresolveProblem');

        $this->assertDatabaseHas('notes', [
            'notable_type' => Problem::class,
            'notable_id' => $problem->id,
            'content' => 'Unresolve audit test',
            'created_by' => $supervisor->id,
        ]);

        $this->assertDatabaseHas('state_change_histories', [
            'trackable_type' => Problem::class,
            'trackable_id' => $problem->id,
            'from_state' => 'resolved',
            'to_state' => 'confirmed',
        ]);
    }

    public function test_unresolve_notifies_lead_care_manager(): void
    {
        Notification::fake();

        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $leadCm = User::factory()->create();
        $memberWithLead = Member::factory()->create(['lead_care_manager' => $leadCm->id]);
        $problem = Problem::factory()->resolved()->create([
            'member_id' => $memberWithLead->id,
            'submitted_by' => $this->user->id,
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $memberWithLead])
            ->set('unresolveProblemId', $problem->id)
            ->set('unresolveNote', 'Notification test')
            ->call('unresolveProblem');

        Notification::assertSentTo($leadCm, ProblemUnresolvedNotification::class, function ($notification) {
            return $notification->note === 'Notification test';
        });
    }

    public function test_unresolve_offers_reactivation_for_cascaded_tasks(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->resolved()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
        ]);

        Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Completed,
            'completion_type' => TaskCompletionType::ProblemResolved,
            'completed_by' => $supervisor->id,
            'completed_at' => now(),
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('unresolveProblemId', $problem->id)
            ->set('unresolveNote', 'Reactivation test')
            ->call('unresolveProblem')
            ->assertDispatched('show-resolve-reactivation-dialog');
    }

    public function test_reactivate_resolved_tasks_restores_state(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $problem = Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
        ]);

        $cascadedTask = Task::factory()->create([
            'problem_id' => $problem->id,
            'submitted_by' => $this->user->id,
            'state' => TaskState::Completed,
            'completion_type' => TaskCompletionType::ProblemResolved,
            'completed_by' => $supervisor->id,
            'completed_at' => now(),
            'started_by' => $this->user->id,
            'started_at' => now()->subHour(),
        ]);

        Livewire::actingAs($supervisor)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->call('reactivateResolvedTasks', $problem->id);

        $cascadedTask->refresh();
        $this->assertEquals(TaskState::Started, $cascadedTask->state);
        $this->assertNull($cascadedTask->completion_type);
        $this->assertNull($cascadedTask->completed_by);
    }

    // ─── CM-PROB-005: Search and Filter ─────────────────────────

    public function test_search_filters_by_problem_name(): void
    {
        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Headache Issue',
        ]);

        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Ankle Pain',
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('search', 'Head')
            ->assertSee('Headache Issue')
            ->assertDontSee('Ankle Pain');
    }

    public function test_search_filters_by_problem_code(): void
    {
        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Problem A',
            'code' => 'ICD-Z99',
        ]);

        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Problem B',
            'code' => 'ICD-J45',
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('search', 'Z99')
            ->assertSee('Problem A')
            ->assertDontSee('Problem B');
    }

    public function test_status_filter_shows_only_matching_state(): void
    {
        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Added Problem',
            'state' => ProblemState::Added,
        ]);

        Problem::factory()->confirmed()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'confirmed_by' => $this->user->id,
            'name' => 'Confirmed Problem',
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('statusFilter', 'added')
            ->assertSee('Added Problem')
            ->assertDontSee('Confirmed Problem');
    }

    public function test_search_and_type_filter_combine(): void
    {
        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Physical Headache',
            'type' => ProblemType::Physical,
        ]);

        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Behavioral Headache',
            'type' => ProblemType::Behavioral,
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('search', 'Headache')
            ->call('setFilter', ProblemType::Physical->value)
            ->assertSee('Physical Headache')
            ->assertDontSee('Behavioral Headache');
    }

    public function test_no_results_shows_empty_state_with_clear_option(): void
    {
        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Existing Problem',
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('search', 'NonExistentXYZ')
            ->assertSee('No problems found matching your search criteria')
            ->assertSee('Clear filters');
    }

    public function test_clear_all_filters_restores_full_list(): void
    {
        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Problem Alpha',
        ]);

        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Problem Beta',
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member]);

        // Filter to hide one
        $component->set('search', 'Alpha')
            ->assertSee('Problem Alpha')
            ->assertDontSee('Problem Beta');

        // Clear all → both visible
        $component->call('clearAllFilters')
            ->assertSee('Problem Alpha')
            ->assertSee('Problem Beta');
    }

    public function test_search_is_case_insensitive(): void
    {
        Problem::factory()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
            'name' => 'Headache Problem',
        ]);

        Livewire::actingAs($this->user)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('search', 'headache')
            ->assertSee('Headache Problem');
    }

    public function test_add_task_disabled_after_resolve(): void
    {
        $careManager = User::factory()->create(['role' => UserRole::CareManager]);
        $problem = Problem::factory()->resolved()->create([
            'member_id' => $this->member->id,
            'submitted_by' => $this->user->id,
        ]);

        // The + button should not wire:click for resolved problems
        // (Only enabled when state === Confirmed)
        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->assertSee($problem->name);

        // Attempting to save a task for the resolved problem should fail validation
        Livewire::actingAs($careManager)
            ->test(CareManagementIndex::class, ['member' => $this->member])
            ->set('taskProblemId', $problem->id)
            ->set('taskType', 'referrals')
            ->set('taskName', 'Should not save')
            ->call('saveTask');

        $this->assertDatabaseMissing('tasks', ['name' => 'Should not save']);
    }
}
