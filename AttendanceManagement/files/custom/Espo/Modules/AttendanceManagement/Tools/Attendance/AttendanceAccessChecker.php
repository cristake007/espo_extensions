<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Config;
use Espo\Entities\User;

final class AttendanceAccessChecker
{
    public function __construct(
        private Config $config,
        private User $user,
    ) {}

    public function isManager(): bool
    {
        if (
            !(bool) $this->user->get('isActive') ||
            !in_array($this->user->get('type'), [User::TYPE_REGULAR, User::TYPE_ADMIN], true)
        ) {
            return false;
        }

        $userId = $this->user->getId();
        $managerIds = $this->config->get('attendanceManagementManagersIds') ?? [];
        $approverIds = $this->config->get('holidayManagementApproversIds') ?? [];

        return
            is_string($userId) &&
            (
                (is_array($managerIds) && in_array($userId, $managerIds, true)) ||
                (is_array($approverIds) && in_array($userId, $approverIds, true))
            );
    }

    public function assertManager(): void
    {
        if (!$this->isManager()) {
            throw new Forbidden('Attendance overview access is restricted to configured managers.');
        }
    }

    public function assertScheduleEditor(): void
    {
        if (!$this->user->isAdmin() && !$this->isManager()) {
            throw new Forbidden('Working schedules can only be changed by an administrator or attendance manager.');
        }
    }
}
