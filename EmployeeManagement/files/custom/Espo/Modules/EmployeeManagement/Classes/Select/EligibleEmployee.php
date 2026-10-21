<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Classes\Select;

use Espo\Core\Select\Applier\AdditionalApplier;
use Espo\Core\Select\SearchParams;
use Espo\ORM\Query\SelectBuilder;

/** Mandatory native Select filter: applies to generic lists and Calendar alike. */
final class EligibleEmployee implements AdditionalApplier
{
    public function apply(SelectBuilder $queryBuilder, SearchParams $searchParams): void
    {
        $queryBuilder->join('assignedUser')->where(['assignedUser.type' => 'regular', 'assignedUser.isActive' => true]);
    }
}
