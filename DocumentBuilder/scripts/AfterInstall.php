<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    private const NAVIGATION_GROUP_ID = 'document-builder';

    /**
     * @param array<string, mixed> $params
     */
    public function run(Container $container, array $params = []): void
    {
        $config = $container->getByClass(Config::class);
        $tabList = $config->get('tabList') ?? [];

        if (!is_array($tabList)) {
            throw new RuntimeException('tabList must be an array.');
        }

        $normalizedTabList = $this->normalizeTabList($tabList);

        if ($normalizedTabList === $tabList) {
            return;
        }

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $configWriter->set('tabList', $normalizedTabList);
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
            if (!$this->isManagedGroup($item)) {
                $normalized[] = $item;

                continue;
            }

            if ($groupAdded) {
                continue;
            }

            $normalized[] = $this->buildNavigationGroup();
            $groupAdded = true;
        }

        if (!$groupAdded) {
            $normalized[] = $this->buildNavigationGroup();
        }

        return array_values($normalized);
    }

    private function isManagedGroup(mixed $item): bool
    {
        if (is_object($item)) {
            $item = (array) $item;
        }

        return is_array($item) && ($item['id'] ?? null) === self::NAVIGATION_GROUP_ID;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildNavigationGroup(): array
    {
        return [
            'type' => 'group',
            'id' => self::NAVIGATION_GROUP_ID,
            'text' => '$DocumentBuilder',
            'iconClass' => 'fas fa-file-alt',
            'itemList' => ['DocumentBuilderTemplate'],
        ];
    }
}
