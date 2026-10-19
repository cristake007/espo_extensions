<?php

declare(strict_types=1);

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    private const NAVIGATION_GROUP_ID = 'attendance-management';
    private const NAVIGATION_SCOPE_LIST = ['Attendance', 'AttendanceOverview'];

    /** @param array<string, mixed> $params */
    public function run(Container $container, array $params = []): void
    {
        $config = $container->getByClass(Config::class);
        $tabList = $config->get('tabList') ?? [];
        $missingDefaults = [];

        if (!$config->has('attendanceManagementEditablePastMonths')) {
            $missingDefaults['attendanceManagementEditablePastMonths'] = 1;
        }

        if (!$config->has('attendanceManagementAllowIncompleteExports')) {
            $missingDefaults['attendanceManagementAllowIncompleteExports'] = false;
        }

        if (!is_array($tabList)) {
            throw new RuntimeException('tabList must be an array.');
        }

        $normalizedTabList = $this->normalizeTabList($tabList);
        $navigationChanged = $normalizedTabList !== $tabList;

        if ($missingDefaults === [] && !$navigationChanged) {
            return;
        }

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        if ($missingDefaults !== []) {
            $configWriter->setMultiple($missingDefaults);
        }

        if ($navigationChanged) {
            $configWriter->set('tabList', $normalizedTabList);
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
        $addedScopes = [];

        foreach ($tabList as $item) {
            if ($this->isManagedGroup($item)) {
                foreach (self::NAVIGATION_SCOPE_LIST as $scope) {
                    if (!isset($addedScopes[$scope])) {
                        $normalized[] = $scope;
                        $addedScopes[$scope] = true;
                    }
                }

                continue;
            }

            if ($this->isManagedScope($item)) {
                if (!isset($addedScopes[$item])) {
                    $normalized[] = $item;
                    $addedScopes[$item] = true;
                }

                continue;
            }

            $normalized[] = $item;
        }

        foreach (self::NAVIGATION_SCOPE_LIST as $scope) {
            if (!isset($addedScopes[$scope])) {
                $normalized[] = $scope;
            }
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

}
