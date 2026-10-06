<?php

declare(strict_types=1);

namespace Espo\Modules\AttendanceManagement\Tools\Attendance;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Acl;
use Espo\Entities\User;

final class AttendanceAccessChecker
{
    public function __construct(
        private Acl $acl,
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

        return $this->user->isAdmin() || $this->acl->check('AttendanceOverview');
    }

    public function assertManager(): void
    {
        if (!$this->isManager()) {
            throw new Forbidden('Attendance overview access is restricted to configured managers.');
        }
    }

    public function assertScheduleEditor(): void
    {
        $this->assertManager();
    }
}
