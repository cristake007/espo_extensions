<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class BeforeUninstall
{
    private const CALENDAR_ENTITY = 'HolidayRequest';
    private const NAVIGATION_ENTITY = 'HolidayRequest';

    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $calendarEntityList = $config->get('calendarEntityList') ?? [];
        $tabList = $config->get('tabList') ?? [];

        if (!is_array($calendarEntityList)) {
            throw new RuntimeException('calendarEntityList must be an array.');
        }

        if (!is_array($tabList)) {
            throw new RuntimeException('tabList must be an array.');
        }

        $filtered = array_values(array_filter(
            $calendarEntityList,
            static fn (mixed $entityType): bool => $entityType !== self::CALENDAR_ENTITY,
        ));

        $filteredTabs = array_values(array_filter(
            $tabList,
            static fn (mixed $entityType): bool => $entityType !== self::NAVIGATION_ENTITY,
        ));

        if ($filtered === $calendarEntityList && $filteredTabs === $tabList) {
            return;
        }

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);
        if ($filtered !== $calendarEntityList) {
            $configWriter->set('calendarEntityList', $filtered);
        }

        if ($filteredTabs !== $tabList) {
            $configWriter->set('tabList', $filteredTabs);
        }

        $configWriter->save();
    }
}
