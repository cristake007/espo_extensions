<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Tools\HolidayRequest;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Language;
use Espo\Entities\User;

final class HolidayRequestManagerAccessChecker
{
    private const SCOPE = 'HolidayRequestManagement';

    public function __construct(
        private Acl $acl,
        private User $user,
        private Language $language,
    ) {}

    public function isManager(): bool
    {
        return
            (bool) $this->user->get('isActive') &&
            in_array($this->user->get('type'), [User::TYPE_REGULAR, User::TYPE_ADMIN], true) &&
            ($this->user->isAdmin() || $this->acl->check(self::SCOPE));
    }

    public function assertManager(): void
    {
        if (!$this->isManager()) {
            throw new Forbidden($this->language->translateLabel(
                'managedBookingForbidden',
                'messages',
                'HolidayRequest',
            ));
        }
    }
}
