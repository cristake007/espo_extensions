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
        return parent::fetchSearchParamsFromRequest($request)
            ->withWhereAdded(WhereItem::fromRaw([
                'type' => 'equals',
                'attribute' => 'assignedUserId',
                'value' => $this->user->getId(),
            ]));
    }
}
