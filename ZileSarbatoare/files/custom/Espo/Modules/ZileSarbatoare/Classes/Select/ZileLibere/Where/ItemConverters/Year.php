<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Classes\Select\ZileLibere\Where\ItemConverters;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Select\Where\Item;
use Espo\Core\Select\Where\ItemConverter;
use Espo\ORM\Query\Part\WhereClause;
use Espo\ORM\Query\Part\WhereItem as WhereClauseItem;
use Espo\ORM\Query\SelectBuilder;

final class Year implements ItemConverter
{
    public function convert(SelectBuilder $queryBuilder, Item $item): WhereClauseItem
    {
        $values = is_array($item->getValue()) ? $item->getValue() : [$item->getValue()];
        $years = [];

        foreach ($values as $value) {
            if (is_string($value) && ctype_digit($value)) {
                $value = (int) $value;
            }

            if (!is_int($value) || $value < 1 || $value > 9998) {
                throw new BadRequest('Year must be an integer between 1 and 9998.');
            }

            $years[$value] = true;
        }

        if ($years === []) {
            throw new BadRequest('At least one year is required.');
        }

        $ranges = [];

        foreach (array_keys($years) as $year) {
            $ranges[] = [
                'dateStart>=' => sprintf('%04d-01-01', $year),
                'dateStart<' => sprintf('%04d-01-01', $year + 1),
            ];
        }

        return WhereClause::fromRaw(['OR' => $ranges]);
    }
}
