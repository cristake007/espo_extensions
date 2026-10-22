<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\Workspace\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\EmployeeManagement\Tools\Administration\AdministrationAccessChecker;

final class GetStatus implements Action
{
    public function __construct(private Acl $acl, private User $user, private AdministrationAccessChecker $administration,
        private \Espo\Modules\EmployeeManagement\Tools\Attendance\AttendanceAccessChecker $attendance) {}

    public function process(Request $request): Response
    {
        $scope = $request->getRouteParam('area') === 'administration'
            ? 'EmployeeAdministration' : 'EmployeeManagement';

        if (!$this->user->isRegular() || !$this->user->isActive() || !$this->acl->check($scope)) {
            throw new Forbidden('Employee Management workspace requires an authorized active regular user.');
        }

        $read = [];
        foreach (['EmployeeAttendanceRecord', 'EmployeeHolidayRequest', 'EmployeeHolidayProfile', 'EmployeeHolidayLedger'] as $entityType) {
            if ($this->acl->checkScope($entityType, 'read')) {
                $read[] = $entityType;
            }
        }

        $sections = [];
        if ($this->acl->checkScope('EmployeeAdministration')) {
            if ($this->acl->checkScope('EmployeeAttendanceSettings')) { $sections[] = 'attendance'; }
            if ($this->acl->checkScope('EmployeeHolidaySettings')) { $sections[] = 'holiday'; }
            if ($this->administration->canManageProfiles()) { $sections[] = 'profiles'; }
            try { $this->attendance->assertScheduleEditor(); $sections[] = 'attendanceMetadata'; } catch (Forbidden) {}
        }
        return ResponseComposer::json([
            'version' => '0.8.16',
            'readableScopes' => $read,
            'businessMutationsEnabled' => false,
            'settingsEnabled' => count($sections) > 0,
            'administrationSections' => $sections,
            'profileManagementEnabled' => $this->administration->canManageProfiles(),
            'holidayBookingEnabled' => $this->acl->checkScope('EmployeeHolidayBooking') && $this->acl->checkScope('EmployeeHolidayRequest', 'read'),
            'holidayRequestManagementEnabled' => $this->acl->checkScope('EmployeeHolidayRequestManagement') && $this->acl->getLevel('EmployeeHolidayRequest', 'read') === 'all',
            'holidayApprovalEnabled' => $this->acl->checkScope('EmployeeHolidayApproval'),
            'attendanceEnabled' => $this->acl->checkScope('EmployeeAttendance'),
            'attendanceOverviewEnabled' => $this->acl->checkScope('EmployeeAttendanceOverview'),
        ]);
    }
}
