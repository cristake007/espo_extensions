<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayAdministration;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;

final class HolidayManagementAccessChecker
{
    private const SETTINGS_SCOPE = 'HolidayManagementSettings';
    private const OVERVIEW_SCOPE = 'HolidayOverview';

    public function __construct(
        private Acl $acl,
        private User $user,
    ) {}

    public function assertSettings(): void
    {
        $this->assertScope(self::SETTINGS_SCOPE, 'Holiday Management settings access denied.');
    }

    public function assertOverview(): void
    {
        $this->assertScope(self::OVERVIEW_SCOPE, 'Holiday overview access denied.');
    }

    private function assertScope(string $scope, string $message): void
    {
        if (
            !(bool) $this->user->get('isActive') ||
            !in_array($this->user->get('type'), [User::TYPE_REGULAR, User::TYPE_ADMIN], true) ||
            (!$this->user->isAdmin() && !$this->acl->check($scope))
        ) {
            throw new Forbidden($message);
        }
    }
}
