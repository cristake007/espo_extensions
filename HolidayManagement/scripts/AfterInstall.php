<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    private const CALENDAR_ENTITY = 'HolidayRequest';
    private const NAVIGATION_ENTITY = 'HolidayRequest';

    /** @var array<string, mixed> */
    private const DEFAULTS = [
        'holidayManagementAnnualEntitlementDays' => null,
        'holidayManagementResetDate' => null,
        'holidayManagementResetCeilingDays' => 90,
        'holidayManagementResetWarningDays' => 80,
        'holidayManagementResetWarningRepeatDays' => 30,
        'holidayManagementNegativeBalanceLimitDays' => -21,
        'holidayManagementApproversIds' => [],
        'holidayManagementApprovalBlock1Title' => "",
        'holidayManagementApprovalBlock1Name' => "",
        'holidayManagementApprovalBlock2Title' => "",
        'holidayManagementApprovalBlock2Name' => "",
    ];

    public function run(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $missingDefaults = [];

        foreach (self::DEFAULTS as $name => $value) {
            if ($config->has($name)) {
                continue;
            }

            $missingDefaults[$name] = $value;
        }

        if (!$config->has('holidayManagementApproversNames')) {
            $missingDefaults['holidayManagementApproversNames'] = (object) [];
        }

        $calendarEntityList = $config->get('calendarEntityList') ?? [];
        $tabList = $config->get('tabList') ?? [];

        if (!is_array($calendarEntityList)) {
            throw new RuntimeException('calendarEntityList must be an array.');
        }

        if (!is_array($tabList)) {
            throw new RuntimeException('tabList must be an array.');
        }

        $calendarChanged = !in_array(self::CALENDAR_ENTITY, $calendarEntityList, true);

        if ($calendarChanged) {
            $calendarEntityList[] = self::CALENDAR_ENTITY;
        }

        $navigationChanged = !in_array(self::NAVIGATION_ENTITY, $tabList, true);

        if ($navigationChanged) {
            $tabList[] = self::NAVIGATION_ENTITY;
        }

        if ($missingDefaults === [] && !$calendarChanged && !$navigationChanged) {
            return;
        }

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        if ($missingDefaults !== []) {
            $configWriter->setMultiple($missingDefaults);
        }

        if ($calendarChanged) {
            $configWriter->set('calendarEntityList', array_values($calendarEntityList));
        }

        if ($navigationChanged) {
            $configWriter->set('tabList', array_values($tabList));
        }

        $configWriter->save();
    }
}
