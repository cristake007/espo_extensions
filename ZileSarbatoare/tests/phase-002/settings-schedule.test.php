<?php

declare(strict_types=1);

use Espo\Modules\ZileSarbatoare\Tools\NagerDate\Schedule;
use Espo\Modules\ZileSarbatoare\Tools\NagerDate\SettingsNormalizer;

$root = __DIR__ . '/../../files/custom/Espo/Modules/ZileSarbatoare/Tools/NagerDate';
require_once "$root/Settings.php";
require_once "$root/HolidayType.php";
require_once "$root/SettingsNormalizer.php";
require_once "$root/Schedule.php";

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . " Expected " . var_export($expected, true) .
            ", received " . var_export($actual, true) . '.');
    }
}

$timezone = new DateTimeZone('Europe/Bucharest');
$now = new DateTimeImmutable('2026-07-17 12:00:00', $timezone);
$normalizer = new SettingsNormalizer();
$schedule = new Schedule();
$defaults = $normalizer->normalize([], $now);

assertSameValue('RO', $defaults->countryCode, 'Country default is invalid.');
assertSameValue([2026, 2027], $defaults->years, 'Dynamic year defaults are invalid.');
assertSameValue('Weekly', $defaults->frequency, 'Frequency default is invalid.');

$daily = $normalizer->normalize(['frequency' => 'Daily'], $now);
assertSameValue(
    '2026-07-18 03:00:00 +03:00',
    $schedule->nextRun($daily, $now)?->format('Y-m-d H:i:s P'),
    'Daily next-run calculation is invalid.',
);

$weekly = $normalizer->normalize(['frequency' => 'Weekly', 'dayOfWeek' => '1'], $now);
assertSameValue(
    '2026-07-20 03:00:00 +03:00',
    $schedule->nextRun($weekly, $now)?->format('Y-m-d H:i:s P'),
    'Weekly next-run calculation is invalid.',
);

$monthly = $normalizer->normalize([
    'frequency' => 'Monthly',
    'dayOfMonth' => 31,
], new DateTimeImmutable('2026-04-29 12:00:00', $timezone));
assertSameValue(
    '2026-04-30 03:00:00 +03:00',
    $schedule->nextRun($monthly, new DateTimeImmutable('2026-04-29 12:00:00', $timezone))
        ?->format('Y-m-d H:i:s P'),
    'Monthly end-of-month calculation is invalid.',
);

$dueAt = new DateTimeImmutable('2026-07-18 03:05:00', $timezone);
assertSameValue(true, $schedule->isDue($daily, $dueAt, null), 'A newly reached daily slot must be due.');
assertSameValue(
    false,
    $schedule->isDue($daily, $dueAt, new DateTimeImmutable('2026-07-18 03:01:00', $timezone)),
    'An attempted slot must not retry in a tight loop.',
);

$manual = $normalizer->normalize(['frequency' => 'ManualOnly'], $now);
assertSameValue(null, $schedule->nextRun($manual, $now), 'Manual-only mode must not have a next run.');
assertSameValue(false, $schedule->isDue($manual, $now, null), 'Manual-only mode must never be due.');

$automaticDisabled = $normalizer->normalize(['automaticSync' => false], $now);
assertSameValue(
    null,
    $schedule->nextRun($automaticDisabled, $now),
    'Disabled automatic synchronization must not have a next run.',
);

$disabled = $normalizer->normalize(['enabled' => false], $now);
assertSameValue(null, $schedule->nextRun($disabled, $now), 'Disabled integration must not have a next run.');

try {
    $normalizer->normalize(['countryCode' => 'ROU'], $now);
    throw new RuntimeException('Invalid country code was accepted.');
} catch (InvalidArgumentException) {
}

echo "PHASE-002 settings and schedule tests passed.\n";
