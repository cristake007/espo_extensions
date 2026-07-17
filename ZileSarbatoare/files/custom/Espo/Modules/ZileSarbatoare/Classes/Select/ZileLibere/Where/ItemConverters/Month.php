<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Classes\Select\ZileLibere\Where\ItemConverters;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Select\Where\Item;
use Espo\Core\Select\Where\ItemConverter;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Expression as Expr;
use Espo\ORM\Query\Part\WhereItem as WhereClauseItem;
use Espo\ORM\Query\SelectBuilder;

final class Month implements ItemConverter
{
    public function convert(SelectBuilder $queryBuilder, Item $item): WhereClauseItem
    {
        $values = is_array($item->getValue()) ? $item->getValue() : [$item->getValue()];
        $months = [];

        foreach ($values as $value) {
            if (is_string($value) && ctype_digit($value)) {
                $value = (int) $value;
            }

            if (!is_int($value) || $value < 1 || $value > 12) {
                throw new BadRequest('Month must be an integer between 1 and 12.');
            }

            $months[$value] = true;
        }

        if ($months === []) {
            throw new BadRequest('At least one month is required.');
        }

        return Cond::in(
            Expr::month(Expr::column('dateStart')),
            array_keys($months)
        );
    }
}
