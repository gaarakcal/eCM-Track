<?php

namespace App\Enums;

enum UserRole: string
{
    case CareManager = 'care_manager';
    case Supervisor = 'supervisor';
    case AuthorizedClinician = 'authorized_clinician';
    case CommunityHealthWorker = 'community_health_worker';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::CareManager => 'Care Manager',
            self::Supervisor => 'Supervisor',
            self::AuthorizedClinician => 'Authorized Clinician',
            self::CommunityHealthWorker => 'Community Health Worker',
            self::Admin => 'Admin',
        };
    }

    public function canConfirmProblem(): bool
    {
        return in_array($this, [
            self::CareManager,
            self::Supervisor,
            self::AuthorizedClinician,
            self::Admin,
        ]);
    }

    public function canUnconfirmProblem(): bool
    {
        return in_array($this, [
            self::Supervisor,
            self::AuthorizedClinician,
            self::Admin,
        ]);
    }

    public function canResolveProblem(): bool
    {
        return in_array($this, [
            self::CareManager,
            self::Supervisor,
            self::AuthorizedClinician,
            self::Admin,
        ]);
    }

    public function canUnresolveProblem(): bool
    {
        return in_array($this, [
            self::Supervisor,
            self::AuthorizedClinician,
            self::Admin,
        ]);
    }
}
