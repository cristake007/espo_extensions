<?php

declare(strict_types=1);

namespace Espo\Modules\ZileSarbatoare\Services;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Utils\Config\ApplicationConfig;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\Schedule;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\SettingsNormalizer;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\SyncManager;
use Espo\Services\Integration as IntegrationService;
use InvalidArgumentException;
use stdClass;

final class NagerDate
{
    private const INTEGRATION = 'NagerDate';
    private const SETTING_FIELDS = [
        'enabled',
        'countryCode',
        'years',
        'holidayTypes',
        'nationalOnly',
        'automaticSync',
        'frequency',
        'timeOfDay',
        'dayOfWeek',
        'dayOfMonth',
    ];

    public function __construct(
        private IntegrationService $integrationService,
        private ApplicationConfig $applicationConfig,
        private SettingsNormalizer $normalizer,
        private Schedule $schedule,
        private SyncManager $syncManager,
    ) {}

    public function saveSettings(stdClass $data): stdClass
    {
        $input = get_object_vars($data);
        $unknown = array_diff(array_keys($input), self::SETTING_FIELDS);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown Nager.Date setting.');
        }

        $now = $this->now();
        $settings = $this->normalizer->normalize($input, $now);
        $values = $settings->toArray();
        $nextRun = $this->schedule->nextRun($settings, $now);
        $values['nextRunAt'] = $nextRun ? $this->storeDate($nextRun) : null;

        return $this->integrationService->update(self::INTEGRATION, (object) $values)->getValueMap();
    }

    public function status(): stdClass
    {
        $entity = $this->integrationService->read(self::INTEGRATION);
        $values = $entity->getValueMap();
        $now = $this->now();
        $settings = $this->normalizer->normalize(get_object_vars($values), $now);
        $nextRun = $this->schedule->nextRun($settings, $now);
        $values->nextRunAt = $nextRun ? $this->storeDate($nextRun) : null;

        return $values;
    }

    public function synchronize(): stdClass
    {
        return $this->syncManager->runManual();
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone($this->applicationConfig->getTimeZone()));
    }

    private function storeDate(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
