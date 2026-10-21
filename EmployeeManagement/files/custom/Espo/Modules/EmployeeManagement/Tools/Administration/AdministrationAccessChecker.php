<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\Administration;

use Espo\Core\Acl;
use Espo\Core\Exceptions\{BadRequest, Forbidden};
use Espo\Entities\User;
use stdClass;

/** Intersection of native role permissions and business eligibility, not a role registry. */
final class AdministrationAccessChecker
{
    public function __construct(private Acl $acl, private User $user) {}

    public function assertScope(string $scope): void
    {
        if (!$this->user->isRegular() || !$this->user->isActive() ||
            !$this->acl->checkScope('EmployeeAdministration') || !$this->acl->checkScope($scope)) {
            throw new Forbidden('Employee Management administration access denied.');
        }
    }

    public function canManageProfiles(): bool
    {
        try { $this->assertProfiles(); return true; } catch (Forbidden) { return false; }
    }

    public function canResetHolidayData(): bool
    {
        try { $this->assertHolidayDataReset(); return true; } catch (Forbidden) { return false; }
    }

    public function canResetAttendanceData(): bool
    {
        try { $this->assertAttendanceDataReset(); return true; } catch (Forbidden) { return false; }
    }

    public function assertAttendanceDataReset(): void
    {
        foreach (['EmployeeAttendanceSettings', 'EmployeeAttendanceManagement'] as $scope) {
            $this->assertScope($scope);
        }
        if ($this->acl->getLevel('EmployeeAttendanceRecord', 'read') !== 'all' ||
            $this->acl->getLevel('EmployeeAttendanceRecord', 'delete') !== 'all' ||
            array_intersect(
                ['id', 'assignedUserId', 'date', 'status', 'source', 'markedAt', 'revision'],
                $this->acl->getScopeForbiddenAttributeList('EmployeeAttendanceRecord', 'read'),
            )) {
            throw new Forbidden(
                'Attendance test-data reset requires native read-all, delete-all and field access.'
            );
        }
    }

    public function assertHolidayDataReset(): void
    {
        $this->assertProfiles();
        foreach (['EmployeeHolidaySettings', 'EmployeeHolidayRequestManagement', 'EmployeeHolidayBalanceCorrection'] as $scope) {
            $this->assertScope($scope);
        }
        if ($this->acl->getLevel('EmployeeHolidayRequest', 'read') !== 'all' ||
            array_intersect(
                ['id', 'assignedUserId', 'dateStartDate', 'dateEndDate', 'days', 'status', 'approvalDocuments'],
                $this->acl->getScopeForbiddenAttributeList('EmployeeHolidayRequest', 'read'),
            )) {
            throw new Forbidden('Holiday test-data reset requires native read-all and field access to requests.');
        }
    }

    public function assertProfiles(): void
    {
        $this->assertScope('EmployeeHolidayProfileManagement');
        foreach (['EmployeeHolidayProfile' => ['name', 'assignedUserId', 'annualEntitlement', 'balance', 'nextResetDate', 'calendarColor', 'isInitialized', 'resetPending'],
            'EmployeeHolidayLedger' => ['id', 'profileId', 'assignedUserId', 'delta', 'balanceBefore', 'balanceAfter', 'entitlementBefore', 'entitlementAfter', 'resetDateBefore', 'resetDateAfter', 'actorId', 'actorName', 'reason', 'effectiveDate', 'createdAt']] as $type => $attributes) {
            if ($this->acl->getLevel($type, 'read') !== 'all' ||
                array_intersect($attributes, $this->acl->getScopeForbiddenAttributeList($type, 'read'))) {
                throw new Forbidden('Profile administration requires native read-all and field access to profiles and ledger.');
            }
        }
    }

    public function assertKeys(stdClass $data, array $allowed): void
    {
        if (array_diff(array_keys(get_object_vars($data)), $allowed)) {
            throw new BadRequest('Unsupported Employee Management parameters.');
        }
    }
}
