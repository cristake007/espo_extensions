<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Tools\Attendance;
use Espo\Core\Acl;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;

/** Adapted original checker; native Roles are the sole source of rights. */
final class AttendanceAccessChecker
{
    public function __construct(private Acl $acl, private User $user, private \Espo\Core\Utils\Config $config) {}
    public function assertActor(): void
    {
        if (!$this->user->isRegular() || !$this->user->isActive() || !$this->acl->checkScope('EmployeeManagement')) { throw new Forbidden('Active regular Employee Management user required.'); }
    }
    public function assertRead(bool $all = false): void
    {
        $this->assertActor();
        if (!$this->acl->checkScope('EmployeeAttendanceRecord', 'read') ||
            ($all && $this->acl->getLevel('EmployeeAttendanceRecord', 'read') !== 'all') ||
            array_intersect(['assignedUserId','date','status','source','markedAt'], $this->acl->getScopeForbiddenAttributeList('EmployeeAttendanceRecord', 'read'))) {
            throw new Forbidden('Native Attendance record and field read permission required.');
        }
        if (!$this->acl->checkScope('EmployeeAttendanceWorkSchedule','read') ||
            ($all && $this->acl->getLevel('EmployeeAttendanceWorkSchedule','read')!=='all') ||
            array_intersect(['remote','cic','cicStartDate','cicEndDate'], $this->acl->getScopeForbiddenAttributeList('EmployeeAttendanceWorkSchedule','read'))) {
            throw new Forbidden('Native operational metadata read permission required.');
        }
    }
    public function assertSelf(): void { $this->assertRead(); if (!$this->acl->checkScope('EmployeeAttendance')) { throw new Forbidden('Own attendance permission required.'); } }
    public function assertRecordWrite(): void
    {
        if (array_intersect(['status'], $this->acl->getScopeForbiddenAttributeList('EmployeeAttendanceRecord','edit'))) { throw new Forbidden('Native attendance field edit permission required.'); }
    }
    public function assertOverview(): void
    {
        $this->assertRead(true);
        if (!$this->acl->checkScope('EmployeeAttendanceOverview')) { throw new Forbidden('Attendance overview permission required.'); }
    }
    public function isManager(): bool { try { $this->assertManager(); return true; } catch (Forbidden) { return false; } }
    public function assertManager(): void
    {
        $this->assertOverview();
        if (!$this->acl->checkScope('EmployeeAttendanceManagement')) { throw new Forbidden('Attendance management permission required.'); }
    }
    public function assertScheduleEditor(): void
    {
        $this->assertManager();
        if (!$this->acl->checkScope('EmployeeAdministration')) { throw new Forbidden('Delegated administration permission required.'); }
        if ($this->acl->getLevel('EmployeeAttendanceWorkSchedule', 'read') !== 'all' ||
            array_intersect(['assignedUserId','remote','cic','cicStartDate','cicEndDate'], $this->acl->getScopeForbiddenAttributeList('EmployeeAttendanceWorkSchedule','read'))) { throw new Forbidden('Native operational metadata read-all required.'); }
    }
    public function canManageSchedules(): bool { try { $this->assertScheduleEditor(); return true; } catch (Forbidden) { return false; } }
    public function assertScheduleWrite(): void
    {
        $this->assertScheduleEditor();
        if (array_intersect(['remote','cic','cicStartDate','cicEndDate'], $this->acl->getScopeForbiddenAttributeList('EmployeeAttendanceWorkSchedule','edit'))) { throw new Forbidden('Native operational field edit permission required.'); }
    }
    public function assertExport(): void
    {
        $this->assertOverview();
        if ($this->config->get('exportDisabled') || $this->acl->getPermissionLevel(\Espo\Core\Acl\Permission::EXPORT) !== 'yes') { throw new Forbidden('Native export permission required.'); }
    }

    /** The monthly attendance report is an operational manager action, not a global Export grant. */
    public function assertPdfDownload(): void
    {
        $this->assertOverview();
        if ($this->config->get('exportDisabled')) {
            throw new Forbidden('Exports are disabled by the platform.');
        }
        if (!$this->acl->checkScope('EmployeeAttendanceManagement') &&
            $this->acl->getPermissionLevel(\Espo\Core\Acl\Permission::EXPORT) !== 'yes') {
            throw new Forbidden('Attendance management or native export permission required for the monthly PDF.');
        }
    }
}
