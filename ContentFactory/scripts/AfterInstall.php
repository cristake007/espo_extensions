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
    private const NAVIGATION_GROUP_ID = 'content-factory';
    private const INTEGRATION_DEFAULTS = [
        'ContentFactoryN8n' => [
            'connectTimeoutSeconds' => 5,
            'responseTimeoutSeconds' => 90,
        ],
        'ContentFactoryWordPress' => [
            'connectTimeoutSeconds' => 5,
            'responseTimeoutSeconds' => 20,
        ],
    ];
    private const NAVIGATION_SCOPE_LIST = [
        'ContentFactoryGenerator',
        'ContentFactoryAnalytics',
        'ContentFactoryContent',
    ];

    /** @param array<string, mixed> $params */
    public function run(Container $container, array $params = []): void
    {
        $config = $container->getByClass(Config::class);
        $tabList = $config->get('tabList') ?? [];
        $configChanges = [];

        if (!is_array($tabList)) {
            throw new RuntimeException('tabList must be an array.');
        }

        $normalizedTabList = $this->normalizeTabList($tabList);

        if ($normalizedTabList !== $tabList) {
            $configChanges['tabList'] = $normalizedTabList;
        }

        $entityManager = $container->getByClass(EntityManager::class);
        $integrations = $config->get('integrations') ?? (object) [];

        if (!$integrations instanceof stdClass) {
            throw new RuntimeException('integrations must be an object.');
        }

        foreach (self::INTEGRATION_DEFAULTS as $integrationId => $defaults) {
            $integration = $entityManager
                ->getRDBRepositoryByClass(Integration::class)
                ->getById($integrationId);

            if (!$integration) {
                throw new RuntimeException("Content Factory integration record $integrationId is unavailable.");
            }

            if (!$integration->isNew()) {
                continue;
            }

            $integration->set(['enabled' => false] + $defaults);
            $entityManager->saveEntity($integration);
            $integrations->{$integrationId} = false;
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

    /**
     * @param array<int, mixed> $tabList
     * @return array<int, mixed>
     */
    private function normalizeTabList(array $tabList): array
    {
        $normalized = [];
        $groupAdded = false;

        foreach ($tabList as $item) {
            if ($this->isManagedScope($item) || $this->isManagedGroup($item)) {
                if (!$groupAdded) {
                    $normalized[] = $this->buildNavigationGroup();
                    $groupAdded = true;
                }

                continue;
            }

            $normalized[] = $item;
        }

        if (!$groupAdded) {
            $normalized[] = $this->buildNavigationGroup();
        }

        return array_values($normalized);
    }

    private function isManagedScope(mixed $item): bool
    {
        return is_string($item) && in_array($item, self::NAVIGATION_SCOPE_LIST, true);
    }

    private function isManagedGroup(mixed $item): bool
    {
        if (is_object($item)) {
            $item = (array) $item;
        }

        return is_array($item) && ($item['id'] ?? null) === self::NAVIGATION_GROUP_ID;
    }

    /** @return array<string, mixed> */
    private function buildNavigationGroup(): array
    {
        return [
            'type' => 'group',
            'id' => self::NAVIGATION_GROUP_ID,
            'text' => '$ContentFactory',
            'iconClass' => 'fas fa-magic',
            'itemList' => self::NAVIGATION_SCOPE_LIST,
        ];
    }
}
