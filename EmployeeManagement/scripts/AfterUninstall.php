<?php
declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\ORM\EntityManager;

/** Native installer skips AfterUninstall during upgrade. Own cleanup only. Preserve balances, ledger, requests, files and settings. */
class AfterUninstall
{
    // Native AfterUninstall runs after module files are removed; keep cleanup self-contained.
    private const NAVIGATION_SCOPES = ['EmployeeManagement', 'EmployeeAttendance', 'EmployeeAttendanceOverview', 'EmployeeAdministration'];

    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $list = $config->get('calendarEntityList', []);
        if (!is_array($list)) { throw new RuntimeException('calendarEntityList must be an array.'); }
        $filtered = array_values(array_filter($list, fn ($name) => !in_array($name, ['EmployeeAbsence', 'EmployeeHolidayRequest'], true)));
        $tabs = $config->get('tabList', []);
        if (!is_array($tabs)) { throw new RuntimeException('tabList must be an array.'); }
        $filteredTabs = self::removeTabs($tabs);
        if ($filtered !== $list || $filteredTabs !== $tabs) {
            $writer = $container->getByClass(InjectableFactory::class)->create(ConfigWriter::class);
            if ($filtered !== $list) { $writer->set('calendarEntityList', $filtered); }
            if ($filteredTabs !== $tabs) { $writer->set('tabList', $filteredTabs); }
            $writer->save();
        }
        foreach ($container->getByClass(EntityManager::class)->getRDBRepository('ScheduledJob')
            ->where(['job' => 'ProcessEmployeeHolidayResets'])->find() as $job) {
            $job->set('status', 'Inactive');
            $container->getByClass(EntityManager::class)->saveEntity($job);
        }
    }
    private static function removeTabs(array $tabs): array
    {
        $result = [];
        foreach ($tabs as $item) {
            if (is_string($item)) {
                if (!in_array($item, self::NAVIGATION_SCOPES, true)) { $result[] = $item; }
                continue;
            }
            $group = (array) $item;
            if (isset($group['itemList'])) {
                $children = self::removeTabs($group['itemList']);
                if ($children === $group['itemList']) { $result[] = $item; continue; }
                if (!$children) { continue; }
                $group['itemList'] = $children;
                $item = is_object($item) ? (object) $group : $group;
            }
            $result[] = $item;
        }
        return $result;
    }
}
