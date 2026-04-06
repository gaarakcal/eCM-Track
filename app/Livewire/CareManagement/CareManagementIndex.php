<?php

namespace App\Livewire\CareManagement;

use App\Enums\NotificationEventType;
use App\Enums\ProblemClassification;
use App\Enums\ProblemState;
use App\Enums\ProblemType;
use App\Enums\TaskCompletionType;
use App\Enums\TaskState;
use App\Enums\TaskType;
use App\Enums\UserRole;
use App\Models\Member;
use App\Models\Note;
use App\Models\NotificationSetting;
use App\Models\OrganizationSetting;
use App\Models\OutreachLog;
use App\Models\Problem;
use App\Models\Resource;
use App\Models\StateChangeHistory;
use App\Models\Task;
use App\Notifications\NoteAddedNotification;
use App\Notifications\OutreachLoggedNotification;
use App\Notifications\ProblemAddedNotification;
use App\Notifications\ProblemConfirmedNotification;
use App\Notifications\ProblemResolvedNotification;
use App\Notifications\ProblemUnconfirmedNotification;
use App\Notifications\ProblemUnresolvedNotification;
use App\Notifications\ResourceAddedNotification;
use App\Notifications\TaskAddedNotification;
use App\Notifications\TaskCompletedNotification;
use App\Notifications\TaskStartedNotification;
use App\Notifications\TaskUncompletedNotification;
use App\Services\CareManagement\NotificationService;
use App\Services\CareManagement\PtrValidationService;
use App\Services\CareManagement\StateMachineService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class CareManagementIndex extends Component
{
    use WithPagination;

    public Member $member;

    public ?string $activeFilter = null;

    // ─── Add Problem Modal ───────────────────────────────────
    public string $problemType = '';

    public string $problemName = '';

    public string $problemCode = '';

    public string $problemEncounterSetting = '';

    // ─── Add Task Modal ──────────────────────────────────────
    public ?int $taskProblemId = null;

    public string $taskType = '';

    public string $taskName = '';

    public string $taskCode = '';

    public string $taskEncounterSetting = '';

    public string $taskProvider = '';

    public ?string $taskDate = null;

    public ?string $taskDueDate = null;

    // ─── Add Resource Modal ──────────────────────────────────
    public ?int $resourceTaskId = null;

    public string $surveyName = '';

    public string $atHome = '';

    public string $atWork = '';

    public string $atPlay = '';

    public string $surveyNumber = '';

    public string $resourceDetails = '';

    // ─── Unconfirm Modal ──────────────────────────────────────
    public ?int $unconfirmProblemId = null;

    public string $unconfirmNote = '';

    // ─── Unresolve Modal ──────────────────────────────────────
    public ?int $unresolveProblemId = null;

    public string $unresolveNote = '';

    // ─── Complete Task Modal ──────────────────────────────────
    public ?int $completeTaskId = null;

    public string $completionReason = '';

    // ─── Uncomplete Task Modal ────────────────────────────────
    public ?int $uncompleteTaskId = null;

    public string $uncompleteTaskNote = '';

    // ─── Goal Completion ─────────────────────────────────────
    public ?int $completeGoalId = null;

    public array $incompleteGoalTasks = [];

    // ─── Goal Associations ─────────────────────────────────────
    public array $selectedGoals = [];

    public ?int $newGoalId = null;

    public array $retroactiveTaskIds = [];

    // ─── Add Note Modal ──────────────────────────────────────────
    public ?string $noteEntityType = null;

    public ?int $noteEntityId = null;

    public string $noteContent = '';

    public bool $noteNotify = false;

    // ─── State Change History Modal ──────────────────────────────
    public array $stateHistoryRecords = [];

    public bool $showHistoryModal = false;

    public ?string $historyEntityName = null;

    // ─── View Mode ──────────────────────────────────────────────
    public string $viewMode = 'ptr'; // 'ptr', 'goal', or 'care_plan'

    // ─── Care Plan ────────────────────────────────────────────────
    public ?int $selectedCarePlanId = null;

    public ?int $carePlanFilter = null;

    // ─── Outreach Modal ─────────────────────────────────────────
    public string $outreachMethod = '';

    public ?string $outreachDate = null;

    public string $outreachOutcome = '';

    public string $outreachNotes = '';

    // ─── Filters & Search ─────────────────────────────────────
    public string $search = '';

    public string $statusFilter = '';

    public function setFilter(string $type): void
    {
        $this->activeFilter = $type;
        $this->resetPage();
    }

    public function clearFilter(): void
    {
        $this->activeFilter = null;
        $this->resetPage();
    }

    public function clearAllFilters(): void
    {
        $this->activeFilter = null;
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function switchView(string $mode): void
    {
        $this->viewMode = $mode;
        if ($mode === 'care_plan' && ! $this->selectedCarePlanId) {
            // Auto-select the latest care plan
            $latest = $this->member->carePlans()->latest('version_number')->first();
            $this->selectedCarePlanId = $latest?->id;
        }
    }

    public function selectCarePlan(?int $carePlanId): void
    {
        $this->selectedCarePlanId = $carePlanId;
        $this->resetPage();
    }

    public function setCarePlanFilter(?int $carePlanId): void
    {
        $this->carePlanFilter = $carePlanId;
        $this->resetPage();
    }

    #[Computed]
    public function carePlans()
    {
        return $this->member->carePlans()->orderBy('version_number', 'desc')->get();
    }

    #[Computed]
    public function carePlanSummary(): ?array
    {
        $latestPlan = $this->member->carePlans()->latest('version_number')->first();

        if (! $latestPlan) {
            return null;
        }

        return [
            'assessment_type' => $latestPlan->assessment_type,
            'assessment_date' => $latestPlan->assessment_date?->format('M j, Y'),
            'next_reassessment_date' => $latestPlan->next_reassessment_date?->format('M j, Y'),
            'is_overdue' => $latestPlan->isReassessmentOverdue(),
            'risk_level' => $latestPlan->risk_level,
            'version_count' => $this->member->carePlans()->count(),
            'current_version' => $latestPlan->version_number,
        ];
    }

    #[Computed]
    public function goals()
    {
        return Task::whereHas('problem', fn ($q) => $q->where('member_id', $this->member->id))
            ->where('type', TaskType::Goal)
            ->with(['associatedTasks', 'problem'])
            ->get();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return (bool) ($this->activeFilter || $this->search || $this->statusFilter);
    }

    #[Computed]
    public function problems()
    {
        $query = $this->member->problems()->with('tasks.resources');

        // CM-ACC-001 AC#3: Exclude blocked BH/SUD categories from results
        $blockedTypes = [];
        if ($this->bhConsentBlocked) {
            $blockedTypes[] = ProblemType::Behavioral->value;
        }
        if ($this->sudConsentBlocked) {
            $blockedTypes[] = ProblemType::SUD->value;
        }
        if ($blockedTypes) {
            $query->whereNotIn('type', $blockedTypes);
        }

        if ($this->activeFilter) {
            $query->where('type', $this->activeFilter);
        }

        if ($this->search) {
            $term = $this->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            });
        }

        if ($this->statusFilter) {
            $query->where('state', $this->statusFilter);
        }

        // Care plan filter (used in both PTR and Care Plan views)
        if ($this->viewMode === 'care_plan' && $this->selectedCarePlanId) {
            $query->where('care_plan_id', $this->selectedCarePlanId);
        } elseif ($this->carePlanFilter) {
            $query->where('care_plan_id', $this->carePlanFilter);
        }

        return $query->paginate(10);
    }

    // ─── Problem Actions ─────────────────────────────────────

    public function saveProblem(): void
    {
        if ($this->cmModuleBlocked && ! $this->consentOverrideActive) {
            return;
        }

        // Role gate: only CM, CHW, Supervisor, Authorized Clinician can add problems
        if (! auth()->user()->can('create', Problem::class)) {
            session()->flash('error', 'You do not have permission to add problems.');

            return;
        }

        $this->validate([
            'problemType' => 'required|string',
            'problemName' => 'required|string|max:255',
            'problemCode' => 'nullable|string|max:50',
            'problemEncounterSetting' => 'nullable|string',
        ]);

        // CM-ACC-001 AC#3: BH/SUD category-level consent blocking
        if ($this->bhConsentBlocked && $this->problemType === ProblemType::Behavioral->value) {
            session()->flash('error', 'BH Consent is set to No Consent. Behavioral problems cannot be added.');

            return;
        }

        if ($this->sudConsentBlocked && $this->problemType === ProblemType::SUD->value) {
            session()->flash('error', 'SUD Consent is set to No Consent. SUD problems cannot be added.');

            return;
        }

        $problem = Problem::create([
            'member_id' => $this->member->id,
            'type' => $this->problemType,
            'name' => $this->problemName,
            'code' => $this->problemCode ?: null,
            'encounter_setting' => $this->problemEncounterSetting ?: null,
            'state' => ProblemState::Added,
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
            'lock_version' => 0,
            'care_plan_id' => ($this->viewMode === 'care_plan' && $this->selectedCarePlanId) ? $this->selectedCarePlanId : null,
        ]);

        // Audit event: PROBLEM_ADDED
        StateChangeHistory::create([
            'trackable_type' => Problem::class,
            'trackable_id' => $problem->id,
            'from_state' => null,
            'to_state' => ProblemState::Added->value,
            'changed_by' => auth()->id(),
            'metadata' => ['event' => 'PROBLEM_ADDED'],
        ]);

        // Notify lead care manager
        app(NotificationService::class)->notifyLeadCareManager(
            $this->member, NotificationEventType::ProblemAdded, new ProblemAddedNotification($problem)
        );

        $this->reset(['problemType', 'problemName', 'problemCode', 'problemEncounterSetting']);
        unset($this->problems);
    }

    public function confirmProblem(int $problemId, ?bool $reactivateTasks = null): void
    {
        $problem = Problem::findOrFail($problemId);

        // Check role-based permission
        if (! auth()->user()->can('confirm', $problem)) {
            session()->flash('error', 'You do not have permission to confirm problems.');

            return;
        }

        // CM-CON-002 AC#3: Notify if lock expired
        if ($this->checkLockExpiredForCurrentUser($problem)) {
            return;
        }

        // CM-CON-003 AC#3: Notify if lock was admin-released
        if ($this->checkLockAdminReleased($problem)) {
            return;
        }

        // Auto-acquire lock (AC#1: lock acquired when edit session begins)
        $this->autoAcquireLock($problem);
        if ($problem->isLockedByAnother(auth()->id())) {
            $lockedBy = $problem->lockedByUser?->name ?? 'another user';
            session()->flash('error', "This problem is currently locked by {$lockedBy}.");

            return;
        }

        // Check if there are cascaded tasks that could be reactivated (first call only)
        if ($reactivateTasks === null) {
            $cascadedCount = $problem->tasks()
                ->where('state', TaskState::Completed)
                ->where('completion_type', TaskCompletionType::ProblemUnconfirmed)
                ->count();

            if ($cascadedCount > 0) {
                $this->dispatch('show-reactivation-dialog', problemId: $problemId, taskCount: $cascadedCount);

                return;
            }
        }

        app(StateMachineService::class)->confirmProblem($problem, auth()->user());

        // Reactivate cascaded tasks if requested
        if ($reactivateTasks === true) {
            $this->reactivateCascadedTasks($problemId);
        }

        // Notify lead care manager
        app(NotificationService::class)->notifyLeadCareManager(
            $problem->member, NotificationEventType::ProblemConfirmed, new ProblemConfirmedNotification($problem->fresh())
        );

        // Release lock after successful action
        $problem->releaseLock();
        unset($this->problems);
    }

    public function openUnconfirmModal(int $problemId): void
    {
        $this->unconfirmProblemId = $problemId;
        $this->unconfirmNote = '';
        // Auto-acquire lock when opening edit modal
        $this->acquireLock($problemId);
    }

    public function unconfirmProblem(): void
    {
        $this->validate([
            'unconfirmNote' => 'required|string|min:1',
            'unconfirmProblemId' => 'required|exists:problems,id',
        ], [
            'unconfirmNote.required' => 'An explanatory note is required to unconfirm a problem.',
        ]);

        $problem = Problem::findOrFail($this->unconfirmProblemId);

        // CM-CON-002 AC#3: Notify if lock expired
        if ($this->checkLockExpiredForCurrentUser($problem)) {
            return;
        }

        // CM-CON-003 AC#3: Notify if lock was admin-released
        if ($this->checkLockAdminReleased($problem)) {
            return;
        }

        // Check role-based permission
        if (! auth()->user()->can('unconfirm', $problem)) {
            session()->flash('error', 'You do not have permission to unconfirm problems.');

            return;
        }

        // Lock already acquired in openUnconfirmModal
        if ($problem->isLockedByAnother(auth()->id())) {
            $lockedBy = $problem->lockedByUser?->name ?? 'another user';
            session()->flash('error', "This problem is currently locked by {$lockedBy}.");

            return;
        }

        $note = $this->unconfirmNote;
        app(StateMachineService::class)->unconfirmProblem($problem, auth()->user(), $note);

        // Notify lead care manager
        app(NotificationService::class)->notifyLeadCareManager(
            $problem->member, NotificationEventType::ProblemUnconfirmed, new ProblemUnconfirmedNotification($problem->fresh(), $note)
        );

        // Release lock after successful action
        $problem->releaseLock();
        $this->reset(['unconfirmProblemId', 'unconfirmNote']);
        unset($this->problems);
    }

    public function reactivateCascadedTasks(int $problemId): void
    {
        $problem = Problem::findOrFail($problemId);

        // Find tasks that were auto-completed due to unconfirm
        $cascadedTasks = $problem->tasks()
            ->where('state', TaskState::Completed)
            ->where('completion_type', TaskCompletionType::ProblemUnconfirmed)
            ->get();

        foreach ($cascadedTasks as $task) {
            $fromState = $task->state;
            // Revert to the state before completion (Started if it was started, Added otherwise)
            $targetState = $task->started_at ? TaskState::Started : TaskState::Added;

            $task->update([
                'state' => $targetState,
                'completion_type' => null,
                'completed_by' => null,
                'completed_at' => null,
            ]);

            StateChangeHistory::create([
                'trackable_type' => Task::class,
                'trackable_id' => $task->id,
                'from_state' => $fromState->value,
                'to_state' => $targetState->value,
                'changed_by' => auth()->id(),
                'note' => 'Reactivated after problem re-confirmed',
                'metadata' => ['reactivation' => true],
            ]);
        }

        unset($this->problems);
    }

    public function resolveProblem(int $problemId): void
    {
        $problem = Problem::findOrFail($problemId);

        if (! auth()->user()->can('resolve', $problem)) {
            session()->flash('error', 'You do not have permission to resolve problems.');

            return;
        }

        // CM-CON-002 AC#3: Notify if lock expired
        if ($this->checkLockExpiredForCurrentUser($problem)) {
            return;
        }

        // CM-CON-003 AC#3: Notify if lock was admin-released
        if ($this->checkLockAdminReleased($problem)) {
            return;
        }

        // Auto-acquire lock
        $this->autoAcquireLock($problem);
        if ($problem->isLockedByAnother(auth()->id())) {
            $lockedBy = $problem->lockedByUser?->name ?? 'another user';
            session()->flash('error', "This problem is currently locked by {$lockedBy}.");

            return;
        }

        app(StateMachineService::class)->resolveProblem($problem, auth()->user());

        // Notify lead care manager
        app(NotificationService::class)->notifyLeadCareManager(
            $problem->member, NotificationEventType::ProblemResolved, new ProblemResolvedNotification($problem->fresh())
        );

        // Release lock after successful action
        $problem->releaseLock();
        unset($this->problems);
    }

    public function openUnresolveModal(int $problemId): void
    {
        $this->unresolveProblemId = $problemId;
        $this->unresolveNote = '';
        // Auto-acquire lock when opening edit modal
        $this->acquireLock($problemId);
    }

    public function unresolveProblem(): void
    {
        $this->validate([
            'unresolveNote' => 'required|string|min:1',
            'unresolveProblemId' => 'required|exists:problems,id',
        ], [
            'unresolveNote.required' => 'An explanatory note is required to unresolve a problem.',
        ]);

        $problem = Problem::findOrFail($this->unresolveProblemId);

        // CM-CON-002 AC#3: Notify if lock expired
        if ($this->checkLockExpiredForCurrentUser($problem)) {
            return;
        }

        // CM-CON-003 AC#3: Notify if lock was admin-released
        if ($this->checkLockAdminReleased($problem)) {
            return;
        }

        if (! auth()->user()->can('unresolve', $problem)) {
            session()->flash('error', 'You do not have permission to unresolve problems.');

            return;
        }

        if ($problem->isLockedByAnother(auth()->id())) {
            $lockedBy = $problem->lockedByUser?->name ?? 'another user';
            session()->flash('error', "This problem is currently locked by {$lockedBy}.");

            return;
        }

        $note = $this->unresolveNote;
        app(StateMachineService::class)->unresolveProblem($problem, auth()->user(), $note);

        // Notify lead care manager
        app(NotificationService::class)->notifyLeadCareManager(
            $problem->member, NotificationEventType::ProblemUnresolved, new ProblemUnresolvedNotification($problem->fresh(), $note)
        );

        // Check for cascaded tasks that can be reactivated
        $cascadedCount = $problem->tasks()
            ->where('state', TaskState::Completed)
            ->where('completion_type', TaskCompletionType::ProblemResolved)
            ->count();

        if ($cascadedCount > 0) {
            $this->dispatch('show-resolve-reactivation-dialog', problemId: $problem->id, taskCount: $cascadedCount);
        }

        // Release lock after successful action
        $problem->releaseLock();
        $this->reset(['unresolveProblemId', 'unresolveNote']);
        unset($this->problems);
    }

    public function reactivateResolvedTasks(int $problemId): void
    {
        $problem = Problem::findOrFail($problemId);

        $cascadedTasks = $problem->tasks()
            ->where('state', TaskState::Completed)
            ->where('completion_type', TaskCompletionType::ProblemResolved)
            ->get();

        foreach ($cascadedTasks as $task) {
            $fromState = $task->state;
            $targetState = $task->started_at ? TaskState::Started : TaskState::Added;

            $task->update([
                'state' => $targetState,
                'completion_type' => null,
                'completed_by' => null,
                'completed_at' => null,
            ]);

            StateChangeHistory::create([
                'trackable_type' => Task::class,
                'trackable_id' => $task->id,
                'from_state' => $fromState->value,
                'to_state' => $targetState->value,
                'changed_by' => auth()->id(),
                'note' => 'Reactivated after problem unresolved',
                'metadata' => ['reactivation' => true],
            ]);
        }

        unset($this->problems);
    }

    // ─── Task Actions ────────────────────────────────────────

    public function openAddTaskModal(int $problemId): void
    {
        $this->taskProblemId = $problemId;
        $this->reset(['taskType', 'taskName', 'taskCode', 'taskEncounterSetting', 'taskProvider', 'taskDate', 'taskDueDate']);
        // Auto-acquire lock on parent problem when adding task
        $this->acquireLock($problemId);
    }

    public function saveTask(): void
    {
        $this->validate([
            'taskType' => 'required|string',
            'taskName' => 'required|string|max:255',
            'taskCode' => 'nullable|string|max:50',
            'taskEncounterSetting' => 'nullable|string',
            'taskProvider' => 'nullable|string|max:255',
            'taskDate' => 'nullable|date',
            'taskDueDate' => 'nullable|date',
            'taskProblemId' => 'required|exists:problems,id',
        ]);

        $problem = Problem::findOrFail($this->taskProblemId);

        try {
            app(PtrValidationService::class)->validateTaskCreation($problem);
        } catch (\InvalidArgumentException $e) {
            $this->addError('taskProblemId', $e->getMessage());

            return;
        }

        // CM staff restriction for Goal creation
        if ($this->taskType === 'goal') {
            $userRole = auth()->user()->role;
            if (! $userRole || ! $userRole->canCreateGoal()) {
                $this->addError('taskType', 'Only Care Managers, Supervisors, or Admins can create Goals.');

                return;
            }
        }

        $task = Task::create([
            'problem_id' => $this->taskProblemId,
            'name' => $this->taskName,
            'type' => $this->taskType,
            'code' => $this->taskCode ?: null,
            'encounter_setting' => $this->taskEncounterSetting ?: null,
            'provider' => $this->taskProvider ?: null,
            'task_date' => $this->taskDate ?: null,
            'due_date' => $this->taskDueDate ?: null,
            'state' => TaskState::Added,
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
            'care_plan_id' => ($this->viewMode === 'care_plan' && $this->selectedCarePlanId) ? $this->selectedCarePlanId : null,
        ]);

        // Audit event: TASK_ADDED
        StateChangeHistory::create([
            'trackable_type' => Task::class,
            'trackable_id' => $task->id,
            'from_state' => null,
            'to_state' => TaskState::Added->value,
            'changed_by' => auth()->id(),
            'metadata' => [
                'event' => 'TASK_ADDED',
                'task_type' => $this->taskType,
                'problem_id' => $this->taskProblemId,
                'member_id' => $problem->member_id,
            ],
        ]);

        // Associate task with selected goals
        if (! empty($this->selectedGoals) && $this->taskType !== 'goal') {
            $task->goals()->sync($this->selectedGoals);
        }

        // Notify lead care manager
        app(NotificationService::class)->notifyLeadCareManager(
            $problem->member, NotificationEventType::TaskAdded, new TaskAddedNotification($task)
        );

        // If a Goal was created, trigger retroactive association dialog
        if ($this->taskType === 'goal') {
            $existingTasks = Task::where('problem_id', $this->taskProblemId)
                ->where('id', '!=', $task->id)
                ->where('type', '!=', TaskType::Goal->value)
                ->pluck('name', 'id')
                ->toArray();

            if (! empty($existingTasks)) {
                $this->newGoalId = $task->id;
                $this->retroactiveTaskIds = [];
                $this->taskProblemId = null;
                $this->selectedGoals = [];
                $this->reset(['taskType', 'taskName', 'taskCode', 'taskEncounterSetting', 'taskProvider', 'taskDate', 'taskDueDate']);
                unset($this->problems);

                return;
            }
        }

        // Release lock on parent problem after saving task
        if ($problem->locked_by === auth()->id()) {
            $problem->releaseLock();
        }

        $this->taskProblemId = null;
        $this->selectedGoals = [];
        $this->reset(['taskType', 'taskName', 'taskCode', 'taskEncounterSetting', 'taskProvider', 'taskDate', 'taskDueDate']);
        unset($this->problems);
    }

    public function approveTask(int $taskId): void
    {
        $task = Task::findOrFail($taskId);

        if (! auth()->user()->can('approve', $task)) {
            session()->flash('error', 'You do not have permission to approve this task.');

            return;
        }

        app(StateMachineService::class)->approveTask($task, auth()->user());
        unset($this->problems);
    }

    public function startTask(int $taskId): void
    {
        $task = Task::findOrFail($taskId);

        // Block start if task requires approval and hasn't been approved
        if ($task->state === TaskState::Added && $task->type->requiresApproval()) {
            session()->flash('error', 'This task requires approval before it can be started.');

            return;
        }

        app(StateMachineService::class)->startTask($task, auth()->user());

        // Notify lead care manager
        app(NotificationService::class)->notifyLeadCareManager(
            $task->problem->member, NotificationEventType::TaskStarted, new TaskStartedNotification($task->fresh())
        );

        unset($this->problems);
    }

    public function openCompleteTaskModal(int $taskId): void
    {
        $this->completeTaskId = $taskId;
        $this->completionReason = '';
    }

    public function completeTask(): void
    {
        $this->validate([
            'completionReason' => 'required|string|in:completed,cancelled,terminated',
            'completeTaskId' => 'required|exists:tasks,id',
        ]);

        $task = Task::findOrFail($this->completeTaskId);
        $completionType = TaskCompletionType::from($this->completionReason);

        app(StateMachineService::class)->completeTask($task, auth()->user(), $completionType);

        // Notify lead care manager
        app(NotificationService::class)->notifyLeadCareManager(
            $task->problem->member, NotificationEventType::TaskCompleted, new TaskCompletedNotification($task->fresh())
        );

        $this->reset(['completeTaskId', 'completionReason']);
        unset($this->problems);
    }

    public function openUncompleteTaskModal(int $taskId): void
    {
        $this->uncompleteTaskId = $taskId;
        $this->uncompleteTaskNote = '';
    }

    public function uncompleteTask(): void
    {
        $this->validate([
            'uncompleteTaskNote' => 'required|string|min:1',
            'uncompleteTaskId' => 'required|exists:tasks,id',
        ], [
            'uncompleteTaskNote.required' => 'An explanatory note is required to uncomplete a task.',
        ]);

        $task = Task::findOrFail($this->uncompleteTaskId);

        if (! auth()->user()->can('uncomplete', $task)) {
            session()->flash('error', 'You do not have permission to uncomplete this task.');

            return;
        }

        $note = $this->uncompleteTaskNote;
        app(StateMachineService::class)->uncompleteTask($task, auth()->user(), $note);

        // Notify lead care manager
        app(NotificationService::class)->notifyLeadCareManager(
            $task->problem->member, NotificationEventType::TaskUncompleted, new TaskUncompletedNotification($task->fresh(), $note)
        );

        $this->reset(['uncompleteTaskId', 'uncompleteTaskNote']);
        unset($this->problems);
    }

    // ─── Resource Actions ────────────────────────────────────

    public function openAddResourceModal(int $taskId): void
    {
        $this->resourceTaskId = $taskId;
        $task = Task::findOrFail($taskId);
        $this->surveyName = 'Resource '.($task->resources()->count() + 1);
        $this->reset(['surveyNumber', 'atHome', 'atWork', 'atPlay', 'resourceDetails']);
    }

    public function saveResource(): void
    {
        $this->validate([
            'surveyName' => 'required|string|max:255',
            'surveyNumber' => 'nullable|string|max:50',
            'atHome' => 'required|string',
            'atWork' => 'required|string',
            'atPlay' => 'required|string',
            'resourceDetails' => 'nullable|string',
            'resourceTaskId' => 'required|exists:tasks,id',
        ]);

        $task = Task::findOrFail($this->resourceTaskId);

        try {
            app(PtrValidationService::class)->validateResourceCreation($task);
        } catch (\InvalidArgumentException $e) {
            $this->addError('resourceTaskId', $e->getMessage());

            return;
        }

        $resource = Resource::create([
            'task_id' => $this->resourceTaskId,
            'survey_name' => $this->surveyName,
            'survey_number' => $this->surveyNumber ?: null,
            'at_home' => $this->atHome,
            'at_work' => $this->atWork,
            'at_play' => $this->atPlay,
            'details' => $this->resourceDetails ?: null,
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);

        // Audit event: RESOURCE_ADDED
        StateChangeHistory::create([
            'trackable_type' => Resource::class,
            'trackable_id' => $resource->id,
            'from_state' => null,
            'to_state' => 'created',
            'changed_by' => auth()->id(),
            'metadata' => [
                'event' => 'RESOURCE_ADDED',
                'task_id' => $task->id,
                'problem_id' => $task->problem_id,
                'member_id' => $task->problem->member_id,
            ],
        ]);

        // Notify lead care manager
        app(NotificationService::class)->notifyLeadCareManager(
            $task->problem->member, NotificationEventType::ResourceAdded, new ResourceAddedNotification($resource)
        );

        $this->resourceTaskId = null;
        $this->reset(['surveyName', 'surveyNumber', 'atHome', 'atWork', 'atPlay', 'resourceDetails']);
        unset($this->problems);
    }

    // ─── Goal Methods ────────────────────────────────────────

    public function openCompleteGoalModal(int $goalId): void
    {
        $goal = Task::where('type', TaskType::Goal)->findOrFail($goalId);
        $incompleteTasks = $goal->associatedTasks()
            ->where('state', '!=', TaskState::Completed->value)
            ->get();

        if ($incompleteTasks->isEmpty()) {
            // All tasks complete — complete the goal directly
            $this->completeGoalDirectly($goalId);

            return;
        }

        // Show confirmation dialog with incomplete tasks
        $this->completeGoalId = $goalId;
        $this->incompleteGoalTasks = $incompleteTasks->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'state' => $t->state->value])->toArray();
    }

    public function confirmCompleteGoal(): void
    {
        if ($this->completeGoalId) {
            $this->completeGoalDirectly($this->completeGoalId);
            $this->completeGoalId = null;
            $this->incompleteGoalTasks = [];
        }
    }

    public function cancelCompleteGoal(): void
    {
        $this->completeGoalId = null;
        $this->incompleteGoalTasks = [];
    }

    private function completeGoalDirectly(int $goalId): void
    {
        $goal = Task::findOrFail($goalId);
        app(StateMachineService::class)->completeTask($goal, auth()->user(), TaskCompletionType::Completed);

        app(NotificationService::class)->notifyLeadCareManager(
            $goal->problem->member, NotificationEventType::TaskCompleted, new TaskCompletedNotification($goal->fresh())
        );

        unset($this->problems);
        unset($this->goals);
    }

    public function getRetroactiveTasks(): array
    {
        if (! $this->newGoalId) {
            return [];
        }

        $goal = Task::find($this->newGoalId);
        if (! $goal) {
            return [];
        }

        return Task::where('problem_id', $goal->problem_id)
            ->where('id', '!=', $this->newGoalId)
            ->where('type', '!=', TaskType::Goal->value)
            ->pluck('name', 'id')
            ->toArray();
    }

    public function saveRetroactiveAssociations(): void
    {
        if ($this->newGoalId && ! empty($this->retroactiveTaskIds)) {
            $goal = Task::where('type', TaskType::Goal)->findOrFail($this->newGoalId);

            foreach ($this->retroactiveTaskIds as $taskId) {
                $task = Task::findOrFail($taskId);
                $task->goals()->syncWithoutDetaching([$this->newGoalId]);

                StateChangeHistory::create([
                    'trackable_type' => Task::class,
                    'trackable_id' => $task->id,
                    'from_state' => $task->state->value,
                    'to_state' => $task->state->value,
                    'changed_by' => auth()->id(),
                    'metadata' => [
                        'event' => 'TASK_GOAL_ASSOCIATED',
                        'goal_id' => $this->newGoalId,
                        'goal_name' => $goal->name,
                    ],
                ]);
            }
        }

        $this->newGoalId = null;
        $this->retroactiveTaskIds = [];
        unset($this->goals);
        unset($this->problems);
    }

    public function skipRetroactiveAssociations(): void
    {
        $this->newGoalId = null;
        $this->retroactiveTaskIds = [];
    }

    public function associateTaskWithGoal(int $taskId, int $goalId): void
    {
        $task = Task::findOrFail($taskId);
        $goal = Task::where('type', TaskType::Goal)->findOrFail($goalId);
        $task->goals()->syncWithoutDetaching([$goalId]);

        StateChangeHistory::create([
            'trackable_type' => Task::class,
            'trackable_id' => $task->id,
            'from_state' => $task->state->value,
            'to_state' => $task->state->value,
            'changed_by' => auth()->id(),
            'metadata' => [
                'event' => 'TASK_GOAL_ASSOCIATED',
                'goal_id' => $goalId,
                'goal_name' => $goal->name,
            ],
        ]);

        unset($this->goals);
    }

    public function getAvailableGoals(): array
    {
        if (! $this->taskProblemId) {
            return [];
        }

        return Task::whereHas('problem', fn ($q) => $q->where('member_id', $this->member->id))
            ->where('type', TaskType::Goal)
            ->pluck('name', 'id')
            ->toArray();
    }

    // ─── Unsupported Problem Classification ────────────────────

    public function classifyUnsupportedProblem(int $problemId, string $classification): void
    {
        $problem = Problem::findOrFail($problemId);
        $classificationEnum = ProblemClassification::from($classification);

        $problem->update([
            'classification' => $classificationEnum,
            'classification_by' => auth()->id(),
            'classification_at' => now(),
            'unsupported_problem_flag' => false,
        ]);

        $noteText = "Reassessment classification: {$classificationEnum->label()}";

        match ($classificationEnum) {
            ProblemClassification::AssessmentEntryError => (function () use ($problem, $noteText) {
                // No state change, just clear the flag and record audit
                StateChangeHistory::create([
                    'trackable_type' => Problem::class,
                    'trackable_id' => $problem->id,
                    'from_state' => $problem->state->value,
                    'to_state' => $problem->state->value,
                    'changed_by' => auth()->id(),
                    'note' => $noteText,
                    'metadata' => ['event' => 'PROBLEM_CLASSIFIED', 'classification' => 'assessment_entry_error', 'trigger' => 'REASSESSMENT_UNSUPPORTED'],
                ]);
            })(),
            ProblemClassification::ProblemNoLongerConfirmed => (function () use ($problem, $noteText) {
                if ($problem->state === ProblemState::Confirmed) {
                    app(StateMachineService::class)->unconfirmProblem($problem, auth()->user(), $noteText);
                }
                StateChangeHistory::create([
                    'trackable_type' => Problem::class,
                    'trackable_id' => $problem->id,
                    'from_state' => ProblemState::Confirmed->value,
                    'to_state' => ProblemState::Added->value,
                    'changed_by' => auth()->id(),
                    'note' => $noteText,
                    'metadata' => ['event' => 'PROBLEM_CLASSIFIED', 'classification' => 'problem_no_longer_confirmed', 'trigger' => 'REASSESSMENT_UNSUPPORTED'],
                ]);
            })(),
            ProblemClassification::ProblemResolved => (function () use ($problem, $noteText) {
                if ($problem->state === ProblemState::Confirmed) {
                    app(StateMachineService::class)->resolveProblem($problem, auth()->user());
                }
                StateChangeHistory::create([
                    'trackable_type' => Problem::class,
                    'trackable_id' => $problem->id,
                    'from_state' => ProblemState::Confirmed->value,
                    'to_state' => ProblemState::Resolved->value,
                    'changed_by' => auth()->id(),
                    'note' => $noteText,
                    'metadata' => ['event' => 'PROBLEM_CLASSIFIED', 'classification' => 'problem_resolved', 'trigger' => 'REASSESSMENT_UNSUPPORTED'],
                ]);
            })(),
        };

        unset($this->problems);
    }

    // ─── Lock Actions ─────────────────────────────────────────

    /**
     * Silently acquire a lock on a problem for editing.
     * Auto-releases expired locks first.
     */
    private function autoAcquireLock(Problem $problem): void
    {
        // Auto-release expired locks and notify if it was our lock (CM-CON-002)
        if ($problem->isLockExpired()) {
            $wasOurLock = $problem->locked_by === auth()->id();
            $problem->releaseLock();
            $problem->refresh();
            if ($wasOurLock) {
                session()->flash('warning', 'Your lock on this problem has expired due to inactivity. A new lock has been acquired.');
            }
        }

        // If already locked by this user, just refresh the expiry
        if ($problem->locked_by === auth()->id()) {
            $timeout = (int) OrganizationSetting::get('lock_timeout_minutes', 15);
            $problem->update(['lock_expires_at' => now()->addMinutes($timeout)]);

            return;
        }

        // If not locked, acquire it
        if (! $problem->locked_by) {
            $timeout = (int) OrganizationSetting::get('lock_timeout_minutes', 15);
            $problem->update([
                'locked_by' => auth()->id(),
                'locked_at' => now(),
                'lock_session_id' => session()->getId(),
                'lock_expires_at' => now()->addMinutes($timeout),
            ]);
        }
    }

    public function acquireLock(int $problemId): void
    {
        $problem = Problem::findOrFail($problemId);

        // Auto-release expired locks
        if ($problem->isLockExpired()) {
            $problem->releaseLock();
            $problem->refresh();
        }

        if ($problem->isLockedByAnother(auth()->id())) {
            $lockedBy = $problem->lockedByUser?->name ?? 'another user';
            session()->flash('error', "This problem is currently locked by {$lockedBy}.");

            return;
        }

        $timeout = (int) OrganizationSetting::get('lock_timeout_minutes', 15);

        $problem->update([
            'locked_by' => auth()->id(),
            'locked_at' => now(),
            'lock_session_id' => session()->getId(),
            'lock_expires_at' => now()->addMinutes($timeout),
        ]);

        unset($this->problems);
    }

    public function releaseLock(int $problemId): void
    {
        $problem = Problem::findOrFail($problemId);

        if ($problem->locked_by === auth()->id()) {
            $problem->releaseLock();
            unset($this->problems);
        }
    }

    public function adminReleaseLock(int $problemId): void
    {
        $user = auth()->user();

        if (! $user->role || $user->role !== UserRole::Admin) {
            session()->flash('error', 'Only administrators can release locks.');

            return;
        }

        $problem = Problem::findOrFail($problemId);
        $originalLockHolder = $problem->locked_by;

        StateChangeHistory::create([
            'trackable_type' => Problem::class,
            'trackable_id' => $problem->id,
            'from_state' => $problem->state->value,
            'to_state' => $problem->state->value,
            'changed_by' => auth()->id(),
            'metadata' => [
                'event' => 'LOCK_ADMIN_RELEASED',
                'original_lock_holder' => $originalLockHolder,
                'released_at' => now()->toISOString(),
                'notified' => false,
            ],
        ]);

        // Notify original lock holder via session flash (CM-CON-003)
        // In a real system this would be a push notification; for now, audit trail captures it

        $problem->releaseLock();
        unset($this->problems);
    }

    // ─── Note Actions ─────────────────────────────────────────

    public function openAddNoteModal(string $entityType, int $entityId): void
    {
        $this->noteEntityType = $entityType;
        $this->noteEntityId = $entityId;
        $this->noteContent = '';
        $this->noteNotify = false;
    }

    public function saveNote(): void
    {
        // CM-AUD-001 AC#4: Role gate — only CM staff can add notes
        $user = auth()->user();
        if (! $user->role || ! $user->role->canAddNote()) {
            session()->flash('error', 'You do not have permission to add notes.');

            return;
        }

        $this->validate([
            'noteContent' => 'required|string|min:1',
            'noteEntityType' => 'required|string|in:problem,task,resource',
            'noteEntityId' => 'required|integer',
        ]);

        $modelClass = match ($this->noteEntityType) {
            'problem' => Problem::class,
            'task' => Task::class,
            'resource' => Resource::class,
        };
        $entity = $modelClass::findOrFail($this->noteEntityId);

        $note = Note::create([
            'content' => $this->noteContent,
            'created_by' => auth()->id(),
            'notable_type' => $modelClass,
            'notable_id' => $this->noteEntityId,
            'notify' => $this->noteNotify,
        ]);

        // Audit event
        StateChangeHistory::create([
            'trackable_type' => $modelClass,
            'trackable_id' => $this->noteEntityId,
            'from_state' => $entity instanceof Problem ? $entity->state->value : $entity->state->value,
            'to_state' => $entity instanceof Problem ? $entity->state->value : $entity->state->value,
            'changed_by' => auth()->id(),
            'metadata' => ['event' => 'NOTE_ADDED', 'note_id' => $note->id],
        ]);

        // Send notification if notify checkbox was checked
        if ($this->noteNotify) {
            $member = match (true) {
                $entity instanceof Problem => $entity->member,
                $entity instanceof Task => $entity->problem->member,
                $entity instanceof Resource => $entity->task->problem->member,
            };
            app(NotificationService::class)->notifyLeadCareManager(
                $member, NotificationEventType::NoteAdded, new NoteAddedNotification($note)
            );
        }

        $this->reset(['noteEntityType', 'noteEntityId', 'noteContent', 'noteNotify']);
        unset($this->problems);
    }

    // ─── State Change History ───────────────────────────────────

    public function showStateHistory(string $type, int $id): void
    {
        $modelClass = $type === 'problem' ? Problem::class : Task::class;
        $entity = $modelClass::findOrFail($id);

        $this->stateHistoryRecords = StateChangeHistory::where('trackable_type', $modelClass)
            ->where('trackable_id', $id)
            ->with('changedByUser')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($record) => [
                'from_state' => $record->from_state,
                'to_state' => $record->to_state,
                'changed_by' => $record->changedByUser?->name ?? 'System',
                'role' => $record->changedByUser?->role?->label() ?? 'N/A',
                'note' => $record->note,
                'event' => $record->metadata['event'] ?? null,
                'created_at' => $record->created_at->format('M j, Y g:i A'),
            ])
            ->toArray();

        $this->historyEntityName = $entity->name;
        $this->showHistoryModal = true;
    }

    public function closeHistoryModal(): void
    {
        $this->showHistoryModal = false;
        $this->stateHistoryRecords = [];
        $this->historyEntityName = null;
    }

    // ─── Outreach Actions ───────────────────────────────────────

    #[Computed]
    public function outreachLogs()
    {
        return $this->member->outreachLogs()
            ->with('staff')
            ->orderBy('outreach_date', 'desc')
            ->get();
    }

    #[Computed]
    public function canLogOutreach(): bool
    {
        $user = auth()->user();

        if (! $user->role || ! $user->role->canLogOutreach()) {
            return false;
        }

        return $this->member->outreachLogs()->count() < OutreachLog::MAX_ATTEMPTS;
    }

    public function saveOutreach(): void
    {
        $this->validate([
            'outreachMethod' => 'required|string',
            'outreachDate' => 'required|date',
            'outreachOutcome' => 'required|string',
            'outreachNotes' => 'nullable|string',
        ]);

        // Enforce max 3 attempts
        if ($this->member->outreachLogs()->count() >= OutreachLog::MAX_ATTEMPTS) {
            $this->addError('outreachMethod', 'Maximum of 3 outreach attempts reached for this member.');

            return;
        }

        // Verify role permission
        $user = auth()->user();
        if (! $user->role || ! $user->role->canLogOutreach()) {
            $this->addError('outreachMethod', 'You do not have permission to log outreach attempts.');

            return;
        }

        $log = OutreachLog::create([
            'member_id' => $this->member->id,
            'method' => $this->outreachMethod,
            'outreach_date' => $this->outreachDate,
            'outcome' => $this->outreachOutcome,
            'notes' => $this->outreachNotes ?: null,
            'staff_id' => auth()->id(),
            'logged_at' => now(),
        ]);

        // Audit event
        StateChangeHistory::create([
            'trackable_type' => Member::class,
            'trackable_id' => $this->member->id,
            'from_state' => 'outreach',
            'to_state' => 'outreach_logged',
            'changed_by' => auth()->id(),
            'metadata' => [
                'event' => 'OUTREACH_LOGGED',
                'outreach_log_id' => $log->id,
                'method' => $this->outreachMethod,
                'outcome' => $this->outreachOutcome,
            ],
        ]);

        // Notify lead care manager
        app(NotificationService::class)->notifyLeadCareManager(
            $this->member, NotificationEventType::OutreachLogged, new OutreachLoggedNotification($log)
        );

        $this->reset(['outreachMethod', 'outreachDate', 'outreachOutcome', 'outreachNotes']);
        unset($this->outreachLogs);
        unset($this->canLogOutreach);
    }

    // ─── Lock Timeout Configuration (CM-CON-002) ──────────────

    public string $lockTimeoutMinutes = '15';

    public function initLockTimeout(): void
    {
        $this->lockTimeoutMinutes = (string) OrganizationSetting::get('lock_timeout_minutes', '15');
    }

    public function updateLockTimeout(): void
    {
        $user = auth()->user();
        if (! $user->role || ! $user->role->canConfigureNotifications()) {
            session()->flash('error', 'Only administrators can update lock timeout settings.');

            return;
        }

        $this->validate([
            'lockTimeoutMinutes' => 'required|integer|min:1|max:120',
        ], [
            'lockTimeoutMinutes.required' => 'Timeout duration is required.',
            'lockTimeoutMinutes.min' => 'Timeout must be at least 1 minute.',
            'lockTimeoutMinutes.max' => 'Timeout cannot exceed 120 minutes.',
        ]);

        OrganizationSetting::set('lock_timeout_minutes', $this->lockTimeoutMinutes);
        $this->showInfoMessage("Lock timeout updated to {$this->lockTimeoutMinutes} minutes.");
    }

    /**
     * Check if a problem's lock has expired for the current user.
     * Returns true (and flashes error) if the lock expired and user must re-enter edit mode.
     */
    private function checkLockExpiredForCurrentUser(Problem $problem): bool
    {
        if ($problem->locked_by === auth()->id() && $problem->isLockExpired()) {
            $problem->releaseLock();
            session()->flash('error', 'Your lock on this problem has expired due to inactivity. Please re-enter edit mode before saving.');

            return true;
        }

        return false;
    }

    /**
     * CM-CON-003 AC#3: Check if the current user's lock was admin-released.
     * Returns true (and flashes error) if lock was released by admin since user last held it.
     */
    private function checkLockAdminReleased(Problem $problem): bool
    {
        $userId = auth()->id();

        // Only check if the problem is currently unlocked (lock was released)
        if ($problem->locked_by !== null) {
            return false;
        }

        $adminRelease = StateChangeHistory::where('trackable_type', Problem::class)
            ->where('trackable_id', $problem->id)
            ->where('metadata->event', 'LOCK_ADMIN_RELEASED')
            ->where('metadata->original_lock_holder', $userId)
            ->where('metadata->notified', false)
            ->latest()
            ->first();

        if ($adminRelease) {
            // Mark as notified so user only sees this once
            $meta = $adminRelease->metadata;
            $meta['notified'] = true;
            $adminRelease->update(['metadata' => $meta]);

            session()->flash('error', 'Your lock on this problem was released by an administrator. Please re-acquire the lock before saving.');

            return true;
        }

        return false;
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function getTaskProblemName(): string
    {
        if ($this->taskProblemId) {
            return Problem::find($this->taskProblemId)?->name ?? '';
        }

        return '';
    }

    public string $infoMessage = '';

    public function showInfoMessage(string $message): void
    {
        $this->infoMessage = $message;
        $this->dispatch('show-info-toast');
    }

    public bool $jiConsentBlocked = false;

    public bool $cmModuleBlocked = false;

    public string $cmBlockReason = '';

    public bool $bhConsentBlocked = false;

    public bool $sudConsentBlocked = false;

    public bool $consentOverrideActive = false;

    public bool $isDeIdentified = false;

    public function mount(Member $member): void
    {
        $this->member = $member;
        $this->initLockTimeout();
        $this->isDeIdentified = auth()->user()->role?->requiresDeIdentification() ?? false;
        $this->refreshConsentState();

        // Audit blocked access attempts (CM-ACC-001 AC#6)
        if ($this->cmModuleBlocked && ! $this->consentOverrideActive) {
            StateChangeHistory::create([
                'trackable_type' => Member::class,
                'trackable_id' => $member->id,
                'from_state' => 'access_attempt',
                'to_state' => 'access_blocked',
                'changed_by' => auth()->id(),
                'metadata' => [
                    'event' => 'CM_ACCESS_BLOCKED',
                    'consent_type' => $member->getCmBlockConsentType(),
                    'timestamp' => now()->toISOString(),
                ],
            ]);
        }

        // Audit BH/SUD category-level blocking
        if ($this->bhConsentBlocked) {
            StateChangeHistory::create([
                'trackable_type' => Member::class,
                'trackable_id' => $member->id,
                'from_state' => 'access_attempt',
                'to_state' => 'category_blocked',
                'changed_by' => auth()->id(),
                'metadata' => [
                    'event' => 'CM_ACCESS_BLOCKED',
                    'consent_type' => 'bh_consent',
                    'timestamp' => now()->toISOString(),
                ],
            ]);
        }

        if ($this->sudConsentBlocked) {
            StateChangeHistory::create([
                'trackable_type' => Member::class,
                'trackable_id' => $member->id,
                'from_state' => 'access_attempt',
                'to_state' => 'category_blocked',
                'changed_by' => auth()->id(),
                'metadata' => [
                    'event' => 'CM_ACCESS_BLOCKED',
                    'consent_type' => 'sud_consent',
                    'timestamp' => now()->toISOString(),
                ],
            ]);
        }
    }

    /**
     * Refresh consent state from database (no caching — AC#5).
     */
    private function refreshConsentState(): void
    {
        $this->member->refresh();
        $this->jiConsentBlocked = $this->member->isJiConsentBlocked();
        $this->cmModuleBlocked = $this->member->isCmModuleBlocked();
        $this->cmBlockReason = $this->member->getCmBlockReason() ?? '';
        $this->bhConsentBlocked = $this->member->isBhConsentBlocked();
        $this->sudConsentBlocked = $this->member->isSudConsentBlocked();

        // consentOverrideActive stays false until explicitly activated via activateConsentOverride()
    }

    /**
     * CM-ACC-001 AC#7: Compliance officer activates override to access blocked record.
     */
    public function activateConsentOverride(): void
    {
        $user = auth()->user();
        if (! $user->role || ! $user->role->canOverrideConsentBlock()) {
            session()->flash('error', 'Only compliance officers can override consent blocks.');

            return;
        }

        $this->consentOverrideActive = true;

        StateChangeHistory::create([
            'trackable_type' => Member::class,
            'trackable_id' => $this->member->id,
            'from_state' => 'access_blocked',
            'to_state' => 'access_override',
            'changed_by' => auth()->id(),
            'metadata' => [
                'event' => 'CM_ACCESS_OVERRIDE',
                'consent_type' => $this->member->getCmBlockConsentType(),
                'override_by' => $user->id,
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    public function toggleNotificationSetting(string $eventType): void
    {
        // Only MCP Admins can modify notification settings
        $user = auth()->user();
        if (! $user->role || ! $user->role->canConfigureNotifications()) {
            session()->flash('error', 'Only administrators can configure notification settings.');

            return;
        }

        $setting = NotificationSetting::where('event_type', $eventType)->first();
        if ($setting) {
            $setting->update(['enabled' => ! $setting->enabled]);
        }
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.care-management.index');
    }
}
