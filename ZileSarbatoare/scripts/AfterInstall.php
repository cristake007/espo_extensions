<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\Integration;
use Espo\ORM\EntityManager;

class AfterInstall
{
    private const ENTITY_TYPE = 'ZileLibere';
    private const INTEGRATION = 'NagerDate';

    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $configChanges = [];

        foreach (['calendarEntityList', 'tabList', 'quickCreateList'] as $listName) {
            $entityTypeList = $config->get($listName) ?? [];

            if (!is_array($entityTypeList)) {
                throw new RuntimeException("$listName must be an array.");
            }

            if (!in_array(self::ENTITY_TYPE, $entityTypeList, true)) {
                $entityTypeList[] = self::ENTITY_TYPE;
                $configChanges[$listName] = array_values($entityTypeList);
            }
        }

        $entityManager = $container->getByClass(EntityManager::class);
        $integration = $entityManager
            ->getRDBRepositoryByClass(Integration::class)
            ->getById(self::INTEGRATION);

        if ($integration && $integration->isNew()) {
            $integrations = $config->get('integrations') ?? (object) [];

            if (!$integrations instanceof stdClass) {
                throw new RuntimeException('integrations must be an object.');
            }

            $year = (int) (new DateTimeImmutable('now', new DateTimeZone(
                (string) ($config->get('timeZone') ?? 'UTC'),
            )))->format('Y');

            $integration->set([
                'enabled' => true,
                'countryCode' => 'RO',
                'years' => [(string) $year, (string) ($year + 1)],
                'holidayTypes' => ['Public'],
                'nationalOnly' => true,
                'automaticSync' => true,
                'frequency' => 'Weekly',
                'timeOfDay' => '03:00',
                'dayOfWeek' => '1',
                'dayOfMonth' => 1,
            ]);
            $entityManager->saveEntity($integration);

            $integrations->{self::INTEGRATION} = true;
            $configChanges['integrations'] = $integrations;
        }

        if ($configChanges === []) {
            return;
        }

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        foreach ($configChanges as $name => $value) {
            $configWriter->set($name, $value);
        }

        $configWriter->save();
    }
}
