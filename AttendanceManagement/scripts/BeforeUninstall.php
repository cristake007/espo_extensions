<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class BeforeUninstall
{
    private const NAVIGATION_SCOPE_LIST = ['Attendance', 'AttendanceOverview'];

    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $tabList = $config->get('tabList') ?? [];

        if (!is_array($tabList)) {
            throw new RuntimeException('tabList must be an array.');
        }

        $filtered = array_values(array_filter(
            $tabList,
            static fn (mixed $scope): bool => !in_array(
                $scope,
                self::NAVIGATION_SCOPE_LIST,
                true,
            ),
        ));

        if ($filtered === $tabList) {
            return;
        }

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);
        $configWriter->set('tabList', $filtered);
        $configWriter->save();
    }
}
