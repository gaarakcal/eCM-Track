<?php

namespace App\Enums;

enum UserRole: string
{
    case CareManager = 'care_manager';
    case Supervisor = 'supervisor';
    case AuthorizedClinician = 'authorized_clinician';
    case CommunityHealthWorker = 'community_health_worker';
    case Admin = 'admin';
    case ComplianceOfficer = 'compliance_officer';

    public function label(): string
    {
        return match ($this) {
            self::CareManager => 'Care Manager',
            self::Supervisor => 'Supervisor',
            self::AuthorizedClinician => 'Authorized Clinician',
            self::CommunityHealthWorker => 'Community Health Worker',
            self::Admin => 'Admin',
            self::ComplianceOfficer => 'Compliance Officer',
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

    public function canUncompleteTask(): bool
    {
        return in_array($this, [
            self::Supervisor,
            self::AuthorizedClinician,
            self::Admin,
        ]);
    }

    public function canApproveTask(): bool
    {
        return in_array($this, [
            self::Supervisor,
            self::AuthorizedClinician,
            self::Admin,
        ]);
    }

    public function canCreateGoal(): bool
    {
        return in_array($this, [
            self::CareManager,
            self::Supervisor,
            self::Admin,
        ]);
    }

    public function canAddProblem(): bool
    {
        return in_array($this, [
            self::CareManager,
            self::CommunityHealthWorker,
            self::Supervisor,
            self::AuthorizedClinician,
        ]);
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function canReleaseLock(): bool
    {
        return $this === self::Admin;
    }

    public function canConfigureNotifications(): bool
    {
        return $this === self::Admin;
    }

    public function isReadOnly(): bool
    {
        return $this === self::Admin;
    }

    public function canLogOutreach(): bool
    {
        return in_array($this, [
            self::CareManager,
            self::Supervisor,
            self::CommunityHealthWorker,
        ]);
    }

    public function canAddNote(): bool
    {
        return in_array($this, [
            self::CareManager,
            self::Supervisor,
            self::AuthorizedClinician,
            self::Admin,
        ]);
    }

    public function canOverrideConsentBlock(): bool
    {
        return $this === self::ComplianceOfficer;
    }

    public function isComplianceOfficer(): bool
    {
        return $this === self::ComplianceOfficer;
    }

    public function requiresDeIdentification(): bool
    {
        return $this === self::ComplianceOfficer;
    }
}
