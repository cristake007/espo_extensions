<?php
declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\ScheduledJob\Populator;
use Espo\Modules\EmployeeManagement\Tools\Calendar\EmployeeAbsenceService;

/** Register Calendar sources without changing the administrator-managed sidebar. */
class AfterInstall
{
    public function run(Container $container): void
    {
        $factory = $container->getByClass(InjectableFactory::class);
        $config = $container->getByClass(Config::class);
        $list = $config->get('calendarEntityList', []);
        if (!is_array($list)) { throw new RuntimeException('calendarEntityList must be an array.'); }
        $updated = array_values(array_unique([...$list, 'EmployeeAbsence', 'EmployeeHolidayRequest']));
        $writer = $factory->create(ConfigWriter::class);
        if ($updated !== $list) { $writer->set('calendarEntityList', $updated); }
        if (!$config->has('employeeHolidayAutomaticAnnualGrantEnabled')) {
            $writer->set('employeeHolidayAutomaticAnnualGrantEnabled', false);
        }
        $writer->save();
        $factory->create(Populator::class)->populate();
        $factory->create(EmployeeAbsenceService::class)->rebuild();
    }
}
