<?php

declare(strict_types=1);

namespace Espo\Modules\EmployeeManagement\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\EmployeeManagement\Tools\HolidayBalance\HolidayRequestService;
use stdClass;

/** Native reads plus a create adapter that always delegates to the accounting command service. */
final class EmployeeHolidayRequest extends \Espo\Core\Controllers\Record
{
    /**
     * Compatibility boundary for native entity create links and stale client metadata.
     * This is not generic CRUD: the actor, ACL/FLS, dates, balance and ledger are
     * validated and written exclusively by HolidayRequestService.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function actionCreate(array $params, stdClass $data, Request $request): array
    {
        $start = $data->dateStartDate ?? $data->dateStart ?? null;
        $end = $data->dateEndDate ?? $data->dateEnd ?? null;
        $description = $data->description ?? '';

        if (!is_string($start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $start) ||
            !is_string($end) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $end) ||
            !is_string($description) || strlen($description) > 2000) {
            throw new BadRequest('Invalid Holiday create fields.');
        }

        $key = $data->idempotencyKey ?? null;
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9._:-]{1,190}$/D', $key)) {
            $key = 'native-create:' . bin2hex(random_bytes(16));
        }

        return $this->injectableFactory->create(HolidayRequestService::class)->create([
            'idempotencyKey' => $key,
            'dateStart' => $start,
            'dateEnd' => $end,
            'description' => $description,
        ]);
    }
}
