<?php

namespace App\Models;

use App\Enums\EncounterSetting;
use App\Enums\ProblemState;
use App\Enums\ProblemType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Problem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'member_id', 'name', 'type', 'code', 'encounter_setting',
        'state', 'submitted_by', 'submitted_at', 'confirmed_by',
        'confirmed_at', 'resolved_by', 'resolved_at', 'lock_version',
        'locked_by', 'locked_at', 'care_plan_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProblemType::class,
            'state' => ProblemState::class,
            'encounter_setting' => EncounterSetting::class,
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function stateHistory(): MorphMany
    {
        return $this->morphMany(StateChangeHistory::class, 'trackable');
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeByType($query, ProblemType $type)
    {
        return $query->where('type', $type);
    }

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isLockedByAnother(?int $userId): bool
    {
        if (!$this->locked_by) {
            return false;
        }

        return $this->locked_by !== $userId;
    }

    public function isConfirmed(): bool
    {
        return $this->state === ProblemState::Confirmed;
    }

    public function isResolved(): bool
    {
        return $this->state === ProblemState::Resolved;
    }
}
