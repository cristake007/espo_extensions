<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    private const ENTITY_TYPE = 'ZileLibere';

    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $calendarEntityList = $config->get('calendarEntityList') ?? [];

        if (!is_array($calendarEntityList)) {
            throw new RuntimeException('calendarEntityList must be an array.');
        }

        if (in_array(self::ENTITY_TYPE, $calendarEntityList, true)) {
            return;
        }

        $calendarEntityList[] = self::ENTITY_TYPE;

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $configWriter->set('calendarEntityList', array_values($calendarEntityList));
        $configWriter->save();
    }
}
