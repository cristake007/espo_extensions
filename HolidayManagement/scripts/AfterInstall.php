<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    /** @var array<string, int|string|null> */
    private const DEFAULTS = [
        'holidayManagementAnnualEntitlementDays' => null,
        'holidayManagementResetDate' => null,
        'holidayManagementResetCeilingDays' => 90,
        'holidayManagementResetWarningDays' => 80,
        'holidayManagementResetWarningRepeatDays' => 30,
        'holidayManagementNegativeBalanceLimitDays' => -21,
        'holidayManagementApproverRoleId' => null,
        'holidayManagementApproverRoleName' => null,
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

        if ($missingDefaults === []) {
            return;
        }

        $configWriter = $container->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $configWriter->setMultiple($missingDefaults);
        $configWriter->save();
    }
}
