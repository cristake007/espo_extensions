<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Jobs;
use Espo\Core\Job\Job;
use Espo\Core\Job\Job\Data;
use Espo\Modules\EmployeeManagement\Tools\HolidayDocument\HolidayApprovalService;

/** Native group Job, no alternate runner/queue. */
final class GenerateHolidayApproval implements Job
{
    public function __construct(private HolidayApprovalService $approvals) {}
    public function run(Data $data): void
    {
        $this->approvals->execute((string)$data->get('requestId'),(string)$data->get('operationKey'),(string)$data->get('token'));
    }
}
