<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class BeforeUninstall
{
    private const NAVIGATION_GROUP_ID = 'attendance-management';
    private const NAVIGATION_SCOPE_LIST = ['Attendance', 'AttendanceOverview'];

    /** @param array<string, mixed> $params */
    public function run(Container $container, array $params = []): void
    {
        $config = $container->getByClass(Config::class);
        $tabList = $config->get('tabList') ?? [];

        if (!is_array($tabList)) {
            throw new RuntimeException('tabList must be an array.');
        }

        $filtered = array_values(array_filter(
            $tabList,
            fn (mixed $item): bool =>
                !$this->isManagedScope($item) && !$this->isManagedGroup($item),
        ));

        if ($filtered === $tabList) {
            return;
        }

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);
        $configWriter->set('tabList', $filtered);
        $configWriter->save();
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
}
