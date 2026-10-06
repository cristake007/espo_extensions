<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use stdClass;

final class AttendanceSettingsService
{
    public function __construct(
        private AttendanceAccessChecker $accessChecker,
        private Config $config,
        private ConfigWriter $configWriter,
        private DateTimeUtil $dateTime,
    ) {}

    /** @return array<string, bool|int|string> */
    public function get(): array
    {
        $this->accessChecker->assertManager();

        return [
            'editablePastMonths' => (int) $this->config->get(
                'attendanceManagementEditablePastMonths',
                1,
            ),
            'allowIncompleteExports' => (bool) $this->config->get(
                'attendanceManagementAllowIncompleteExports',
                false,
            ),
            'archiveStartMonth' => (string) $this->config->get(
                'attendanceManagementArchiveStartMonth',
                '',
            ),
        ];
    }

    /** @return array<string, bool|int|string> */
    public function update(stdClass $data): array
    {
        $this->accessChecker->assertManager();

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
            'attendanceManagementEditablePastMonths' => $editablePastMonths,
            'attendanceManagementAllowIncompleteExports' => $allowIncompleteExports,
        ]);
        $this->configWriter->save();

        return [
            'editablePastMonths' => $editablePastMonths,
            'allowIncompleteExports' => $allowIncompleteExports,
            'archiveStartMonth' => (string) $this->config->get(
                'attendanceManagementArchiveStartMonth',
                '',
            ),
        ];
    }

    /** @return array{archiveStartMonth: string} */
    public function initializeArchive(): array
    {
        $this->accessChecker->assertManager();
        $archiveStartMonth = substr($this->dateTime->getToday()->toString(), 0, 7);

        $this->configWriter->set(
            'attendanceManagementArchiveStartMonth',
            $archiveStartMonth,
        );
        $this->configWriter->save();

        return ['archiveStartMonth' => $archiveStartMonth];
    }
}
