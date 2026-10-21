<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Tools\Attendance;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use stdClass;

final class AttendanceSettingsService
{
    public function __construct(
        private \Espo\Modules\EmployeeManagement\Tools\Administration\AdministrationAccessChecker $accessChecker,
        private Config $config,
        private ConfigWriter $configWriter,
        private \Espo\Core\Utils\DateTime $dateTime,
    ) {}

    /** @return array<string, bool|int|string> */
    public function get(): array
    {
        $this->accessChecker->assertScope('EmployeeAttendanceSettings');

        return [
            'editablePastMonths' => (int) $this->config->get(
                'employeeAttendanceEditablePastMonths',
                1,
            ),
            'allowIncompleteExports' => (bool) $this->config->get(
                'employeeAttendanceAllowIncompleteExports',
                false,
            ),
            'archiveStartMonth' => (string) $this->config->get(
                'employeeAttendanceArchiveStartMonth',
                '',
            ),
            'attendanceTestDataResetAllowed' =>
                $this->accessChecker->canResetAttendanceData(),
        ];
    }

    /** @return array<string, bool|int|string> */
    public function update(stdClass $data): array
    {
        $this->accessChecker->assertScope('EmployeeAttendanceSettings');

        $this->accessChecker->assertKeys($data, ['editablePastMonths', 'allowIncompleteExports']);
        $editablePastMonths = $data->editablePastMonths ?? null;
        $allowIncompleteExports = $data->allowIncompleteExports ?? null;
        if (
            !is_int($editablePastMonths) ||
            $editablePastMonths < 0 ||
            $editablePastMonths > 120 ||
            !is_bool($allowIncompleteExports)
        ) {
            throw new BadRequest('Invalid attendance management settings.');
        }

        $this->configWriter->setMultiple([
            'employeeAttendanceEditablePastMonths' => $editablePastMonths,
            'employeeAttendanceAllowIncompleteExports' => $allowIncompleteExports,
        ]);
        $this->configWriter->save();

        return [
            'editablePastMonths' => $editablePastMonths,
            'allowIncompleteExports' => $allowIncompleteExports,
            'archiveStartMonth' => (string) $this->config->get(
                'employeeAttendanceArchiveStartMonth',
                '',
            ),
        ];
    }

    /** Original archive visibility initializer; never deletes attendance records or norms. */
    public function initializeArchive(): array
    {
        $this->accessChecker->assertScope('EmployeeAttendanceSettings');
        $archiveStartMonth=substr($this->dateTime->getToday()->toString(),0,7);
        $this->configWriter->set('employeeAttendanceArchiveStartMonth',$archiveStartMonth);
        $this->configWriter->save();
        return ['archiveStartMonth'=>$archiveStartMonth];
    }
}
