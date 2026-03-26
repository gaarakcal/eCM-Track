<div x-data="{
    showAddProblemModal: false,
    showAddTaskModal: false,
    showAddResourceModal: false,
    showConfirmDialog: false,
    showResolveDialog: false,
    showUnconfirmModal: false,
    showUnresolveModal: false,
    showReactivationDialog: false,
    showResolveReactivationDialog: false,
    confirmProblemId: null,
    resolveProblemId: null,
    reactivationProblemId: null,
    reactivationTaskCount: 0,
    resolveReactivationProblemId: null,
    resolveReactivationTaskCount: 0,
    expanded: [],
    taskExpanded: [],
    toggle(id) {
        const idx = this.expanded.indexOf(id);
        if (idx === -1) { this.expanded.push(id); } else { this.expanded.splice(idx, 1); }
    },
    toggleTask(id) {
        const idx = this.taskExpanded.indexOf(id);
        if (idx === -1) { this.taskExpanded.push(id); } else { this.taskExpanded.splice(idx, 1); }
    },
    isExpanded(id) { return this.expanded.includes(id) },
    isTaskExpanded(id) { return this.taskExpanded.includes(id) }
}" @show-reactivation-dialog.window="reactivationProblemId = $event.detail.problemId; reactivationTaskCount = $event.detail.taskCount; showReactivationDialog = true" @show-resolve-reactivation-dialog.window="resolveReactivationProblemId = $event.detail.problemId; resolveReactivationTaskCount = $event.detail.taskCount; showResolveReactivationDialog = true">
    <x-slot name="header">Care Management</x-slot>

    @if($jiConsentBlocked)
    <!-- JI Consent Block -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow px-8 py-12 text-center">
        <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-500 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Access Restricted</h3>
        <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto">Access to the Care Management module for this member is restricted due to Justice-Involved consent status. Please contact your administrator for more information.</p>
    </div>
    @else

    <!-- Member Header Bar -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow px-6 py-4 mb-6 flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-4">
            <span class="font-semibold text-gray-900 dark:text-white whitespace-nowrap">{{ $member->name }}</span>
            <span class="text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $member->dob->format('m-d-Y') }}</span>
            <span class="text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $member->member_id }}</span>
            <span class="text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $member->organization }}</span>
        </div>
        <div class="ml-auto flex flex-wrap gap-2">
            <button type="button" @click="showAddProblemModal = true" class="bg-gray-800 dark:bg-gray-600 text-white px-5 py-2 rounded-md text-sm font-medium hover:bg-gray-700 dark:hover:bg-gray-500 whitespace-nowrap">Add Problem</button>
            <button type="button" class="bg-gray-500 dark:bg-gray-600 text-white px-5 py-2 rounded-md text-sm font-medium hover:bg-gray-400 whitespace-nowrap">Notes</button>
            <button type="button" class="bg-indigo-600 text-white px-5 py-2 rounded-md text-sm font-medium hover:bg-indigo-500 whitespace-nowrap">Member Main</button>
            <button type="button" class="bg-orange-500 text-white px-5 py-2 rounded-md text-sm font-medium hover:bg-orange-400 whitespace-nowrap">NOTIFY</button>
        </div>
    </div>

    <div class="flex gap-4">
        <!-- Left Sidebar: Category Filters -->
        <div class="shrink-0" style="width: 11rem;">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow py-2 w-full">
                @foreach(\App\Enums\ProblemType::cases() as $type)
                    <button type="button" wire:click="setFilter('{{ $type->value }}')"
                        @class([
                            'w-full text-center px-3 py-3 text-sm font-medium transition',
                            'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300' => $activeFilter === $type->value,
                            'text-indigo-600 dark:text-indigo-400 hover:bg-gray-50 dark:hover:bg-gray-700/50' => $activeFilter !== $type->value,
                        ])>
                        {{ $type->label() }}
                    </button>
                @endforeach
                <button type="button" wire:click="clearFilter"
                    @class([
                        'w-full text-center px-3 py-3 text-sm font-medium transition',
                        'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300' => !$activeFilter,
                        'text-indigo-600 dark:text-indigo-400 hover:bg-gray-50 dark:hover:bg-gray-700/50' => $activeFilter,
                    ])>
                    All Categories
                </button>
            </div>
        </div>

        <!-- Main Content: PTR Table -->
        <div class="flex-1 min-w-0 bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <!-- Search & Status Filter Bar -->
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px]" style="position:relative;">
                    <div style="position:absolute;top:0;bottom:0;left:12px;display:flex;align-items:center;pointer-events:none;">
                        <svg style="width:16px;height:16px;color:#9ca3af;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by problem name or code..." style="padding-left:36px;" class="block w-full pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
                <select wire:model.live="statusFilter" class="text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md focus:border-indigo-500 focus:ring-indigo-500 py-2 px-3">
                    <option value="">All Statuses</option>
                    @foreach(\App\Enums\ProblemState::cases() as $state)
                        <option value="{{ $state->value }}">{{ ucfirst($state->value) }}</option>
                    @endforeach
                </select>
                @if($this->hasActiveFilters)
                <button type="button" wire:click="clearAllFilters" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 font-medium whitespace-nowrap">Clear filters</button>
                @endif
            </div>
            <table class="w-full table-fixed">
                <thead>
                    <tr class="border-b-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                        <th class="w-[30%] px-5 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Problem</th>
                        <th class="w-[25%] px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Task</th>
                        <th class="w-[15%] px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Resource</th>
                        <th class="w-[30%] px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    @forelse($this->problems as $problem)
                        {{-- Problem Row --}}
                        <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 border-t-2 border-gray-200 dark:border-gray-600 first:border-t-0">
                            <td class="px-5 py-4 text-sm font-semibold text-gray-900 dark:text-white align-top">
                                @if($problem->tasks->count() > 0)
                                <button type="button" @click="toggle({{ $problem->id }})" class="inline-flex items-center gap-2 group">
                                    <svg :class="isExpanded({{ $problem->id }}) ? 'rotate-90' : ''" class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 transition-transform duration-200 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                    <span>{{ $problem->name }}</span>
                                </button>
                                @else
                                {{ $problem->name }}
                                @endif
                            </td>
                            <td class="px-4 py-4"></td>
                            <td class="px-4 py-4"></td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-2 flex-wrap">
                                    @if($problem->isLockedByAnother(auth()->id()))
                                        <span class="text-xs text-amber-600 dark:text-amber-400 italic">Locked by {{ $problem->lockedByUser?->name ?? 'another user' }}</span>
                                    @endif
                                    @can('confirm', $problem)
                                    <button type="button"
                                        @if($problem->state === \App\Enums\ProblemState::Added && !$problem->isLockedByAnother(auth()->id())) @click="confirmProblemId = {{ $problem->id }}; showConfirmDialog = true" @endif
                                        @class([
                                            'px-3.5 py-1 rounded-full text-xs font-semibold transition',
                                            'bg-green-500 text-white hover:bg-green-600 shadow-sm' => $problem->state === \App\Enums\ProblemState::Added && !$problem->isLockedByAnother(auth()->id()),
                                            'bg-green-50 text-green-300 dark:bg-green-900/10 dark:text-green-800 cursor-default' => $problem->state !== \App\Enums\ProblemState::Added || $problem->isLockedByAnother(auth()->id()),
                                        ])>Confirm</button>
                                    @else
                                    <span class="px-3.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-300 dark:bg-green-900/10 dark:text-green-800 cursor-default">Confirm</span>
                                    @endcan
                                    <button type="button"
                                        @if($problem->state === \App\Enums\ProblemState::Confirmed) @click="resolveProblemId = {{ $problem->id }}; showResolveDialog = true" @endif
                                        @class([
                                            'px-3.5 py-1 rounded-full text-xs font-semibold transition',
                                            'bg-rose-500 text-white hover:bg-rose-600 shadow-sm' => $problem->state === \App\Enums\ProblemState::Confirmed,
                                            'bg-rose-50 text-rose-300 dark:bg-rose-900/10 dark:text-rose-800 cursor-default' => $problem->state !== \App\Enums\ProblemState::Confirmed,
                                        ])>Resolve</button>
                                    @can('unconfirm', $problem)
                                    <button type="button"
                                        wire:click="openUnconfirmModal({{ $problem->id }})" @click="showUnconfirmModal = true"
                                        class="px-3.5 py-1 rounded-full text-xs font-semibold bg-amber-500 text-white hover:bg-amber-600 shadow-sm transition">Unconfirm</button>
                                    @endcan
                                    @can('unresolve', $problem)
                                    <button type="button"
                                        wire:click="openUnresolveModal({{ $problem->id }})" @click="showUnresolveModal = true"
                                        class="px-3.5 py-1 rounded-full text-xs font-semibold bg-amber-500 text-white hover:bg-amber-600 shadow-sm transition">Unresolve</button>
                                    @endcan
                                    <button type="button" wire:click="$dispatch('open-problem-detail', { problemId: {{ $problem->id }} })" title="Click to view Problem Details" class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold bg-indigo-100 text-indigo-600 hover:bg-indigo-200 dark:bg-indigo-900/40 dark:text-indigo-300 transition shrink-0">?</button>
                                    <button type="button"
                                        @if($problem->state === \App\Enums\ProblemState::Confirmed) wire:click="openAddTaskModal({{ $problem->id }})" @click="showAddTaskModal = true" @endif
                                        title="{{ $problem->state === \App\Enums\ProblemState::Confirmed ? 'Click to ADD Task to Problem' : 'Tasks may be added only if a Problem has been confirmed but not resolved' }}" class="inline-flex items-center justify-center w-7 h-7 rounded-full text-lg text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-600 dark:hover:text-gray-200 transition shrink-0">+</button>
                                    @if($problem->tasks->count() > 0)
                                    <button type="button" wire:click="$dispatch('open-problem-detail', { problemId: {{ $problem->id }} })" title="View details" class="inline-flex items-center justify-center w-7 h-7 rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-600 dark:hover:text-gray-200 transition shrink-0">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        {{-- Task Rows (collapsible) --}}
                        @foreach($problem->tasks as $task)
                            <tr x-show="isExpanded({{ $problem->id }})" x-cloak class="bg-gray-50 dark:bg-gray-700/30 hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                <td class="py-3 pl-12 pr-2 align-top">
                                    <span class="text-gray-300 dark:text-gray-600 text-sm">&#8627;</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 align-top">
                                    @if($task->resources->count() > 0)
                                    <button type="button" @click="toggleTask({{ $task->id }})" class="inline-flex items-center gap-1.5 group">
                                        <svg :class="isTaskExpanded({{ $task->id }}) ? 'rotate-90' : ''" class="w-3.5 h-3.5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 transition-transform duration-200 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                        <span>{{ $task->name }}</span>
                                    </button>
                                    @else
                                    {{ $task->name }}
                                    @endif
                                </td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2 flex-wrap">
                                        @if($task->state === \App\Enums\TaskState::Completed && $task->completion_type)
                                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-600 dark:bg-gray-600 dark:text-gray-300">{{ $task->completion_type->label() }}</span>
                                        @elseif($task->state === \App\Enums\TaskState::Added || $task->state === \App\Enums\TaskState::Approved)
                                            <button type="button" wire:click="startTask({{ $task->id }})" class="px-3.5 py-1 rounded-full text-xs font-semibold bg-green-500 text-white hover:bg-green-600 transition shadow-sm">Start</button>
                                        @elseif($task->state === \App\Enums\TaskState::Started)
                                            <button type="button" wire:click="completeTask({{ $task->id }})" class="px-3.5 py-1 rounded-full text-xs font-semibold bg-rose-500 text-white hover:bg-rose-600 transition shadow-sm">Complete</button>
                                        @endif
                                        <button type="button" wire:click="$dispatch('open-task-detail', { taskId: {{ $task->id }} })" title="Click to view Task Details" class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold bg-indigo-100 text-indigo-600 hover:bg-indigo-200 dark:bg-indigo-900/40 dark:text-indigo-300 transition shrink-0">?</button>
                                        @if(($task->state === \App\Enums\TaskState::Started || $task->state === \App\Enums\TaskState::Completed) && !in_array($task->completion_type, [\App\Enums\TaskCompletionType::ProblemUnconfirmed, \App\Enums\TaskCompletionType::ProblemResolved]))
                                            <button type="button" wire:click="openAddResourceModal({{ $task->id }})" @click="showAddResourceModal = true" title="Click to ADD Resource to Task" class="inline-flex items-center justify-center w-7 h-7 rounded-full text-lg text-gray-400 hover:bg-gray-300 hover:text-gray-600 dark:hover:bg-gray-600 dark:hover:text-gray-200 transition shrink-0">+</button>
                                        @endif
                                        @if($task->resources->count() > 0)
                                        <button type="button" wire:click="$dispatch('open-task-detail', { taskId: {{ $task->id }} })" title="View details" class="inline-flex items-center justify-center w-7 h-7 rounded-full text-gray-400 hover:bg-gray-300 hover:text-gray-600 dark:hover:bg-gray-600 dark:hover:text-gray-200 transition shrink-0">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            {{-- Resource Rows (collapsible under both problem AND task) --}}
                            @foreach($task->resources as $resource)
                                <tr x-show="isExpanded({{ $problem->id }}) && isTaskExpanded({{ $task->id }})" x-cloak class="bg-gray-100/80 dark:bg-gray-700/20">
                                    <td class="py-2.5 pl-12 pr-2">
                                        <span class="text-gray-200 dark:text-gray-700 text-sm">&#8627;</span>
                                    </td>
                                    <td class="py-2.5 pl-12 pr-2">
                                        <span class="text-gray-200 dark:text-gray-700 text-sm">&#8627;</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400">{{ $resource->survey_name }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button" title="Click to view Resource Details" class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold bg-indigo-100 text-indigo-600 hover:bg-indigo-200 dark:bg-indigo-900/40 dark:text-indigo-300 transition shrink-0">?</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-sm">
                                @if($this->hasActiveFilters)
                                    <p class="text-gray-400 dark:text-gray-500">No problems found matching your search criteria.</p>
                                    <button type="button" wire:click="clearAllFilters" class="mt-2 text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 font-medium text-sm">Clear filters</button>
                                @else
                                    <p class="text-gray-400 dark:text-gray-500">No problems found for this member.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->problems->links() }}
            </div>
        </div>
    </div>

    {{-- ══════ MODALS ══════ --}}

    {{-- Confirm Problem Dialog --}}
    <div x-show="showConfirmDialog" x-cloak class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" @keydown.escape.window="showConfirmDialog = false">
        <div class="fixed inset-0" @click="showConfirmDialog = false"><div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div></div>
        <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl sm:w-full sm:max-w-md sm:mx-auto relative" @click.stop>
            <div class="px-6 py-5"><p class="text-base font-medium text-gray-900 dark:text-gray-100">Would you like to CONFIRM this Problem?</p></div>
            <div class="flex justify-end px-6 py-4 bg-gray-100 dark:bg-gray-700">
                <button type="button" @click="showConfirmDialog = false; $wire.confirmProblem(confirmProblemId)" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 px-4 py-2">OK</button>
            </div>
        </div>
    </div>

    {{-- Resolve Problem Dialog --}}
    <div x-show="showResolveDialog" x-cloak class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" @keydown.escape.window="showResolveDialog = false">
        <div class="fixed inset-0" @click="showResolveDialog = false"><div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div></div>
        <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl sm:w-full sm:max-w-md sm:mx-auto relative" @click.stop>
            <div class="px-6 py-5"><p class="text-base font-medium text-gray-900 dark:text-gray-100">Would you like to RESOLVE this Problem?</p></div>
            <div class="flex justify-end px-6 py-4 bg-gray-100 dark:bg-gray-700">
                <button type="button" @click="showResolveDialog = false; $wire.resolveProblem(resolveProblemId)" class="text-sm font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 px-4 py-2">OK</button>
            </div>
        </div>
    </div>

    {{-- Add Problem Modal --}}
    <div x-show="showAddProblemModal" x-cloak class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" @keydown.escape.window="showAddProblemModal = false">
        <div class="fixed inset-0" @click="showAddProblemModal = false"><div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div></div>
        <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl sm:w-full sm:max-w-lg sm:mx-auto relative" @click.stop>
            <div class="px-6 py-4">
                <div class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Add New Problem') }}</div>
                <div class="mt-4 space-y-6">
                    <div>
                        <x-label for="problemType" value="{{ __('Problem Type') }}" />
                        <select id="problemType" wire:model="problemType" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="">{{ __('Select a type...') }}</option>
                            @foreach(\App\Enums\ProblemType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="problemType" class="mt-2" />
                    </div>
                    <div>
                        <x-label for="problemName" value="{{ __('Problem Name') }}" />
                        <x-input id="problemName" type="text" class="mt-1 block w-full" wire:model="problemName" />
                        <x-input-error for="problemName" class="mt-2" />
                    </div>
                    <div>
                        <x-label for="problemCode" value="{{ __('Code') }}" />
                        <x-input id="problemCode" type="text" class="mt-1 block w-full" wire:model="problemCode" placeholder="{{ __('Optional') }}" />
                    </div>
                    <div>
                        <x-label for="problemEncounterSetting" value="{{ __('Encounter Setting') }}" />
                        <select id="problemEncounterSetting" wire:model="problemEncounterSetting" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="">{{ __('Select a setting...') }}</option>
                            @foreach(\App\Enums\EncounterSetting::cases() as $setting)
                                <option value="{{ $setting->value }}">{{ $setting->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex justify-end px-6 py-4 bg-gray-100 dark:bg-gray-700">
                <x-secondary-button type="button" @click="showAddProblemModal = false">{{ __('Cancel') }}</x-secondary-button>
                <x-button class="ms-3" wire:click="saveProblem" @click="showAddProblemModal = false">{{ __('Add Problem') }}</x-button>
            </div>
        </div>
    </div>

    {{-- Add Task Modal --}}
    <div x-show="showAddTaskModal" x-cloak class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" @keydown.escape.window="showAddTaskModal = false">
        <div class="fixed inset-0" @click="showAddTaskModal = false"><div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div></div>
        <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl sm:w-full sm:max-w-lg sm:mx-auto relative" @click.stop>
            <div class="px-6 py-4">
                <div class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Add New Task') }}</div>
                <div class="mt-4 space-y-6">
                    <div>
                        <x-label for="taskType" value="{{ __('Task Type') }}" />
                        <select id="taskType" wire:model="taskType" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="">{{ __('Enter Task Type') }}</option>
                            @foreach(\App\Enums\TaskType::cases() as $tt)
                                @if($tt !== \App\Enums\TaskType::Goal)
                                    <option value="{{ $tt->value }}">{{ $tt->label() }}</option>
                                @endif
                            @endforeach
                        </select>
                        <x-input-error for="taskType" class="mt-2" />
                    </div>
                    <div>
                        <x-label for="taskName" value="{{ __('Task') }}" />
                        <x-input id="taskName" type="text" class="mt-1 block w-full" wire:model="taskName" />
                        <x-input-error for="taskName" class="mt-2" />
                    </div>
                    <div>
                        <x-label for="taskCode" value="{{ __('Task Code') }}" />
                        <x-input id="taskCode" type="text" class="mt-1 block w-full" wire:model="taskCode" />
                    </div>
                    <div>
                        <x-label for="taskEncounterSetting" value="{{ __('Encounter Setting') }}" />
                        <select id="taskEncounterSetting" wire:model="taskEncounterSetting" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="">{{ __('Choose Setting') }}</option>
                            @foreach(\App\Enums\EncounterSetting::cases() as $setting)
                                <option value="{{ $setting->value }}">{{ $setting->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-label value="{{ __('Associated Problem') }}" />
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $this->getTaskProblemName() }}</p>
                    </div>
                    <x-input-error for="taskProblemId" class="mt-2" />
                </div>
            </div>
            <div class="flex justify-end px-6 py-4 bg-gray-100 dark:bg-gray-700">
                <x-secondary-button type="button" @click="showAddTaskModal = false">{{ __('Cancel') }}</x-secondary-button>
                <x-button class="ms-3" wire:click="saveTask" @click="showAddTaskModal = false">{{ __('Add Task') }}</x-button>
            </div>
        </div>
    </div>

    {{-- Add Resource Modal --}}
    <div x-show="showAddResourceModal" x-cloak class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" @keydown.escape.window="showAddResourceModal = false">
        <div class="fixed inset-0" @click="showAddResourceModal = false"><div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div></div>
        <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl sm:w-full sm:max-w-lg sm:mx-auto relative" @click.stop>
            <div class="px-6 py-4">
                <div class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Add Resource') }}</div>
                <div class="mt-4 space-y-6">
                    <div>
                        <x-label for="surveyName" value="{{ __('Survey Name') }}" />
                        <x-input id="surveyName" type="text" class="mt-1 block w-full" wire:model="surveyName" />
                        <x-input-error for="surveyName" class="mt-2" />
                    </div>
                    <div>
                        <x-label for="atHome" value="{{ __('At Home') }}" />
                        <select id="atHome" wire:model="atHome" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="">{{ __('Choose') }}</option>
                            @foreach(\App\Enums\ResourceRating::cases() as $rating)
                                <option value="{{ $rating->value }}">{{ $rating->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="atHome" class="mt-2" />
                    </div>
                    <div>
                        <x-label for="atWork" value="{{ __('At Work') }}" />
                        <select id="atWork" wire:model="atWork" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="">{{ __('Choose') }}</option>
                            @foreach(\App\Enums\ResourceRating::cases() as $rating)
                                <option value="{{ $rating->value }}">{{ $rating->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="atWork" class="mt-2" />
                    </div>
                    <div>
                        <x-label for="atPlay" value="{{ __('At Play') }}" />
                        <select id="atPlay" wire:model="atPlay" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                            <option value="">{{ __('Choose') }}</option>
                            @foreach(\App\Enums\ResourceRating::cases() as $rating)
                                <option value="{{ $rating->value }}">{{ $rating->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="atPlay" class="mt-2" />
                    </div>
                    <x-input-error for="resourceTaskId" class="mt-2" />
                </div>
            </div>
            <div class="flex justify-end px-6 py-4 bg-gray-100 dark:bg-gray-700">
                <x-secondary-button type="button" @click="showAddResourceModal = false">{{ __('Cancel') }}</x-secondary-button>
                <x-button class="ms-3" wire:click="saveResource" @click="showAddResourceModal = false">{{ __('OK') }}</x-button>
            </div>
        </div>
    </div>

    {{-- Unconfirm Problem Modal --}}
    <div x-show="showUnconfirmModal" x-cloak class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" @keydown.escape.window="showUnconfirmModal = false">
        <div class="fixed inset-0" @click="showUnconfirmModal = false"><div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div></div>
        <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl sm:w-full sm:max-w-md sm:mx-auto relative" @click.stop>
            <div class="px-6 py-5">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Unconfirm Problem</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This will revert the problem to Added status. All incomplete child tasks will be auto-completed with status "Complete – Problem Unconfirmed".</p>
                <div class="mt-4">
                    <x-label for="unconfirmNote" value="{{ __('Explanatory Note (required)') }}" />
                    <textarea id="unconfirmNote" wire:model="unconfirmNote" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" placeholder="Enter the reason for unconfirming this problem..."></textarea>
                    <x-input-error for="unconfirmNote" class="mt-2" />
                </div>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 bg-gray-100 dark:bg-gray-700">
                <x-secondary-button type="button" @click="showUnconfirmModal = false">{{ __('Cancel') }}</x-secondary-button>
                <button type="button" wire:click="unconfirmProblem" @click="showUnconfirmModal = false" class="inline-flex items-center px-4 py-2 bg-amber-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-600 transition">{{ __('Unconfirm') }}</button>
            </div>
        </div>
    </div>

    {{-- Reactivation Dialog (shown when re-confirming a problem that had cascaded tasks) --}}
    <div x-show="showReactivationDialog" x-cloak class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" @keydown.escape.window="showReactivationDialog = false">
        <div class="fixed inset-0" @click="showReactivationDialog = false"><div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div></div>
        <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl sm:w-full sm:max-w-md sm:mx-auto relative" @click.stop>
            <div class="px-6 py-5">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Reactivate Tasks?</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This problem has <span class="font-semibold" x-text="reactivationTaskCount"></span> task(s) that were auto-completed when it was previously unconfirmed. Would you like to reactivate them?</p>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 bg-gray-100 dark:bg-gray-700">
                <x-secondary-button type="button" @click="showReactivationDialog = false; $wire.confirmProblem(reactivationProblemId, false)">{{ __('No, just confirm') }}</x-secondary-button>
                <x-button type="button" @click="showReactivationDialog = false; $wire.confirmProblem(reactivationProblemId, true)">{{ __('Yes, reactivate tasks') }}</x-button>
            </div>
        </div>
    </div>

    {{-- Unresolve Problem Modal --}}
    <div x-show="showUnresolveModal" x-cloak class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" @keydown.escape.window="showUnresolveModal = false">
        <div class="fixed inset-0" @click="showUnresolveModal = false"><div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div></div>
        <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl sm:w-full sm:max-w-md sm:mx-auto relative" @click.stop>
            <div class="px-6 py-5">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Unresolve Problem</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This will revert the problem to Confirmed status. Tasks that were auto-completed will be offered for reactivation.</p>
                <div class="mt-4">
                    <x-label for="unresolveNote" value="{{ __('Explanatory Note (required)') }}" />
                    <textarea id="unresolveNote" wire:model="unresolveNote" rows="3" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" placeholder="Enter the reason for unresolving this problem..."></textarea>
                    <x-input-error for="unresolveNote" class="mt-2" />
                </div>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 bg-gray-100 dark:bg-gray-700">
                <x-secondary-button type="button" @click="showUnresolveModal = false">{{ __('Cancel') }}</x-secondary-button>
                <button type="button" wire:click="unresolveProblem" @click="showUnresolveModal = false" class="inline-flex items-center px-4 py-2 bg-amber-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-600 transition">{{ __('Unresolve') }}</button>
            </div>
        </div>
    </div>

    {{-- Resolve Reactivation Dialog --}}
    <div x-show="showResolveReactivationDialog" x-cloak class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" @keydown.escape.window="showResolveReactivationDialog = false">
        <div class="fixed inset-0" @click="showResolveReactivationDialog = false"><div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div></div>
        <div class="bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl sm:w-full sm:max-w-md sm:mx-auto relative" @click.stop>
            <div class="px-6 py-5">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Reactivate Tasks?</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This problem has <span class="font-semibold" x-text="resolveReactivationTaskCount"></span> task(s) that were auto-completed when it was resolved. Would you like to reactivate them?</p>
            </div>
            <div class="flex justify-end gap-2 px-6 py-4 bg-gray-100 dark:bg-gray-700">
                <x-secondary-button type="button" @click="showResolveReactivationDialog = false">{{ __('No, leave as is') }}</x-secondary-button>
                <x-button type="button" @click="showResolveReactivationDialog = false; $wire.reactivateResolvedTasks(resolveReactivationProblemId)">{{ __('Yes, reactivate tasks') }}</x-button>
            </div>
        </div>
    </div>

    {{-- Detail modals --}}
    @livewire('care-management.problem-detail', ['memberId' => $member->id], key('problem-detail-' . $member->id))
    @livewire('care-management.task-detail', key('task-detail'))

    @endif {{-- end JI consent check --}}
</div>
