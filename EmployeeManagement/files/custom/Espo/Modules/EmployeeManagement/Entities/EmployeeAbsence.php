<?php
declare(strict_types=1);
namespace Espo\Modules\EmployeeManagement\Entities;

/** Minimal public Event; not a second holiday/accounting source. */
final class EmployeeAbsence extends \Espo\Core\Templates\Entities\Event
{
    public const ENTITY_TYPE = 'EmployeeAbsence';
}
