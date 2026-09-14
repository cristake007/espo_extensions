<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    private const NAVIGATION_SCOPE_LIST = ['Attendance', 'AttendanceOverview'];

    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $tabList = $config->get('tabList') ?? [];
        $missingDefaults = [];

        if (!$config->has('attendanceManagementManagersIds')) {
            $missingDefaults['attendanceManagementManagersIds'] = [];
        }

        if (!$config->has('attendanceManagementManagersNames')) {
            $missingDefaults['attendanceManagementManagersNames'] = (object) [];
        }

        if (!is_array($tabList)) {
            throw new RuntimeException('tabList must be an array.');
        }

        $navigationChanged = false;

        foreach (self::NAVIGATION_SCOPE_LIST as $scope) {
            if (!in_array($scope, $tabList, true)) {
                $tabList[] = $scope;
                $navigationChanged = true;
            }
        }

        if ($missingDefaults === [] && !$navigationChanged) {
            return;
        }

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        if ($missingDefaults !== []) {
            $configWriter->setMultiple($missingDefaults);
        }

        if ($navigationChanged) {
            $configWriter->set('tabList', array_values($tabList));
        }

        $configWriter->save();
    }
}
