<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    private const NAVIGATION_SCOPE = 'Attendance';

    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $tabList = $config->get('tabList') ?? [];

        if (!is_array($tabList)) {
            throw new RuntimeException('tabList must be an array.');
        }

        if (in_array(self::NAVIGATION_SCOPE, $tabList, true)) {
            return;
        }

        $tabList[] = self::NAVIGATION_SCOPE;
        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);
        $configWriter->set('tabList', array_values($tabList));
        $configWriter->save();
    }
}
