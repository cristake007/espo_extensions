<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Jobs;

use Espo\Core\Job\Job;
use Espo\Core\Job\Job\Data;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidaySubmissionNotifier;

/** One native grouped Job for one request-recipient submission notification. */
final class SendHolidayApprovalNotification implements Job
{
    public function __construct(private HolidaySubmissionNotifier $notifier) {}

    public function run(Data $data): void
    {
        $this->notifier->deliver(
            (string) $data->get('requestId'),
            (string) $data->get('recipientId'),
            (string) $data->get('emailId'),
            (string) $data->get('identity'),
            (int) $data->get('attempt'),
        );
    }
}
