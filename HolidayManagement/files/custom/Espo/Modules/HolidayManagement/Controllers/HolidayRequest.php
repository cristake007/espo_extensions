<?php

declare(strict_types=1);

namespace Espo\Modules\HolidayManagement\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\Where\Item as WhereItem;

final class HolidayRequest extends Record
{
    protected function fetchSearchParamsFromRequest(Request $request): SearchParams
    {
        $searchParams = parent::fetchSearchParamsFromRequest($request)
            ->withWhereAdded(WhereItem::fromRaw([
                'type' => 'equals',
                'attribute' => 'assignedUserId',
                'value' => $this->user->getId(),
            ]));

        $approverIds = $this->config->get('holidayManagementApproversIds') ?? [];

        if (is_array($approverIds) && in_array($this->user->getId(), $approverIds, true)) {
            $searchParams = $searchParams->withWhereAdded(WhereItem::fromRaw([
                'type' => 'notEquals',
                'attribute' => 'status',
                'value' => 'Pending',
            ]));
        }

        return $searchParams;
    }
}
